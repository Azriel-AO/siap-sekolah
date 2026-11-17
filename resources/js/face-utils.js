import { FaceLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';

const WASM_PATH = '/vendor/mediapipe/wasm';
const LANDMARKER_MODEL_PATH = '/vendor/mediapipe/models/face_landmarker.task';

export const FACE_LANDMARK_COUNT = 478;
export const FACE_SIGNATURE_LENGTH = 11;
export const FACE_DISTANCE_THRESHOLD = 0.15;
export const FACE_MATCH_THRESHOLD = 0.75;
export const FACE_VERIFY_SIMILARITY_THRESHOLD = 0.72;
export const FACE_SIGNATURE_SIGMA = 0.084;
export const FACE_VERIFY_TIMEOUT_MS = 5000;
export const FACE_VERIFY_INTERVAL_MS = 250;
export const MAX_DETECT_CANVAS_WIDTH = 320;

const RIGID_ANCHOR_INDICES = [
    33, 133, 362, 263, 159, 145, 386, 374,
    168, 6, 197, 195, 5, 4, 1, 2, 98, 327,
    50, 280, 101, 330, 118, 347, 234, 454, 127, 356,
];

// Indeks ringan (8 titik wajah utama) untuk menghitung posisi tengah tanpa iterasi 478 titik di loop live
const CENTER_SAMPLE_INDICES = [33, 133, 263, 362, 168, 2, 234, 454];

let videoLandmarkerPromise = null;
let videoLandmarkerInstance = null;

let imageLandmarkerPromise = null;
let imageLandmarkerInstance = null;

// Buffer & canvas statis yang dipakai ulang (mencegah alokasi memori & GC spike berulang di main thread)
const _liveSignatureBuffer = new Float32Array(FACE_SIGNATURE_LENGTH);
const _tmpSignatureBuffer = new Float32Array(FACE_SIGNATURE_LENGTH);
const _verifySignatureBuffer = new Float32Array(FACE_SIGNATURE_LENGTH);
const _reusableCenterState = { centerX: 0, centerY: 0 };

let _scratchImgCanvas = null;
let _scratchImgCtx = null;
let _scratchFallbackCanvas = null;
let _scratchFallbackCtx = null;

async function createFaceLandmarker(runningMode) {
    const fileset = await FilesetResolver.forVisionTasks(WASM_PATH);
    const baseOptions = {
        modelAssetPath: LANDMARKER_MODEL_PATH,
        delegate: 'GPU',
    };
    const options = {
        baseOptions,
        runningMode,
        numFaces: 1,
        outputFaceBlendshapes: false,
        minFaceDetectionConfidence: 0.45,
        minFacePresenceConfidence: 0.45,
        minTrackingConfidence: 0.45,
    };

    try {
        return await FaceLandmarker.createFromOptions(fileset, options);
    } catch {
        baseOptions.delegate = 'CPU';

        return FaceLandmarker.createFromOptions(fileset, options);
    }
}

/**
 * Inisialisasi singleton FaceLandmarker dalam mode VIDEO (detectForVideo langsung dari elemen <video>).
 * Jauh lebih hemat CPU & RAM dibanding mode IMAGE karena menghindari salinan canvas RGBA per frame.
 */
export function getKioskVideoFaceLandmarker() {
    if (!videoLandmarkerPromise) {
        videoLandmarkerPromise = createFaceLandmarker('VIDEO')
            .then((instance) => {
                videoLandmarkerInstance = instance;

                return instance;
            })
            .catch((error) => {
                videoLandmarkerPromise = null;
                videoLandmarkerInstance = null;
                throw error;
            });
    }

    return videoLandmarkerPromise;
}

/**
 * Inisialisasi singleton FaceLandmarker dalam mode IMAGE (numFaces=1).
 * Digunakan hanya saat mengekstrak foto pendaftaran (<img>) atau verifikasi kartu 1:1.
 */
export function getVerifierFaceLandmarker() {
    if (!imageLandmarkerPromise) {
        imageLandmarkerPromise = createFaceLandmarker('IMAGE')
            .then((instance) => {
                imageLandmarkerInstance = instance;

                return instance;
            })
            .catch((error) => {
                imageLandmarkerPromise = null;
                imageLandmarkerInstance = null;
                throw error;
            });
    }

    return imageLandmarkerPromise;
}

/**
 * Tutup instance FaceLandmarker mode IMAGE dan kosongkan scratch canvas agar RAM tidak membengkak.
 */
export function closeImageFaceLandmarker() {
    if (imageLandmarkerInstance && typeof imageLandmarkerInstance.close === 'function') {
        try {
            imageLandmarkerInstance.close();
        } catch {
            // Abaikan kegagalan cleanup saat halaman ditutup
        }
    }
    imageLandmarkerInstance = null;
    imageLandmarkerPromise = null;

    if (_scratchImgCanvas) {
        _scratchImgCanvas.width = 0;
        _scratchImgCanvas.height = 0;
        _scratchImgCanvas = null;
        _scratchImgCtx = null;
    }
}

/**
 * Tutup seluruh instance FaceLandmarker (VIDEO & IMAGE) serta bebaskan seluruh offscreen canvas.
 * Wajib dipanggil saat komponen Kiosk dihancurkan atau halaman ditutup (pagehide/beforeunload).
 */
export function closeVerifierFaceLandmarker() {
    if (videoLandmarkerInstance && typeof videoLandmarkerInstance.close === 'function') {
        try {
            videoLandmarkerInstance.close();
        } catch {
            // Abaikan kegagalan cleanup saat halaman ditutup
        }
    }
    videoLandmarkerInstance = null;
    videoLandmarkerPromise = null;

    if (_scratchFallbackCanvas) {
        _scratchFallbackCanvas.width = 0;
        _scratchFallbackCanvas.height = 0;
        _scratchFallbackCanvas = null;
        _scratchFallbackCtx = null;
    }

    closeImageFaceLandmarker();
}

/**
 * Hitung rasio yaw horizontal wajah dari ujung hidung (1) dan pipi luar (234, 454).
 * Nilai mendekati 0 berarti menghadap lurus ke kamera.
 *
 * @param {Array<{x: number, y: number, z?: number}>} landmarks
 * @returns {number}
 */
export function computePoseYawRatio(landmarks) {
    if (!Array.isArray(landmarks) || landmarks.length < 455) {
        return 0;
    }

    const nose = landmarks[1];
    const leftCheek = landmarks[234];
    const rightCheek = landmarks[454];
    const cheekSpan = Math.max(0.001, Math.abs(Number(rightCheek?.x ?? 0) - Number(leftCheek?.x ?? 0)));
    const minCheekX = Math.min(Number(leftCheek?.x ?? 0), Number(rightCheek?.x ?? 0));

    return ((Number(nose?.x ?? 0) - minCheekX) / cheekSpan) - 0.5;
}

/**
 * Ekstrak signature ringan 11 rasio jarak kraniofasial dari landmark wajah MediaPipe.
 * Operasi murni aritmatika O(1) pada ~16 titik kunci tanpa matriks 3D ataupun alokasi array baru.
 * Invarian terhadap translasi, skala jarak ke kamera, rasio aspek gambar, serta rotasi ringan.
 *
 * @param {Array<{x: number, y: number, z?: number}>} landmarks
 * @param {number} [imageWidth=1]
 * @param {number} [imageHeight=1]
 * @param {Float32Array} [outBuffer]
 * @returns {Float32Array|null}
 */
export function extractSignatureFromLandmarks(landmarks, imageWidth = 1, imageHeight = 1, outBuffer = null) {
    if (!Array.isArray(landmarks) || landmarks.length < 455) {
        return null;
    }

    const w = Number(imageWidth) > 0 ? Number(imageWidth) : 1;
    const h = Number(imageHeight) > 0 ? Number(imageHeight) : 1;
    const aspect = h / w;
    const out = outBuffer || _liveSignatureBuffer;

    const rEyeO = landmarks[33];
    const rEyeI = landmarks[133];
    const lEyeI = landmarks[362];
    const lEyeO = landmarks[263];
    const bridge = landmarks[168];
    const tip = landmarks[4];
    const subnasale = landmarks[2];
    const alaR = landmarks[98];
    const alaL = landmarks[327];
    const cheekR = landmarks[234];
    const cheekL = landmarks[454];
    const malarR = landmarks[50];
    const malarL = landmarks[280];
    const browR = landmarks[105];
    const browL = landmarks[334];
    const jawR = landmarks[172];
    const jawL = landmarks[397];

    const rEx = 0.5 * (rEyeO.x + rEyeI.x);
    const rEy = 0.5 * (rEyeO.y + rEyeI.y) * aspect;
    const rEz = 0.5 * ((rEyeO.z ?? 0) + (rEyeI.z ?? 0)) * 0.45;

    const lEx = 0.5 * (lEyeI.x + lEyeO.x);
    const lEy = 0.5 * (lEyeI.y + lEyeO.y) * aspect;
    const lEz = 0.5 * ((lEyeI.z ?? 0) + (lEyeO.z ?? 0)) * 0.45;

    const iod = Math.hypot(lEx - rEx, lEy - rEy, lEz - rEz);
    if (iod <= 1e-6) {
        return null;
    }

    const ex = 0.5 * (rEx + lEx);
    const ey = 0.5 * (rEy + lEy);
    const ez = 0.5 * (rEz + lEz);

    const dist = (a, b) => Math.hypot(a.x - b.x, (a.y - b.y) * aspect, ((a.z ?? 0) - (b.z ?? 0)) * 0.45);

    out[0] = dist(rEyeI, lEyeI) / iod;
    out[1] = dist(rEyeO, lEyeO) / iod;
    out[2] = (dist(rEyeO, rEyeI) + dist(lEyeI, lEyeO)) / (2 * iod);
    out[3] = dist(alaR, alaL) / iod;
    out[4] = dist(bridge, subnasale) / iod;
    out[5] = Math.hypot(ex - tip.x, ey - tip.y * aspect, ez - (tip.z ?? 0) * 0.45) / iod;
    out[6] = dist(cheekR, cheekL) / iod;
    out[7] = dist(malarR, malarL) / iod;
    out[8] = Math.hypot(ex - subnasale.x, ey - subnasale.y * aspect, ez - (subnasale.z ?? 0) * 0.45) / iod;
    out[9] = (dist(browR, rEyeO) + dist(browL, lEyeO)) / (2 * iod);
    out[10] = dist(jawR, jawL) / iod;

    return out;
}

/**
 * Ekstrak signature ringan 11 rasio yang identik dari vektor template 1434-float tersimpan (`template.json`).
 * Dipanggil 1x per siswa saat cache template dimuat, sehingga loop kamera tidak mengolah 1434 float lagi.
 *
 * @param {Float32Array|Array<number>} vec
 * @param {Float32Array} [outBuffer]
 * @returns {Float32Array|null}
 */
export function extractSignatureFromTemplate(vec, outBuffer = null) {
    if (!vec || vec.length < 455 * 3) {
        return null;
    }

    const out = outBuffer || new Float32Array(FACE_SIGNATURE_LENGTH);

    const dist = (i, j) => {
        const bi = i * 3;
        const bj = j * 3;
        return Math.hypot(vec[bi] - vec[bj], vec[bi + 1] - vec[bj + 1], (vec[bi + 2] - vec[bj + 2]) * 0.45);
    };

    const rEx = 0.5 * (vec[33 * 3] + vec[133 * 3]);
    const rEy = 0.5 * (vec[33 * 3 + 1] + vec[133 * 3 + 1]);
    const rEz = 0.5 * (vec[33 * 3 + 2] + vec[133 * 3 + 2]) * 0.45;

    const lEx = 0.5 * (vec[362 * 3] + vec[263 * 3]);
    const lEy = 0.5 * (vec[362 * 3 + 1] + vec[263 * 3 + 1]);
    const lEz = 0.5 * (vec[362 * 3 + 2] + vec[263 * 3 + 2]) * 0.45;

    const iod = Math.hypot(lEx - rEx, lEy - rEy, lEz - rEz);
    if (iod <= 1e-6) {
        return null;
    }

    const ex = 0.5 * (rEx + lEx);
    const ey = 0.5 * (rEy + lEy);
    const ez = 0.5 * (rEz + lEz);

    const distEyeMid = (j) => {
        const bj = j * 3;
        return Math.hypot(ex - vec[bj], ey - vec[bj + 1], ez - vec[bj + 2] * 0.45);
    };

    out[0] = dist(133, 362) / iod;
    out[1] = dist(33, 263) / iod;
    out[2] = (dist(33, 133) + dist(362, 263)) / (2 * iod);
    out[3] = dist(98, 327) / iod;
    out[4] = dist(168, 2) / iod;
    out[5] = distEyeMid(4) / iod;
    out[6] = dist(234, 454) / iod;
    out[7] = dist(50, 280) / iod;
    out[8] = distEyeMid(2) / iod;
    out[9] = (dist(105, 33) + dist(334, 263)) / (2 * iod);
    out[10] = dist(172, 397) / iod;

    return out;
}

/**
 * Hitung kemiripan dua signature ringan menggunakan Euclidean Distance sederhana.
 * Tidak ada alokasi objek atau perkalian matriks 3D.
 *
 * @param {Float32Array|Array<number>} sigA
 * @param {Float32Array|Array<number>} sigB
 * @param {number} [sigma=FACE_SIGNATURE_SIGMA]
 * @returns {number}
 */
export function computeSignatureSimilarity(sigA, sigB, sigma = FACE_SIGNATURE_SIGMA) {
    if (!sigA || !sigB || sigA.length === 0 || sigA.length !== sigB.length) {
        return 0;
    }

    let sumSquares = 0;
    const len = sigA.length;
    for (let i = 0; i < len; i += 1) {
        const diff = sigA[i] - sigB[i];
        sumSquares += diff * diff;
    }

    const rms = Math.sqrt(sumSquares / len);
    const safeSigma = Math.max(0.01, Number(sigma) || FACE_SIGNATURE_SIGMA);
    const ratio = rms / safeSigma;

    return Math.max(0, Math.min(1, Math.exp(-(ratio * ratio))));
}

/**
 * Siapkan cache `_signature` pada setiap item template yang siap pakai sekali di muka.
 *
 * @param {Array<object>} templates
 * @returns {Array<object>}
 */
export function prepareTemplateSignatures(templates) {
    if (!Array.isArray(templates)) {
        return [];
    }

    const ready = [];
    for (let i = 0; i < templates.length; i += 1) {
        const item = templates[i];
        if (!item || !Array.isArray(item.template) || item.template.length < 455 * 3) {
            continue;
        }
        if (!item._signature) {
            const sig = extractSignatureFromTemplate(item.template);
            if (!sig) {
                continue;
            }
            item._signature = sig;
        }
        ready.push(item);
    }

    return ready;
}

/**
 * Normalisasi 478 titik landmark MediaPipe (x, y, z) menjadi vektor 1434 float
 * (dipakai satu kali saat pendaftaran / hidrasi awal agar format `template.json` tetap 1434 float).
 *
 * @param {Array<{x: number, y: number, z?: number}>} landmarks
 * @param {number} [imageWidth=1]
 * @param {number} [imageHeight=1]
 * @returns {Float32Array|null}
 */
export function flattenAndNormalizeLandmarks(landmarks, imageWidth = 1, imageHeight = 1) {
    if (!Array.isArray(landmarks) || landmarks.length < 468) {
        return null;
    }

    const w = Number(imageWidth) > 0 ? Number(imageWidth) : 1;
    const h = Number(imageHeight) > 0 ? Number(imageHeight) : 1;
    const aspect = h / w;
    const count = Math.min(landmarks.length, FACE_LANDMARK_COUNT);

    const p33 = landmarks[33];
    const p133 = landmarks[133];
    const p362 = landmarks[362];
    const p263 = landmarks[263];
    const p168 = landmarks[168];
    const p2 = landmarks[2];

    const rEx = 0.5 * (p33.x + p133.x);
    const rEy = 0.5 * (p33.y + p133.y) * aspect;
    const rEz = 0.5 * ((p33.z ?? 0) + (p133.z ?? 0));

    const lEx = 0.5 * (p362.x + p263.x);
    const lEy = 0.5 * (p362.y + p263.y) * aspect;
    const lEz = 0.5 * ((p362.z ?? 0) + (p263.z ?? 0));

    const eyeVecX = lEx - rEx;
    const eyeVecY = lEy - rEy;
    const eyeVecZ = lEz - rEz;
    const eyeSpan = Math.hypot(eyeVecX, eyeVecY, eyeVecZ);
    if (eyeSpan <= 1e-8) {
        return null;
    }

    let ox = 0;
    let oy = 0;
    let oz = 0;
    let anchorCount = 0;
    for (let k = 0; k < RIGID_ANCHOR_INDICES.length; k += 1) {
        const idx = RIGID_ANCHOR_INDICES[k];
        if (idx < count) {
            const pt = landmarks[idx];
            ox += Number(pt?.x ?? 0);
            oy += Number(pt?.y ?? 0) * aspect;
            oz += Number(pt?.z ?? 0);
            anchorCount += 1;
        }
    }
    if (anchorCount === 0) {
        return null;
    }
    ox /= anchorCount;
    oy /= anchorCount;
    oz /= anchorCount;

    const ux0 = eyeVecX / eyeSpan;
    const ux1 = eyeVecY / eyeSpan;
    const ux2 = eyeVecZ / eyeSpan;

    const downX = p2.x - p168.x;
    const downY = (p2.y - p168.y) * aspect;
    const downZ = (p2.z ?? 0) - (p168.z ?? 0);
    const dotDownUx = downX * ux0 + downY * ux1 + downZ * ux2;
    const rawUyX = downX - dotDownUx * ux0;
    const rawUyY = downY - dotDownUx * ux1;
    const rawUyZ = downZ - dotDownUx * ux2;
    const uyLen = Math.hypot(rawUyX, rawUyY, rawUyZ);
    if (uyLen <= 1e-8) {
        return null;
    }

    const uy0 = rawUyX / uyLen;
    const uy1 = rawUyY / uyLen;
    const uy2 = rawUyZ / uyLen;

    const rawUzX = ux1 * uy2 - ux2 * uy1;
    const rawUzY = ux2 * uy0 - ux0 * uy2;
    const rawUzZ = ux0 * uy1 - ux1 * uy0;
    const uzLen = Math.hypot(rawUzX, rawUzY, rawUzZ) || 1;
    const uz0 = rawUzX / uzLen;
    const uz1 = rawUzY / uzLen;
    const uz2 = rawUzZ / uzLen;

    const normalized = new Float32Array(count * 3);
    for (let i = 0; i < count; i += 1) {
        const pt = landmarks[i];
        const px = Number(pt?.x ?? 0) - ox;
        const py = Number(pt?.y ?? 0) * aspect - oy;
        const pz = Number(pt?.z ?? 0) - oz;
        const base = i * 3;
        normalized[base] = (px * ux0 + py * ux1 + pz * ux2) / eyeSpan;
        normalized[base + 1] = (px * uy0 + py * uy1 + pz * uy2) / eyeSpan;
        normalized[base + 2] = (px * uz0 + py * uz1 + pz * uz2) / eyeSpan;
    }

    return normalized;
}

/**
 * Hitung rata-rata (opsional berbobot) dari kumpulan vektor landmark ternormalisasi.
 *
 * @param {Array<Float32Array|Array<number>>} vectors
 * @param {Array<number>|null} [weights=null]
 * @returns {Float32Array|null}
 */
export function computeAverageVector(vectors, weights = null) {
    const valid = [];
    const validWeights = [];

    for (let i = 0; i < (vectors || []).length; i += 1) {
        const vec = vectors[i];
        if (!vec || vec.length === 0) {
            continue;
        }
        const weight = Array.isArray(weights) && Number.isFinite(weights[i]) ? Math.max(0, Number(weights[i])) : 1;
        valid.push(vec);
        validWeights.push(weight);
    }

    if (valid.length === 0) {
        return null;
    }

    const length = valid[0].length;
    const weightSum = validWeights.reduce((acc, w) => acc + w, 0);
    const useUniform = weightSum <= 1e-8;
    const denom = useUniform ? valid.length : weightSum;
    const avg = new Float32Array(length);

    for (let k = 0; k < valid.length; k += 1) {
        const vec = valid[k];
        if (vec.length !== length) {
            return null;
        }
        const factor = (useUniform ? 1 : validWeights[k]) / denom;
        for (let i = 0; i < length; i += 1) {
            avg[i] += vec[i] * factor;
        }
    }

    return avg;
}

/**
 * Hitung Euclidean Distance antara dua vektor 1D.
 *
 * @param {Float32Array|Array<number>} vecA
 * @param {Float32Array|Array<number>} vecB
 * @returns {number}
 */
export function computeEuclideanDistance(vecA, vecB) {
    if (!vecA || !vecB || vecA.length === 0 || vecA.length !== vecB.length) {
        return Number.POSITIVE_INFINITY;
    }

    let sumSquares = 0;
    for (let i = 0; i < vecA.length; i += 1) {
        const diff = vecA[i] - vecB[i];
        sumSquares += diff * diff;
    }

    return Math.sqrt(sumSquares);
}

/**
 * Hitung Cosine Similarity antara dua vektor 1D.
 *
 * @param {Float32Array|Array<number>} vecA
 * @param {Float32Array|Array<number>} vecB
 * @returns {number}
 */
export function computeCosineSimilarity(vecA, vecB) {
    if (!vecA || !vecB || vecA.length === 0 || vecA.length !== vecB.length) {
        return 0;
    }

    let dot = 0;
    let normA = 0;
    let normB = 0;
    const len = vecA.length;

    for (let i = 0; i < len; i += 1) {
        const a = vecA[i];
        const b = vecB[i];
        dot += a * b;
        normA += a * a;
        normB += b * b;
    }

    if (normA <= 1e-8 || normB <= 1e-8) {
        return 0;
    }

    const similarity = dot / (Math.sqrt(normA) * Math.sqrt(normB));
    return Math.max(-1, Math.min(1, similarity));
}

/**
 * Cari siswa dengan kemiripan wajah terbaik (1:N matching) menggunakan signature ringan 11 rasio.
 * Menerima baik live signature (11 float) maupun live vector (1434 float), tanpa matriks 3D berat.
 * Mencetak Debug Score ke browser console untuk setiap kandidat siswa yang dibandingkan.
 *
 * @param {Float32Array|Array<number>} liveInput
 * @param {Array<{student_id: number, nama?: string, student_name?: string, card_label?: string, template: Array<number>, _signature?: Float32Array}>} templates
 * @param {number} [threshold=FACE_MATCH_THRESHOLD]
 * @returns {{matched: boolean, bestMatch: object|null, bestSimilarity: number}}
 */
export function findBestFaceMatch(liveInput, templates, threshold = FACE_MATCH_THRESHOLD) {
    if (!liveInput || !Array.isArray(templates) || templates.length === 0) {
        return { matched: false, bestMatch: null, bestSimilarity: 0 };
    }

    let liveSig = null;
    if (liveInput.length === FACE_SIGNATURE_LENGTH) {
        liveSig = liveInput;
    } else if (liveInput.length >= 455 * 3) {
        liveSig = extractSignatureFromTemplate(liveInput, _tmpSignatureBuffer);
    }

    if (!liveSig) {
        return { matched: false, bestMatch: null, bestSimilarity: 0 };
    }

    let bestItem = null;
    let bestSim = -1;

    for (let i = 0; i < templates.length; i += 1) {
        const item = templates[i];
        if (!item) {
            continue;
        }

        let itemSig = item._signature;
        if (!itemSig && Array.isArray(item.template) && item.template.length >= 455 * 3) {
            itemSig = extractSignatureFromTemplate(item.template);
            if (itemSig) {
                item._signature = itemSig;
            }
        }

        if (!itemSig || itemSig.length !== FACE_SIGNATURE_LENGTH) {
            continue;
        }

        const sim = computeSignatureSimilarity(liveSig, itemSig);
        const nama = item.nama || item.student_name || `Siswa #${item.student_id ?? '?'}`;
        console.log(`Matching dengan ${nama}: Score ${sim.toFixed(4)}`);

        if (sim > bestSim) {
            bestSim = sim;
            bestItem = item;
        }
    }

    if (bestSim < 0) {
        bestSim = 0;
    }

    const matched = bestSim >= threshold && bestItem !== null;

    return {
        matched,
        bestMatch: matched ? { ...bestItem, similarity: Number(bestSim.toFixed(4)) } : null,
        bestSimilarity: Number(bestSim.toFixed(4)),
    };
}

/**
 * Muat data URL JPEG base64 menjadi HTMLImageElement siap deteksi.
 *
 * @param {string} dataUrl
 * @returns {Promise<HTMLImageElement>}
 */
function loadImageFromDataUrl(dataUrl) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = (err) => reject(err);
        img.src = dataUrl;
    });
}

