import { FaceLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';

const WASM_PATH = '/vendor/mediapipe/wasm';
const LANDMARKER_MODEL_PATH = '/vendor/mediapipe/models/face_landmarker.task';

const DETECT_INTERVAL_MS = 110;
const CENTER_TOLERANCE = 0.22;
const MIN_FACE_WIDTH_RATIO = 0.14;
const YAW_STRAIGHT_MAX = 0.15;
const YAW_TURN_MIN = 0.19;
const BLINK_SCORE_MIN = 0.38;
const HOLD_FRAMES_POSE = 3;

let landmarkerPromise = null;

/**
 * Inisialisasi singleton FaceLandmarker (mode VIDEO, numFaces=1, outputFaceBlendshapes=true).
 * Mencoba GPU terlebih dahulu, lalu fallback ke CPU. Bila keduanya gagal, melempar error
 * agar UI kembali ke mode capture 3 foto manual secara graceful.
 */
export function getFaceLandmarker() {
    if (!landmarkerPromise) {
        landmarkerPromise = (async () => {
            const fileset = await FilesetResolver.forVisionTasks(WASM_PATH);
            const baseOptions = {
                modelAssetPath: LANDMARKER_MODEL_PATH,
                delegate: 'GPU',
            };
            const options = {
                baseOptions,
                runningMode: 'VIDEO',
                numFaces: 1,
                outputFaceBlendshapes: true,
                minFaceDetectionConfidence: 0.5,
                minFacePresenceConfidence: 0.5,
                minTrackingConfidence: 0.5,
            };

            try {
                return await FaceLandmarker.createFromOptions(fileset, options);
            } catch (gpuError) {
                baseOptions.delegate = 'CPU';

                return FaceLandmarker.createFromOptions(fileset, options);
            }
        })().catch((error) => {
            landmarkerPromise = null;
            throw error;
        });
    }

    return landmarkerPromise;
}

/**
 * Evaluasi geometri & blendshapes dari hasil FaceLandmarker.
 *
 * Indeks landmark kanonik MediaPipe Face Mesh:
 * - 1   : ujung hidung (nose tip)
 * - 234 : pipi terluar kanan subjek (x lebih kecil saat menghadap kamera)
 * - 454 : pipi terluar kiri subjek (x lebih besar saat menghadap kamera)
 *
 * Yaw ratio:
 *   yawRatio = ((nose.x - leftCheek.x) / faceWidth) - 0.5
 *   - Hadap lurus  : |yawRatio| <= YAW_STRAIGHT_MAX
 *   - Tengok kanan / kiri : |yawRatio| >= YAW_TURN_MIN
 *
 * Eye blink (blendshapes):
 *   max(eyeBlinkLeft, eyeBlinkRight) >= BLINK_SCORE_MIN
 */
export function evaluateFacePose(result) {
    const landmarks = result?.faceLandmarks?.[0];
    if (!landmarks || landmarks.length < 455) {
        return { hasFace: false };
    }

    let minX = 1;
    let maxX = 0;
    let minY = 1;
    let maxY = 0;

    for (const pt of landmarks) {
        if (pt.x < minX) minX = pt.x;
        if (pt.x > maxX) maxX = pt.x;
        if (pt.y < minY) minY = pt.y;
        if (pt.y > maxY) maxY = pt.y;
    }

    const width = Math.max(0.001, maxX - minX);
    const height = Math.max(0.001, maxY - minY);
    const centerX = (minX + maxX) / 2;
    const centerY = (minY + maxY) / 2;

    const centered = width >= MIN_FACE_WIDTH_RATIO
        && Math.abs(centerX - 0.5) <= CENTER_TOLERANCE
        && Math.abs(centerY - 0.5) <= CENTER_TOLERANCE;

    const nose = landmarks[1];
    const leftCheek = landmarks[234];
    const rightCheek = landmarks[454];
    const cheekSpan = Math.max(0.001, Math.abs(rightCheek.x - leftCheek.x));
    const minCheekX = Math.min(leftCheek.x, rightCheek.x);
    const yawRatio = ((nose.x - minCheekX) / cheekSpan) - 0.5;

    const categories = result?.faceBlendshapes?.[0]?.categories || [];
    let blinkLeft = 0;
    let blinkRight = 0;

    for (const cat of categories) {
        if (cat.categoryName === 'eyeBlinkLeft') {
            blinkLeft = cat.score;
        } else if (cat.categoryName === 'eyeBlinkRight') {
            blinkRight = cat.score;
        }
    }

    const blinkScore = Math.max(blinkLeft, blinkRight);

    return {
        hasFace: true,
        centered,
        yawRatio,
        isStraight: centered && Math.abs(yawRatio) <= YAW_STRAIGHT_MAX,
        isTurnRight: width >= MIN_FACE_WIDTH_RATIO && yawRatio <= -YAW_TURN_MIN,
        isTurnLeft: width >= MIN_FACE_WIDTH_RATIO && yawRatio >= YAW_TURN_MIN,
        isTurnEither: width >= MIN_FACE_WIDTH_RATIO && Math.abs(yawRatio) >= YAW_TURN_MIN,
        blinkScore,
        isBlink: width >= MIN_FACE_WIDTH_RATIO && blinkScore >= BLINK_SCORE_MIN,
    };
}

/**
 * Controller auto-capture + liveness 4 langkah:
 *   0: Hadap lurus ke kamera      -> auto-capture Foto 1 (depan)
 *   1: Tengok ke kanan            -> auto-capture Foto 2 (kanan)
 *   2: Tengok ke kiri             -> auto-capture Foto 3 (kiri)
 *   3: Kedipkan mata (liveness)   -> verifikasi selesai
 *
 * Frame diambil langsung dari elemen <video> ke offscreen canvas,
 * sehingga kanvas overlay panduan tidak pernah ikut terekam ke foto JPEG.
 */
export class FaceAutoCaptureSession {
    constructor({ video, onStateChange, onPhotoCaptured, onComplete, onFallback }) {
        this.video = video;
        this.onStateChange = onStateChange || (() => {});
        this.onPhotoCaptured = onPhotoCaptured || (() => {});
        this.onComplete = onComplete || (() => {});
        this.onFallback = onFallback || (() => {});

        this.landmarker = null;
        this.rafId = null;
        this.lastDetectAt = 0;
        this.stepIndex = 0;
        this.holdCount = 0;
        this.firstTurnSign = 0;
        this.cooldownUntil = 0;
        this.stopped = false;

        this.loop = this.loop.bind(this);
    }

    async start() {
        this.stopped = false;
        this.stepIndex = 0;
        this.holdCount = 0;
        this.firstTurnSign = 0;

        this.emitState('Memuat pendeteksi wajah otomatis…');

        try {
            this.landmarker = await getFaceLandmarker();
            if (this.stopped) {
                return;
            }
            this.emitState('Posisikan wajah menghadap lurus ke kamera');
            this.rafId = requestAnimationFrame(this.loop);
        } catch (error) {
            console.warn('[face-autocapture] FaceLandmarker gagal dimuat; beralih ke mode manual.', error);
            if (!this.stopped) {
                this.onFallback(error);
            }
        }
    }

    resetToStep(step = 0) {
        this.stepIndex = step;
        this.holdCount = 0;
        if (step <= 1) {
            this.firstTurnSign = 0;
        }
        this.cooldownUntil = performance.now() + 450;
        this.emitState(this.defaultHintForStep(this.stepIndex));

        if (!this.rafId && !this.stopped && this.landmarker) {
            this.rafId = requestAnimationFrame(this.loop);
        }
    }

    stop() {
        this.stopped = true;
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = null;
        }
    }

    defaultHintForStep(step) {
        switch (step) {
            case 0:
                return 'Langkah 1/4: Hadap lurus ke tengah kamera';
            case 1:
                return 'Langkah 2/4: Tengok perlahan ke kanan';
            case 2:
                return 'Langkah 3/4: Tengok perlahan ke kiri';
            case 3:
                return 'Langkah 4/4: Kedipkan kedua mata Anda';
            default:
                return 'Selesai! 3 foto wajah & liveness terverifikasi.';
        }
    }

    emitState(hint, faceDetected = false) {
        this.onStateChange({
            stepIndex: this.stepIndex,
            hint,
            faceDetected,
            completed: this.stepIndex >= 4,
        });
    }

    captureVideoFrameJpeg() {
        if (!this.video || !this.video.videoWidth || !this.video.videoHeight) {
            return null;
        }

        const offscreen = document.createElement('canvas');
        offscreen.width = this.video.videoWidth;
        offscreen.height = this.video.videoHeight;
        offscreen.getContext('2d').drawImage(this.video, 0, 0);

        return offscreen.toDataURL('image/jpeg', 0.7);
    }

    loop(now) {
        if (this.stopped) {
            return;
        }

        this.rafId = requestAnimationFrame(this.loop);

        if (document.hidden || !this.landmarker || !this.video || this.video.readyState < 2 || !this.video.videoWidth) {
            return;
        }

        if (now < this.cooldownUntil || now - this.lastDetectAt < DETECT_INTERVAL_MS) {
            return;
        }

        this.lastDetectAt = now;

        let result;
        try {
            result = this.landmarker.detectForVideo(this.video, now);
        } catch (error) {
            console.warn('[face-autocapture] Deteksi runtime gagal; beralih ke mode manual.', error);
            this.stop();
            this.onFallback(error);

            return;
        }

        const pose = evaluateFacePose(result);
        if (!pose.hasFace) {
            this.holdCount = 0;
            this.emitState('Wajah belum terlihat — posisikan wajah di depan kamera', false);

            return;
        }

        if (this.stepIndex === 0) {
            if (pose.isStraight) {
                this.holdCount += 1;
                this.emitState('Tahan posisi hadap lurus…', true);
                if (this.holdCount >= HOLD_FRAMES_POSE) {
                    const jpeg = this.captureVideoFrameJpeg();
                    if (jpeg) {
                        this.onPhotoCaptured(0, jpeg);
                        this.stepIndex = 1;
                        this.holdCount = 0;
                        this.cooldownUntil = now + 550;
                        this.emitState(this.defaultHintForStep(1), true);
                    }
                }
            } else {
                this.holdCount = 0;
                this.emitState('Langkah 1/4: Posisikan wajah lurus di tengah kamera', true);
            }

            return;
        }

        if (this.stepIndex === 1) {
            // Terima tengok kanan (atau arah pertama yang dipilih siswa bila kamera mirror)
            const turnedRight = pose.isTurnRight || (pose.isTurnEither && pose.yawRatio <= -YAW_TURN_MIN);
            const turnedAny = pose.isTurnEither;

            if (turnedRight || turnedAny) {
                const currentSign = pose.yawRatio < 0 ? -1 : 1;
                this.holdCount += 1;
                this.emitState('Tahan posisi tengok kanan…', true);
                if (this.holdCount >= HOLD_FRAMES_POSE) {
                    const jpeg = this.captureVideoFrameJpeg();
                    if (jpeg) {
                        this.firstTurnSign = currentSign;
                        this.onPhotoCaptured(1, jpeg);
                        this.stepIndex = 2;
                        this.holdCount = 0;
                        this.cooldownUntil = now + 550;
                        this.emitState(this.defaultHintForStep(2), true);
                    }
                }
            } else {
                this.holdCount = 0;
                this.emitState('Langkah 2/4: Tengok perlahan ke kanan', true);
            }

            return;
        }

        if (this.stepIndex === 2) {
            // Wajib tengok ke arah berlawanan dari langkah ke-2 (tengok kiri)
            const oppositeTurn = this.firstTurnSign !== 0
                ? (this.firstTurnSign < 0 ? pose.yawRatio >= YAW_TURN_MIN : pose.yawRatio <= -YAW_TURN_MIN)
                : pose.isTurnLeft;

            if (oppositeTurn) {
                this.holdCount += 1;
                this.emitState('Tahan posisi tengok kiri…', true);
                if (this.holdCount >= HOLD_FRAMES_POSE) {
                    const jpeg = this.captureVideoFrameJpeg();
                    if (jpeg) {
                        this.onPhotoCaptured(2, jpeg);
                        this.stepIndex = 3;
                        this.holdCount = 0;
                        this.cooldownUntil = now + 450;
                        this.emitState(this.defaultHintForStep(3), true);
                    }
                }
            } else {
                this.holdCount = 0;
                this.emitState('Langkah 3/4: Tengok perlahan ke arah sebaliknya (kiri)', true);
            }

            return;
        }

        if (this.stepIndex === 3) {
            if (pose.isBlink) {
                this.stepIndex = 4;
                this.holdCount = 0;
                this.emitState('Selesai! Kedipan terdeteksi & 3 foto tersimpan.', true);
                this.stop();
                this.onComplete();
            } else {
                this.emitState('Langkah 4/4: Kedipkan kedua mata Anda untuk verifikasi', true);
            }
        }
    }
}
