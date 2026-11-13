import { FaceLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';

const WASM_PATH = '/vendor/mediapipe/wasm';
const LANDMARKER_MODEL_PATH = '/vendor/mediapipe/models/face_landmarker.task';

export const FACE_LANDMARK_COUNT = 478;
export const FACE_DISTANCE_THRESHOLD = 0.15;
export const FACE_VERIFY_TIMEOUT_MS = 5000;
export const FACE_VERIFY_INTERVAL_MS = 180;

let verifierLandmarkerPromise = null;

/**
 * Inisialisasi singleton FaceLandmarker dalam mode IMAGE (numFaces=1).
 * Mode IMAGE dipakai untuk mengekstrak 478 landmark dari 3 foto referensi (<img>)
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
 * Flatten 478 titik landmark (x, y, z) menjadi array 1434 float,
 * kemudian normalisasi dengan mengurangi rata-rata (mean) dan membagi dengan L2 norm.
 *
 * @param {Array<{x: number, y: number, z?: number}>} landmarks
 * @returns {Float32Array|null}
 */
export function flattenAndNormalizeLandmarks(landmarks) {
    if (!Array.isArray(landmarks) || landmarks.length < 468) {
        return null;
    }

    const count = Math.min(landmarks.length, FACE_LANDMARK_COUNT);
    const length = count * 3;
    const raw = new Float32Array(length);

    let sum = 0;
    let idx = 0;
    for (let i = 0; i < count; i += 1) {
        const pt = landmarks[i];
        const x = Number(pt?.x ?? 0);
        const y = Number(pt?.y ?? 0);
        const z = Number(pt?.z ?? 0);
        raw[idx] = x;
        raw[idx + 1] = y;
        raw[idx + 2] = z;
        sum += x + y + z;
        idx += 3;
    }

    const mean = sum / length;
    let sumSquares = 0;
    for (let i = 0; i < length; i += 1) {
        const centered = raw[i] - mean;
        raw[i] = centered;
        sumSquares += centered * centered;
    }

    const l2Norm = Math.sqrt(sumSquares);
    if (l2Norm <= 1e-8) {
        return null;
    }

    for (let i = 0; i < length; i += 1) {
        raw[i] /= l2Norm;
    }

    return raw;
}

/**
 * Hitung rata-rata vektor dari kumpulan vektor ternormalisasi (misal 3 foto enrollment).
 *
 * @param {Array<Float32Array>} vectors
 * @returns {Float32Array|null}
 */
export function computeAverageVector(vectors) {
    const valid = (vectors || []).filter((v) => v && v.length > 0);
    if (valid.length === 0) {
        return null;
    }

    const length = valid[0].length;
    const avg = new Float32Array(length);

    for (const vec of valid) {
        if (vec.length !== length) {
            return null;
        }
        for (let i = 0; i < length; i += 1) {
            avg[i] += vec[i];
        }
    }

    const count = valid.length;
    for (let i = 0; i < length; i += 1) {
        avg[i] /= count;
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
 * Cari siswa dengan kemiripan wajah terbaik (1:N matching) dari daftar template.
 *
 * @param {Float32Array|Array<number>} liveVector
 * @param {Array<{student_id: number, nama?: string, student_name?: string, card_label?: string, template: Array<number>}>} templates
 * @param {number} [threshold=0.75]
 * @returns {{matched: boolean, bestMatch: object|null, bestSimilarity: number}}
 */
export function findBestFaceMatch(liveVector, templates, threshold = 0.75) {
    if (!liveVector || !Array.isArray(templates) || templates.length === 0) {
        return { matched: false, bestMatch: null, bestSimilarity: 0 };
    }

    let bestItem = null;
    let bestSim = -1;

    for (const item of templates) {
        if (!item || !Array.isArray(item.template) || item.template.length !== liveVector.length) {
            continue;
        }

        const sim = computeCosineSimilarity(liveVector, item.template);
        if (sim > bestSim) {
            bestSim = sim;
            bestItem = item;
        }
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
 * Ekstrak vektor landmark ternormalisasi dari 1 data URL foto referensi.
 *
 * @param {FaceLandmarker} landmarker
 * @param {string} dataUrl
 * @returns {Promise<Float32Array|null>}
 */
export async function extractVectorFromDataUrl(landmarker, dataUrl) {
    try {
        const img = await loadImageFromDataUrl(dataUrl);
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth || img.width || 320;
        canvas.height = img.naturalHeight || img.height || 240;
        canvas.getContext('2d').drawImage(img, 0, 0);

        const result = landmarker.detect(canvas);
        const landmarks = result?.faceLandmarks?.[0];

        return flattenAndNormalizeLandmarks(landmarks);
    } catch {
        return null;
    }
}

/**
 * Ekstrak vektor landmark ternormalisasi dari frame live elemen <video>.
 *
 * @param {FaceLandmarker} landmarker
 * @param {HTMLVideoElement} video
 * @param {HTMLCanvasElement} [sharedCanvas]
 * @returns {Float32Array|null}
 */
export function extractVectorFromVideo(landmarker, video, sharedCanvas = null) {
    if (!video || video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
        return null;
    }

    try {
        const canvas = sharedCanvas || document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        const result = landmarker.detect(canvas);
        const landmarks = result?.faceLandmarks?.[0];

        return flattenAndNormalizeLandmarks(landmarks);
    } catch {
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
    threshold = FACE_DISTANCE_THRESHOLD,
    onProgress = () => {},
}) {
    if (!Array.isArray(facePhotos) || facePhotos.length === 0) {
        return { matched: false, matchScore: 0, distance: Infinity, reason: 'no_reference_photos' };
    }

    onProgress({ stage: 'loading_model', message: 'Memuat pendeteksi landmark wajah…' });
    const landmarker = await getVerifierFaceLandmarker();

    onProgress({ stage: 'extracting_reference', message: 'Mengekstrak landmark 3 foto referensi siswa…' });
    const refVectors = [];
    for (const photoDataUrl of facePhotos) {
        try {
            const vec = await extractVectorFromDataUrl(landmarker, photoDataUrl);
            if (vec) {
                refVectors.push(vec);
            }
        } catch {
            // Abaikan kesalahan pembacaan foto referensi individual
        }
    }

    const avgReferenceVector = computeAverageVector(refVectors);
    if (!avgReferenceVector) {
        return { matched: false, matchScore: 0, distance: Infinity, reason: 'reference_landmarks_empty' };
    }

    const offscreenCanvas = document.createElement('canvas');
    const startedAt = performance.now();
    let bestDistance = Number.POSITIVE_INFINITY;

    onProgress({ stage: 'matching_live', message: 'Mencocokkan wajah dengan kamera… Pastikan wajah terkena cahaya.' });

    while (performance.now() - startedAt < timeoutMs) {
        const liveVector = extractVectorFromVideo(landmarker, video, offscreenCanvas);

        if (liveVector) {
            const distance = computeEuclideanDistance(liveVector, avgReferenceVector);
            if (distance < bestDistance) {
                bestDistance = distance;
            }

            onProgress({
                stage: 'matching_live',
                distance: Number(distance.toFixed(4)),
                bestDistance: Number(bestDistance.toFixed(4)),
                message: 'Wajah terdeteksi — memverifikasi kecocokan landmark…',
            });

            if (distance < threshold) {
                const rawScore = Number((1 - distance).toFixed(4));
                const matchScore = Math.min(0.9999, Math.max(0.86, rawScore));

                return {
                    matched: true,
                    matchScore,
                    distance: Number(distance.toFixed(4)),
                    reason: 'matched',
                };
            }
        } else {
            onProgress({
                stage: 'waiting_face',
                bestDistance: Number.isFinite(bestDistance) ? Number(bestDistance.toFixed(4)) : null,
                message: 'Arahkan wajah ke tengah kamera — pastikan wajah terkena cahaya.',
            });
        }

        await new Promise((resolve) => setTimeout(resolve, FACE_VERIFY_INTERVAL_MS));
    }

    const failedScore = Number.isFinite(bestDistance)
        ? Math.max(0, Math.min(0.85, Number((1 - bestDistance).toFixed(4))))
        : 0;

    return {
        matched: false,
        matchScore: failedScore,
        distance: Number.isFinite(bestDistance) ? Number(bestDistance.toFixed(4)) : Infinity,
        reason: 'timeout_mismatch',
    };
}