function getScratchImageCanvas(targetW, targetH) {
    if (!_scratchImgCanvas) {
        _scratchImgCanvas = document.createElement('canvas');
        _scratchImgCtx = _scratchImgCanvas.getContext('2d');
    }
    if (_scratchImgCanvas.width !== targetW || _scratchImgCanvas.height !== targetH) {
        _scratchImgCanvas.width = targetW;
        _scratchImgCanvas.height = targetH;
    }

    return { canvas: _scratchImgCanvas, ctx: _scratchImgCtx };
}

/**
 * Ekstrak vektor landmark ternormalisasi beserta estimasi sudut yaw dari 1 data URL foto referensi.
 * Menggambar ke satu canvas scratch yang diperkecil (maks. 320px) agar tidak membebani memori.
 *
 * @param {FaceLandmarker} landmarker
 * @param {string} dataUrl
 * @returns {Promise<{vector: Float32Array, yawRatio: number}|null>}
 */
export async function extractVectorFromDataUrlWithMeta(landmarker, dataUrl) {
    let img = null;
    try {
        img = await loadImageFromDataUrl(dataUrl);
        const rawW = img.naturalWidth || img.width || 320;
        const rawH = img.naturalHeight || img.height || 240;
        const scale = rawW > MAX_DETECT_CANVAS_WIDTH ? MAX_DETECT_CANVAS_WIDTH / rawW : 1;
        const targetW = Math.max(1, Math.round(rawW * scale));
        const targetH = Math.max(1, Math.round(rawH * scale));

        const { canvas, ctx } = getScratchImageCanvas(targetW, targetH);
        ctx.drawImage(img, 0, 0, targetW, targetH);

        const result = landmarker.detect(canvas);
        const landmarks = result?.faceLandmarks?.[0];
        const vector = flattenAndNormalizeLandmarks(landmarks, targetW, targetH);
        if (!vector) {
            return null;
        }

        return {
            vector,
            yawRatio: computePoseYawRatio(landmarks),
        };
    } catch {
        return null;
    } finally {
        if (img) {
            img.onload = null;
            img.onerror = null;
            img.src = '';
        }
    }
}

