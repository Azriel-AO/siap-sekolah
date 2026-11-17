import { FaceLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';

const WASM_PATH = '/vendor/mediapipe/wasm';
const LANDMARKER_MODEL_PATH = '/vendor/mediapipe/models/face_landmarker.task';

export const FACE_LANDMARK_COUNT = 478;
export const FACE_DISTANCE_THRESHOLD = 0.15;
export const FACE_MATCH_THRESHOLD = 0.75;
export const FACE_VERIFY_SIMILARITY_THRESHOLD = 0.72;
export const FACE_SIMILARITY_SIGMA = 0.065;
export const FACE_VERIFY_TIMEOUT_MS = 5000;
export const FACE_VERIFY_INTERVAL_MS = 180;

/**
 * Titik jangkar kranial kaku (mata, pangkal/puncak hidung, tulang pipi, pelipis)
 * yang stabil terhadap ekspresi mulut dan dipakai untuk alignment 3D Orthogonal Procrustes.
 */
const RIGID_ANCHOR_INDICES = [
    33, 133, 362, 263, 159, 145, 386, 374,
    168, 6, 197, 195, 5, 4, 1, 2, 98, 327,
    50, 280, 101, 330, 118, 347, 234, 454, 127, 356,
];

const NOSE_LANDMARK_SET = new Set([
    168, 6, 197, 195, 5, 4, 1, 19, 94, 2, 98, 97, 326, 327,
    48, 115, 220, 45, 275, 440, 344, 278, 64, 294, 49, 279, 102, 331,
]);

const BROW_EYE_LANDMARK_SET = new Set([
    33, 133, 159, 145, 158, 153, 263, 362, 386, 374, 385, 380,
    70, 63, 105, 66, 107, 55, 65, 52, 53, 46,
    300, 293, 334, 296, 336, 285, 295, 282, 283, 276,
]);

const DYNAMIC_EXPRESSION_SET = new Set([
    0, 11, 12, 13, 14, 15, 16, 17, 37, 39, 40, 61, 72, 73, 74, 76, 77, 78, 80, 81,
    82, 84, 85, 86, 87, 88, 89, 90, 91, 95, 96, 146, 178, 179, 180, 181, 183, 184, 185, 191,
    267, 269, 270, 291, 302, 303, 304, 306, 307, 308, 310, 311, 312, 314, 315, 316, 317, 318, 319, 320,
    321, 324, 325, 375, 402, 403, 404, 405, 407, 408, 409, 415,
    468, 469, 470, 471, 472, 473, 474, 475, 476, 477,
]);

let verifierLandmarkerPromise = null;

/**
 * Inisialisasi singleton FaceLandmarker dalam mode IMAGE (numFaces=1).
 * Mode IMAGE dipakai untuk mengekstrak 478 landmark dari foto referensi (<img>)
 * maupun dari frame kamera live yang digambar ke offscreen <canvas>.
 */
export function getVerifierFaceLandmarker() {
    if (!verifierLandmarkerPromise) {
        verifierLandmarkerPromise = (async () => {
            const fileset = await FilesetResolver.forVisionTasks(WASM_PATH);
            const baseOptions = {
                modelAssetPath: LANDMARKER_MODEL_PATH,
                delegate: 'GPU',
            };
            const options = {
                baseOptions,
                runningMode: 'IMAGE',
                numFaces: 1,
                outputFaceBlendshapes: false,
                minFaceDetectionConfidence: 0.45,
                minFacePresenceConfidence: 0.45,
            };

            try {
                return await FaceLandmarker.createFromOptions(fileset, options);
            } catch (gpuError) {
                baseOptions.delegate = 'CPU';

                return FaceLandmarker.createFromOptions(fileset, options);
            }
        })().catch((error) => {
            verifierLandmarkerPromise = null;
            throw error;
        });
    }

    return verifierLandmarkerPromise;
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
 * Normalisasi 478 titik landmark MediaPipe (x, y, z) menjadi vektor 1434 float
 * yang invarian terhadap rasio aspek gambar, translasi, skala jarak ke kamera, dan rotasi 3D kepala:
 * 1. Koreksi rasio aspek isotropik: y dikali (imageHeight / imageWidth) agar setara skala x dan z.
 * 2. Centering 3-sumbu independen menggunakan centroid jangkar kaku kranial (mata + hidung + pipi).
 * 3. Alignment basis ortonormal 3D (mata-ke-mata u_x, sumbu vertikal glabella->subnasale u_y, normal u_z).
 * 4. Skala seragam terhadap jarak inter-okular 3D (Inter-Ocular Distance = 1.0).
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

    const pts = new Array(count);
    for (let i = 0; i < count; i += 1) {
        const pt = landmarks[i];
        pts[i] = [
            Number(pt?.x ?? 0),
            Number(pt?.y ?? 0) * aspect,
            Number(pt?.z ?? 0),
        ];
    }

    const rEye = [
        0.5 * (pts[33][0] + pts[133][0]),
        0.5 * (pts[33][1] + pts[133][1]),
        0.5 * (pts[33][2] + pts[133][2]),
    ];
    const lEye = [
        0.5 * (pts[362][0] + pts[263][0]),
        0.5 * (pts[362][1] + pts[263][1]),
        0.5 * (pts[362][2] + pts[263][2]),
    ];

    const eyeVecX = lEye[0] - rEye[0];
    const eyeVecY = lEye[1] - rEye[1];
    const eyeVecZ = lEye[2] - rEye[2];
    const eyeSpan = Math.hypot(eyeVecX, eyeVecY, eyeVecZ);
    if (eyeSpan <= 1e-8) {
        return null;
    }

    let ox = 0;
    let oy = 0;
    let oz = 0;
    let anchorCount = 0;
    for (const idx of RIGID_ANCHOR_INDICES) {
        if (idx < count) {
            ox += pts[idx][0];
            oy += pts[idx][1];
            oz += pts[idx][2];
            anchorCount += 1;
        }
    }
    if (anchorCount === 0) {
        return null;
    }
    ox /= anchorCount;
    oy /= anchorCount;
    oz /= anchorCount;

    const ux = [eyeVecX / eyeSpan, eyeVecY / eyeSpan, eyeVecZ / eyeSpan];
    const downX = pts[2][0] - pts[168][0];
    const downY = pts[2][1] - pts[168][1];
    const downZ = pts[2][2] - pts[168][2];
    const dotDownUx = downX * ux[0] + downY * ux[1] + downZ * ux[2];
    const rawUyX = downX - dotDownUx * ux[0];
    const rawUyY = downY - dotDownUx * ux[1];
    const rawUyZ = downZ - dotDownUx * ux[2];
    const uyLen = Math.hypot(rawUyX, rawUyY, rawUyZ);
    if (uyLen <= 1e-8) {
        return null;
    }
    const uy = [rawUyX / uyLen, rawUyY / uyLen, rawUyZ / uyLen];

    const rawUzX = ux[1] * uy[2] - ux[2] * uy[1];
    const rawUzY = ux[2] * uy[0] - ux[0] * uy[2];
    const rawUzZ = ux[0] * uy[1] - ux[1] * uy[0];
    const uzLen = Math.hypot(rawUzX, rawUzY, rawUzZ) || 1;
    const uz = [rawUzX / uzLen, rawUzY / uzLen, rawUzZ / uzLen];

    const normalized = new Float32Array(count * 3);
    for (let i = 0; i < count; i += 1) {
        const px = pts[i][0] - ox;
        const py = pts[i][1] - oy;
        const pz = pts[i][2] - oz;
        const base = i * 3;
        normalized[base] = (px * ux[0] + py * ux[1] + pz * ux[2]) / eyeSpan;
        normalized[base + 1] = (px * uy[0] + py * uy[1] + pz * uy[2]) / eyeSpan;
        normalized[base + 2] = (px * uz[0] + py * uz[1] + pz * uz[2]) / eyeSpan;
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
 * Hitung Euclidean Distance antara dua vektor landmark 1D.
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
 * Hitung Cosine Similarity antara dua vektor landmark 1D (misal 1434 float).
 * Mengembalikan nilai antara -1.0 sampai 1.0 (1.0 = identik sempurna).
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
 * Solusi Horn Unit-Quaternion untuk rotasi ortogonal 3D terbaik (det=+1)
 * yang menyelaraskan srcPts ke dstPts pada ruang 3D kaku.
 */
function solveHornRotation3D(srcPts, dstPts) {
    let Sxx = 0;
    let Sxy = 0;
    let Sxz = 0;
    let Syx = 0;
    let Syy = 0;
    let Syz = 0;
    let Szx = 0;
    let Szy = 0;
    let Szz = 0;

    for (let i = 0; i < srcPts.length; i += 1) {
        const [px, py, pz] = srcPts[i];
        const [qx, qy, qz] = dstPts[i];
        Sxx += px * qx;
        Sxy += px * qy;
        Sxz += px * qz;
        Syx += py * qx;
        Syy += py * qy;
        Syz += py * qz;
        Szx += pz * qx;
        Szy += pz * qy;
        Szz += pz * qz;
    }

    const N = [
        [Sxx + Syy + Szz, Syz - Szy, Szx - Sxz, Sxy - Syx],
        [Syz - Szy, Sxx - Syy - Szz, Sxy + Syx, Szx + Sxz],
        [Szx - Sxz, Sxy + Syx, -Sxx + Syy - Szz, Syz + Szy],
        [Sxy - Syx, Szx + Sxz, Syz + Szy, -Sxx - Syy + Szz],
    ];
    const V = [
        [1, 0, 0, 0],
        [0, 1, 0, 0],
        [0, 0, 1, 0],
        [0, 0, 0, 1],
    ];

    for (let iter = 0; iter < 30; iter += 1) {
        let p = 0;
        let q = 1;
        let maxVal = Math.abs(N[0][1]);
        for (let i = 0; i < 4; i += 1) {
            for (let j = i + 1; j < 4; j += 1) {
                const absVal = Math.abs(N[i][j]);
                if (absVal > maxVal) {
                    maxVal = absVal;
                    p = i;
                    q = j;
                }
            }
        }
        if (maxVal < 1e-12) {
            break;
        }
        const app = N[p][p];
        const aqq = N[q][q];
        const apq = N[p][q];
        const phi = 0.5 * Math.atan2(2 * apq, aqq - app);
        const c = Math.cos(phi);
        const s = Math.sin(phi);

        for (let i = 0; i < 4; i += 1) {
            if (i !== p && i !== q) {
                const nip = N[i][p];
                const niq = N[i][q];
                N[i][p] = c * nip - s * niq;
                N[p][i] = N[i][p];
                N[i][q] = s * nip + c * niq;
                N[q][i] = N[i][q];
            }
        }
        N[p][p] = c * c * app - 2 * s * c * apq + s * s * aqq;
        N[q][q] = s * s * app + 2 * s * c * apq + c * c * aqq;
        N[p][q] = 0;
        N[q][p] = 0;

        for (let i = 0; i < 4; i += 1) {
            const vip = V[i][p];
            const viq = V[i][q];
            V[i][p] = c * vip - s * viq;
            V[i][q] = s * vip + c * viq;
        }
    }

    let bestCol = 0;
    for (let j = 1; j < 4; j += 1) {
        if (N[j][j] > N[bestCol][bestCol]) {
            bestCol = j;
        }
    }
    const qw = V[0][bestCol];
    const qx = V[1][bestCol];
    const qy = V[2][bestCol];
    const qz = V[3][bestCol];

    const R = [
        1 - 2 * (qy * qy + qz * qz), 2 * (qx * qy - qw * qz), 2 * (qx * qz + qw * qy),
        2 * (qx * qy + qw * qz), 1 - 2 * (qx * qx + qz * qz), 2 * (qy * qz - qw * qx),
        2 * (qx * qz - qw * qy), 2 * (qy * qz + qw * qx), 1 - 2 * (qx * qx + qy * qy),
    ];
    const Rt = [R[0], R[3], R[6], R[1], R[4], R[7], R[2], R[5], R[8]];

    let d1 = 0;
    let d2 = 0;
    for (let i = 0; i < srcPts.length; i += 1) {
        const [px, py, pz] = srcPts[i];
        const [dx, dy, dz] = dstPts[i];
        d1 += dx * (R[0] * px + R[1] * py + R[2] * pz)
            + dy * (R[3] * px + R[4] * py + R[5] * pz)
            + dz * (R[6] * px + R[7] * py + R[8] * pz);
        d2 += dx * (Rt[0] * px + Rt[1] * py + Rt[2] * pz)
            + dy * (Rt[3] * px + Rt[4] * py + Rt[5] * pz)
            + dz * (Rt[6] * px + Rt[7] * py + Rt[8] * pz);
    }

    return d1 >= d2 ? R : Rt;
}

/**
 * Hitung kemiripan wajah berbasis 3D Orthogonal Procrustes Alignment + bobot struktur kranial.
 * Mengembalikan nilai ternormalisasi [0, 1] (1.0 = identik sempurna).
 *
 * @param {Float32Array|Array<number>} vecA
 * @param {Float32Array|Array<number>} vecB
 * @param {number} [sigma=FACE_SIMILARITY_SIGMA]
 * @returns {number}
 */
export function computeProcrustesSimilarity(vecA, vecB, sigma = FACE_SIMILARITY_SIGMA) {
    if (!vecA || !vecB || vecA.length === 0 || vecA.length !== vecB.length) {
        return 0;
    }

    const landmarkCount = Math.floor(vecA.length / 3);
    if (landmarkCount < 468) {
        return 0;
    }

    const srcAnchors = [];
    const dstAnchors = [];
    for (const idx of RIGID_ANCHOR_INDICES) {
        if (idx < landmarkCount) {
            const base = idx * 3;
            srcAnchors.push([vecA[base], vecA[base + 1], vecA[base + 2]]);
            dstAnchors.push([vecB[base], vecB[base + 1], vecB[base + 2]]);
        }
    }

    const R = solveHornRotation3D(srcAnchors, dstAnchors);
    let weightedSumSquares = 0;
    let weightTotal = 0;

    for (let i = 0; i < landmarkCount; i += 1) {
        const base = i * 3;
        const ax = vecA[base];
        const ay = vecA[base + 1];
        const az = vecA[base + 2];

        const rx = R[0] * ax + R[1] * ay + R[2] * az;
        const ry = R[3] * ax + R[4] * ay + R[5] * az;
        const rz = R[6] * ax + R[7] * ay + R[8] * az;

        let weight = 0.7;
        if (DYNAMIC_EXPRESSION_SET.has(i)) {
            weight = 0.1;
        } else if (NOSE_LANDMARK_SET.has(i)) {
            weight = 2.0;
        } else if (BROW_EYE_LANDMARK_SET.has(i)) {
            weight = 1.6;
        }

        const dx = rx - vecB[base];
        const dy = ry - vecB[base + 1];
        const dz = (rz - vecB[base + 2]) * 0.5;

        weightedSumSquares += weight * (dx * dx + dy * dy + dz * dz);
        weightTotal += weight;
    }

    if (weightTotal <= 1e-8) {
        return 0;
    }

    const rms = Math.sqrt(weightedSumSquares / weightTotal);
    const safeSigma = Math.max(0.01, Number(sigma) || FACE_SIMILARITY_SIGMA);
    const ratio = rms / safeSigma;

    return Math.max(0, Math.min(1, Math.exp(-(ratio * ratio))));
}

/**
 * Cari siswa dengan kemiripan wajah terbaik (1:N matching) dari daftar template.
 * Mencetak Debug Score ke browser console untuk setiap kandidat siswa yang dibandingkan.
 *
 * @param {Float32Array|Array<number>} liveVector
 * @param {Array<{student_id: number, nama?: string, student_name?: string, card_label?: string, template: Array<number>}>} templates
 * @param {number} [threshold=FACE_MATCH_THRESHOLD]
 * @returns {{matched: boolean, bestMatch: object|null, bestSimilarity: number}}
 */
export function findBestFaceMatch(liveVector, templates, threshold = FACE_MATCH_THRESHOLD) {
    if (!liveVector || !Array.isArray(templates) || templates.length === 0) {
        return { matched: false, bestMatch: null, bestSimilarity: 0 };
    }

    let bestItem = null;
    let bestSim = -1;

    for (const item of templates) {
        if (!item || !Array.isArray(item.template) || item.template.length !== liveVector.length) {
            continue;
        }

        const sim = computeProcrustesSimilarity(liveVector, item.template);
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

/**
 * Ekstrak vektor landmark ternormalisasi beserta estimasi sudut yaw dari 1 data URL foto referensi.
 *
 * @param {FaceLandmarker} landmarker
 * @param {string} dataUrl
 * @returns {Promise<{vector: Float32Array, yawRatio: number}|null>}
 */
export async function extractVectorFromDataUrlWithMeta(landmarker, dataUrl) {
    try {
        const img = await loadImageFromDataUrl(dataUrl);
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth || img.width || 320;
        canvas.height = img.naturalHeight || img.height || 240;
        canvas.getContext('2d').drawImage(img, 0, 0);

        const result = landmarker.detect(canvas);
        const landmarks = result?.faceLandmarks?.[0];
        const vector = flattenAndNormalizeLandmarks(landmarks, canvas.width, canvas.height);
        if (!vector) {
            return null;
        }

        return {
            vector,
            yawRatio: computePoseYawRatio(landmarks),
        };
    } catch {
        return null;
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
 * Memberi bobot tinggi pada pose menghadap lurus kamera (|yawRatio| kecil) agar foto menoleh
 * untuk liveness tidak merusak ketajaman geometri frontal.
 *
 * @param {Array<string>} facePhotos
 * @returns {Promise<Array<number>|null>}
 */
export async function extractTemplateFromPhotoDataUrls(facePhotos) {
    if (!Array.isArray(facePhotos) || facePhotos.length === 0) {
        return null;
    }

    const landmarker = await getVerifierFaceLandmarker();
    const vectors = [];
    const weights = [];

    for (const dataUrl of facePhotos) {
        if (typeof dataUrl !== 'string' || !dataUrl.startsWith('data:image/')) {
            continue;
        }
        const item = await extractVectorFromDataUrlWithMeta(landmarker, dataUrl);
        if (item?.vector) {
            const poseRatio = item.yawRatio / 0.16;
            const weight = Math.exp(-(poseRatio * poseRatio));
            vectors.push(item.vector);
            weights.push(weight);
        }
    }

    const avgVector = computeAverageVector(vectors, weights);
    if (!avgVector) {
        return null;
    }

    return Array.from(avgVector, (val) => Number(val.toFixed(6)));
}

/**
 * Hitung koordinat pusat wajah (rata-rata x, y dari seluruh landmark) dalam koordinat viewport
 * dengan memperhitungkan aspect ratio video dan CSS object-fit: cover.
 *
 * @param {Array<{x: number, y: number, z?: number}>} landmarks
 * @param {HTMLVideoElement} video
 * @returns {{centerX: number, centerY: number, bounds: {minX: number, maxX: number, minY: number, maxY: number}}|null}
 */
export function computeFaceCenterInViewport(landmarks, video) {
    if (!Array.isArray(landmarks) || landmarks.length === 0 || !video || typeof video.getBoundingClientRect !== 'function') {
        return null;
    }

    const videoRect = video.getBoundingClientRect();
    if (!videoRect || videoRect.width <= 0 || videoRect.height <= 0) {
        return null;
    }

    let sumX = 0;
    let sumY = 0;
    let minX = 1;
    let maxX = 0;
    let minY = 1;
    let maxY = 0;
    const count = landmarks.length;

    for (let i = 0; i < count; i += 1) {
        const pt = landmarks[i];
        const x = Number(pt?.x ?? 0);
        const y = Number(pt?.y ?? 0);
        sumX += x;
        sumY += y;
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
    }

    const normCenterX = sumX / count;
    const normCenterY = sumY / count;

    const videoW = video.videoWidth || videoRect.width;
    const videoH = video.videoHeight || videoRect.height;
    const scale = Math.max(videoRect.width / videoW, videoRect.height / videoH);
    const renderedW = videoW * scale;
    const renderedH = videoH * scale;
    const offsetX = (videoRect.width - renderedW) / 2;
    const offsetY = (videoRect.height - renderedH) / 2;

    return {
        centerX: videoRect.left + offsetX + normCenterX * renderedW,
        centerY: videoRect.top + offsetY + normCenterY * renderedH,
        bounds: { minX, maxX, minY, maxY },
    };
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

/**
 * Ekstrak vektor landmark ternormalisasi dari frame live elemen <video>.
 * Hanya memproses landmark jika pusat wajah berada di dalam elemen `.face-guide-overlay` (bila tersedia).
 * Menggunakan proses matematis yang 100% identik dengan extractVectorFromDataUrl.
 *
 * @param {FaceLandmarker} landmarker
 * @param {HTMLVideoElement} video
 * @param {HTMLCanvasElement} [sharedCanvas]
 * @param {{guideElement?: Element|null, onDetectionState?: (state: {faceDetected: boolean, insideGuide: boolean, center: {centerX: number, centerY: number}|null, yawRatio?: number}) => void}} [options]
 * @returns {Float32Array|null}
 */
export function extractVectorFromVideo(landmarker, video, sharedCanvas = null, options = {}) {
    if (!video || video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
        options?.onDetectionState?.({ faceDetected: false, insideGuide: false, center: null, yawRatio: 0 });
        return null;
    }

    try {
        const canvas = sharedCanvas || document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        const result = landmarker.detect(canvas);
        const landmarks = result?.multiFaceLandmarks?.[0] || result?.faceLandmarks?.[0];

        if (!Array.isArray(landmarks) || landmarks.length < 468) {
            options?.onDetectionState?.({ faceDetected: false, insideGuide: false, center: null, yawRatio: 0 });
            return null;
        }

        const yawRatio = computePoseYawRatio(landmarks);
        const guideElement = options?.guideElement
            ?? (typeof document !== 'undefined' ? document.querySelector('.face-guide-overlay') : null);
        const faceCenter = computeFaceCenterInViewport(landmarks, video);

        if (guideElement) {
            const overlayRect = guideElement.getBoundingClientRect();
            const insideGuide = Boolean(
                faceCenter && isPointInsideGuideRect(faceCenter.centerX, faceCenter.centerY, overlayRect)
            );

            if (!insideGuide) {
                options?.onDetectionState?.({ faceDetected: true, insideGuide: false, center: faceCenter, yawRatio });
                return null;
            }
        }

        options?.onDetectionState?.({ faceDetected: true, insideGuide: true, center: faceCenter, yawRatio });

        return flattenAndNormalizeLandmarks(landmarks, canvas.width, canvas.height);
    } catch {
        options?.onDetectionState?.({ faceDetected: false, insideGuide: false, center: null, yawRatio: 0 });
        return null;
    }
}

/**
 * Jalankan verifikasi wajah 1:1 di browser maksimal selama timeoutMs (default 5000 ms).
 * Mengembalikan objek { matched, matchScore, distance, reason }.
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
    const landmarker = await getVerifierFaceLandmarker();

    onProgress({ stage: 'extracting_reference', message: 'Mengekstrak landmark 3 foto referensi siswa…' });
    const refVectors = [];
    const refWeights = [];
    for (const photoDataUrl of facePhotos) {
        try {
            const item = await extractVectorFromDataUrlWithMeta(landmarker, photoDataUrl);
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
    if (!avgReferenceVector) {
        return { matched: false, matchScore: 0, distance: Infinity, reason: 'reference_landmarks_empty' };
    }

    const similarityThreshold = threshold > 0 && threshold < 0.4 ? FACE_VERIFY_SIMILARITY_THRESHOLD : threshold;
    const offscreenCanvas = document.createElement('canvas');
    const startedAt = performance.now();
    let bestSimilarity = 0;
    let bestDistance = Number.POSITIVE_INFINITY;
    let noFaceSince = null;
    let lowScoreSince = null;

    onProgress({ stage: 'matching_live', message: 'Mencocokkan wajah dengan kamera... Pastikan wajah terkena cahaya.' });

    while (performance.now() - startedAt < timeoutMs) {
        let detectionState = { faceDetected: false, insideGuide: false, yawRatio: 0 };
        const liveVector = extractVectorFromVideo(landmarker, video, offscreenCanvas, {
            onDetectionState: (state) => {
                if (state) {
                    detectionState = state;
                }
            },
        });

        const nowMs = performance.now();

        if (liveVector) {
            noFaceSince = null;
            const similarity = computeProcrustesSimilarity(liveVector, avgReferenceVector);
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