/**
 * Ekstrak vektor landmark ternormalisasi dari 1 data URL foto referensi.
 *
 * @param {FaceLandmarker} landmarker
 * @param {string} dataUrl
 * @returns {Promise<Float32Array|null>}
 */
export async function extractVectorFromDataUrl(landmarker, dataUrl) {
    const extracted = await extractVectorFromDataUrlWithMeta(landmarker, dataUrl);
    return extracted ? extracted.vector : null;
}

/**
 * Ekstrak template vektor wajah 1434-float dari array foto pendaftaran (maks. 3 foto base64).
 *
 * @param {Array<string>} facePhotos
 * @param {{closeImageLandmarkerAfter?: boolean}} [options]
 * @returns {Promise<Array<number>|null>}
 */
export async function extractTemplateFromPhotoDataUrls(facePhotos, options = {}) {
    if (!Array.isArray(facePhotos) || facePhotos.length === 0) {
        return null;
    }

    const landmarker = await getVerifierFaceLandmarker();
    const vectors = [];
    const weights = [];

    try {
        for (const dataUrl of facePhotos) {
            if (typeof dataUrl !== 'string' || !dataUrl.startsWith('data:image/')) {
                continue;
            }
            const item = await extractVectorFromDataUrlWithMeta(landmarker, dataUrl);
            if (item?.vector) {
                const poseRatio = item.yawRatio / 0.16;
                vectors.push(item.vector);
                weights.push(Math.exp(-(poseRatio * poseRatio)));
            }
        }

        const avgVector = computeAverageVector(vectors, weights);
        if (!avgVector) {
            return null;
        }

        return Array.from(avgVector, (val) => Number(val.toFixed(6)));
    } finally {
        if (_scratchImgCanvas) {
            _scratchImgCanvas.width = 0;
            _scratchImgCanvas.height = 0;
            _scratchImgCanvas = null;
            _scratchImgCtx = null;
        }
        if (options?.closeImageLandmarkerAfter) {
            closeImageFaceLandmarker();
        }
    }
}

/**
 * Hitung koordinat pusat wajah dalam koordinat viewport dengan mempertimbangkan object-fit: cover.
 *
 * @param {Array<{x: number, y: number, z?: number}>} landmarks
 * @param {HTMLVideoElement} video
 * @param {{centerX: number, centerY: number}|null} [outCenter]
 * @returns {{centerX: number, centerY: number}|null}
 */
export function computeFaceCenterInViewport(landmarks, video, outCenter = null) {
    if (!Array.isArray(landmarks) || landmarks.length < 455 || !video || typeof video.getBoundingClientRect !== 'function') {
        return null;
    }

    const videoRect = video.getBoundingClientRect();
    if (!videoRect || videoRect.width <= 0 || videoRect.height <= 0) {
        return null;
    }

    let sumX = 0;
    let sumY = 0;
    const sampleCount = CENTER_SAMPLE_INDICES.length;
    for (let k = 0; k < sampleCount; k += 1) {
        const pt = landmarks[CENTER_SAMPLE_INDICES[k]];
        sumX += Number(pt?.x ?? 0);
        sumY += Number(pt?.y ?? 0);
    }

    const normCenterX = sumX / sampleCount;
    const normCenterY = sumY / sampleCount;

    const videoW = video.videoWidth || videoRect.width;
    const videoH = video.videoHeight || videoRect.height;
    const scale = Math.max(videoRect.width / videoW, videoRect.height / videoH);
    const renderedW = videoW * scale;
    const renderedH = videoH * scale;
    const offsetX = (videoRect.width - renderedW) / 2;
    const offsetY = (videoRect.height - renderedH) / 2;

    const target = outCenter || _reusableCenterState;
    target.centerX = videoRect.left + offsetX + normCenterX * renderedW;
    target.centerY = videoRect.top + offsetY + normCenterY * renderedH;

    return target;
}

/**
 * Periksa apakah titik (x, y) berada di dalam area DOMRect overlay panduan wajah.
 *
 * @param {number} x
 * @param {number} y
 * @param {DOMRect|{left: number, right: number, top: number, bottom: number, width: number, height: number}|null} rect
 * @returns {boolean}
 */
export function isPointInsideGuideRect(x, y, rect) {
    if (!rect || rect.width <= 0 || rect.height <= 0) {
        return false;
    }

    return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
}

function detectLandmarksFromVideo(landmarker, video, timestampMs, sharedCanvas = null) {
    if (typeof landmarker.detectForVideo === 'function') {
        const result = landmarker.detectForVideo(video, timestampMs);

        return result?.multiFaceLandmarks?.[0] || result?.faceLandmarks?.[0] || null;
    }

    // Fallback aman bila menerima landmarker mode IMAGE
    const srcW = video.videoWidth;
    const srcH = video.videoHeight;
    const scale = srcW > MAX_DETECT_CANVAS_WIDTH ? MAX_DETECT_CANVAS_WIDTH / srcW : 1;
    const targetW = Math.max(1, Math.round(srcW * scale));
    const targetH = Math.max(1, Math.round(srcH * scale));

    let canvas = sharedCanvas;
    let ctx = null;
    if (canvas) {
        if (canvas.width !== targetW || canvas.height !== targetH) {
            canvas.width = targetW;
            canvas.height = targetH;
        }
        ctx = canvas.getContext('2d');
    } else {
        if (!_scratchFallbackCanvas) {
            _scratchFallbackCanvas = document.createElement('canvas');
            _scratchFallbackCtx = _scratchFallbackCanvas.getContext('2d');
        }
        if (_scratchFallbackCanvas.width !== targetW || _scratchFallbackCanvas.height !== targetH) {
            _scratchFallbackCanvas.width = targetW;
            _scratchFallbackCanvas.height = targetH;
        }
        canvas = _scratchFallbackCanvas;
        ctx = _scratchFallbackCtx;
    }

    ctx.drawImage(video, 0, 0, targetW, targetH);
    const result = landmarker.detect(canvas);

    return result?.multiFaceLandmarks?.[0] || result?.faceLandmarks?.[0] || null;
}

/**
 * Ekstrak signature ringan 11-rasio dari frame video tanpa membuat canvas baru atau alokasi array.
 * Menggunakan detectForVideo langsung dari elemen <video> saat tersedia.
 *
 * @param {FaceLandmarker} landmarker
 * @param {HTMLVideoElement} video
 * @param {number} [timestampMs=performance.now()]
 * @param {{guideElement?: Element|null, sharedCanvas?: HTMLCanvasElement|null, outSignature?: Float32Array|null, onDetectionState?: (state: {faceDetected: boolean, insideGuide: boolean, yawRatio: number}) => void}} [options]
 * @returns {Float32Array|null}
 */
export function extractSignatureFromVideo(landmarker, video, timestampMs = performance.now(), options = {}) {
    const stateCb = options?.onDetectionState;

    if (!landmarker || !video || video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
        stateCb?.({ faceDetected: false, insideGuide: false, yawRatio: 0 });
        return null;
    }

    try {
        const landmarks = detectLandmarksFromVideo(landmarker, video, timestampMs, options?.sharedCanvas || null);
        if (!Array.isArray(landmarks) || landmarks.length < 455) {
            stateCb?.({ faceDetected: false, insideGuide: false, yawRatio: 0 });
            return null;
        }

        const yawRatio = computePoseYawRatio(landmarks);
        const guideElement = options?.guideElement
            ?? (typeof document !== 'undefined' ? document.querySelector('.face-guide-overlay') : null);

        if (guideElement) {
            const faceCenter = computeFaceCenterInViewport(landmarks, video, _reusableCenterState);
            const overlayRect = guideElement.getBoundingClientRect();
            const insideGuide = Boolean(
                faceCenter && isPointInsideGuideRect(faceCenter.centerX, faceCenter.centerY, overlayRect)
            );

            if (!insideGuide) {
                stateCb?.({ faceDetected: true, insideGuide: false, yawRatio });
                return null;
            }
        }

        stateCb?.({ faceDetected: true, insideGuide: true, yawRatio });

        return extractSignatureFromLandmarks(
            landmarks,
            video.videoWidth,
            video.videoHeight,
            options?.outSignature || _liveSignatureBuffer
        );
    } catch {
        stateCb?.({ faceDetected: false, insideGuide: false, yawRatio: 0 });
        return null;
    }
}

/**
 * Adapter kompatibilitas untuk ekstraksi vektor 1434-float dari video.
 */
export function extractVectorFromVideo(landmarker, video, sharedCanvas = null, options = {}) {
    const stateCb = options?.onDetectionState;
    if (!landmarker || !video || video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
        stateCb?.({ faceDetected: false, insideGuide: false, center: null, yawRatio: 0 });
        return null;
    }

    try {
        const landmarks = detectLandmarksFromVideo(landmarker, video, performance.now(), sharedCanvas);
        if (!Array.isArray(landmarks) || landmarks.length < 468) {
            stateCb?.({ faceDetected: false, insideGuide: false, center: null, yawRatio: 0 });
            return null;
        }

        const yawRatio = computePoseYawRatio(landmarks);
        const guideElement = options?.guideElement
            ?? (typeof document !== 'undefined' ? document.querySelector('.face-guide-overlay') : null);
        const faceCenter = computeFaceCenterInViewport(landmarks, video, _reusableCenterState);

        if (guideElement) {
            const overlayRect = guideElement.getBoundingClientRect();
            const insideGuide = Boolean(
                faceCenter && isPointInsideGuideRect(faceCenter.centerX, faceCenter.centerY, overlayRect)
            );

            if (!insideGuide) {
                stateCb?.({ faceDetected: true, insideGuide: false, center: faceCenter, yawRatio });
                return null;
            }
        }

        stateCb?.({ faceDetected: true, insideGuide: true, center: faceCenter, yawRatio });

        return flattenAndNormalizeLandmarks(landmarks, video.videoWidth, video.videoHeight);
    } catch {
        stateCb?.({ faceDetected: false, insideGuide: false, center: null, yawRatio: 0 });
        return null;
    }
}

/**
 * Jalankan verifikasi wajah 1:1 di browser maksimal selama timeoutMs (default 5000 ms)
 * menggunakan signature ringan 11-rasio dan mode VIDEO tanpa membebani CPU/RAM.
 */
export async function verifyStudentFace1to1({
    facePhotos,
    video,
    timeoutMs = FACE_VERIFY_TIMEOUT_MS,
    threshold = FACE_VERIFY_SIMILARITY_THRESHOLD,
    onProgress = () => {},
}) {
    if (!Array.isArray(facePhotos) || facePhotos.length === 0) {
        return { matched: false, matchScore: 0, distance: Infinity, reason: 'no_reference_photos' };
    }

    onProgress({ stage: 'loading_model', message: 'Memuat pendeteksi landmark wajah…' });
    const imgLandmarker = await getVerifierFaceLandmarker();

    onProgress({ stage: 'extracting_reference', message: 'Mengekstrak landmark 3 foto referensi siswa…' });
    let refSignature = null;
    try {
        const refVectors = [];
        const refWeights = [];
        for (const photoDataUrl of facePhotos) {
            try {
                const item = await extractVectorFromDataUrlWithMeta(imgLandmarker, photoDataUrl);
                if (item?.vector) {
                    const poseRatio = item.yawRatio / 0.16;
                    refVectors.push(item.vector);
                    refWeights.push(Math.exp(-(poseRatio * poseRatio)));
                }
            } catch {
                // Abaikan kesalahan pembacaan foto referensi individual
            }
        }

        const avgReferenceVector = computeAverageVector(refVectors, refWeights);
        refSignature = avgReferenceVector
            ? extractSignatureFromTemplate(avgReferenceVector, _verifySignatureBuffer)
            : null;
    } finally {
        closeImageFaceLandmarker();
    }

    if (!refSignature) {
        return { matched: false, matchScore: 0, distance: Infinity, reason: 'reference_landmarks_empty' };
    }

    const videoLandmarker = await getKioskVideoFaceLandmarker();
    const similarityThreshold = threshold > 0 && threshold < 0.4 ? FACE_VERIFY_SIMILARITY_THRESHOLD : threshold;
    const startedAt = performance.now();
    let lastTimestampMs = 0;
    let bestSimilarity = 0;
    let bestDistance = Number.POSITIVE_INFINITY;
    let noFaceSince = null;
    let lowScoreSince = null;
    const detectionState = { faceDetected: false, insideGuide: false, yawRatio: 0 };

    onProgress({ stage: 'matching_live', message: 'Mencocokkan wajah dengan kamera... Pastikan wajah terkena cahaya.' });

    while (performance.now() - startedAt < timeoutMs) {
        lastTimestampMs = Math.max(lastTimestampMs + 1, Math.floor(performance.now()));
        const liveSig = extractSignatureFromVideo(videoLandmarker, video, lastTimestampMs, {
            outSignature: _liveSignatureBuffer,
            onDetectionState: (state) => {
                detectionState.faceDetected = state.faceDetected;
                detectionState.insideGuide = state.insideGuide;
                detectionState.yawRatio = state.yawRatio;
            },
        });

        const nowMs = performance.now();

        if (liveSig) {
            noFaceSince = null;
            const similarity = computeSignatureSimilarity(liveSig, refSignature);
            const distance = Number((1 - similarity).toFixed(4));
            if (similarity > bestSimilarity) {
                bestSimilarity = similarity;
                bestDistance = distance;
            }
            console.log(`Matching verifikasi 1:1: Score ${similarity.toFixed(4)}`);

            if (similarity < 0.60) {
                if (lowScoreSince === null) {
                    lowScoreSince = nowMs;
                }
            } else {
                lowScoreSince = null;
            }

            const isLowScoreSustained = lowScoreSince !== null && (nowMs - lowScoreSince) >= 3000;

            onProgress({
                stage: isLowScoreSustained ? 'low_score' : 'matching_live',
                distance,
                bestDistance: Number.isFinite(bestDistance) ? Number(bestDistance.toFixed(4)) : null,
                similarity: Number(similarity.toFixed(4)),
                message: isLowScoreSustained
                    ? 'Wajah tidak dikenali. Pastikan wajah menghadap kamera dan cahaya cukup.'
                    : 'Wajah terdeteksi, memverifikasi kecocokan landmark...',
            });

            if (similarity >= similarityThreshold) {
                const matchScore = Math.min(0.9999, Math.max(0.86, Number(similarity.toFixed(4))));

                return {
                    matched: true,
                    matchScore,
                    distance,
                    reason: 'matched',
                };
            }
        } else if (detectionState.faceDetected && !detectionState.insideGuide) {
            noFaceSince = null;
            lowScoreSince = null;
            onProgress({
                stage: 'outside_guide',
                bestDistance: Number.isFinite(bestDistance) ? Number(bestDistance.toFixed(4)) : null,
                message: 'Geser wajah ke tengah kotak panduan...',
            });
        } else {
            lowScoreSince = null;
            if (noFaceSince === null) {
                noFaceSince = nowMs;
            }
            const noFaceSustained = (nowMs - noFaceSince) >= 3000;
            onProgress({
                stage: 'waiting_face',
                bestDistance: Number.isFinite(bestDistance) ? Number(bestDistance.toFixed(4)) : null,
                message: noFaceSustained
                    ? 'Wajah tidak terdeteksi. Pastikan cahaya cukup dan wajah menghadap kamera.'
                    : 'Arahkan wajah ke tengah kamera, pastikan wajah terkena cahaya.',
            });
        }

        await new Promise((resolve) => setTimeout(resolve, FACE_VERIFY_INTERVAL_MS));
    }

    return {
        matched: false,
        matchScore: Number(Math.min(0.85, bestSimilarity).toFixed(4)),
        distance: Number.isFinite(bestDistance) ? Number(bestDistance.toFixed(4)) : Infinity,
        reason: 'timeout_mismatch',
    };
}
