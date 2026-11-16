import { FaceLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';
import { checkFaceObscured } from './face-utils.js';

export { checkFaceObscured };

const WASM_PATH = '/vendor/mediapipe/wasm';
const LANDMARKER_MODEL_PATH = '/vendor/mediapipe/models/face_landmarker.task';

export const DETECT_INTERVAL_MS = 110;
export const CENTER_TOLERANCE = 0.25;
export const CENTER_TOLERANCE_Y = 0.28;
export const MIN_FACE_WIDTH_RATIO = 0.12;
export const MIN_FACE_TURN_WIDTH_RATIO = 0.10;
export const YAW_STRAIGHT_MAX = 0.18;
export const YAW_TURN_MIN = 0.14;
export const BLINK_SCORE_MIN = 0.30;
export const EAR_BLINK_MAX = 0.20;
export const HOLD_FRAMES_POSE = 3;
export const DWELL_TIME_MS = 1500;
export const STEP_TIMEOUT_MS = 10000;
export const DEBUG_LOG_INTERVAL_MS = 500;

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
                minFaceDetectionConfidence: 0.45,
                minFacePresenceConfidence: 0.45,
                minTrackingConfidence: 0.45,
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
 * Hitung Eye Aspect Ratio (EAR) sederhana dari landmark kelopak mata MediaPipe
 * sebagai cadangan deteksi kedipan bila skor blendshape webcam rendah.
 */
function computeEyeAspectRatio(landmarks) {
    const dist = (a, b) => Math.hypot((a?.x ?? 0) - (b?.x ?? 0), (a?.y ?? 0) - (b?.y ?? 0));

    // Mata kiri: vertikal (159, 145), horizontal (33, 133)
    const leftV = dist(landmarks[159], landmarks[145]);
    const leftH = Math.max(0.001, dist(landmarks[33], landmarks[133]));
    const leftEar = leftV / leftH;

    // Mata kanan: vertikal (386, 374), horizontal (362, 263)
    const rightV = dist(landmarks[386], landmarks[374]);
    const rightH = Math.max(0.001, dist(landmarks[362], landmarks[263]));
    const rightEar = rightV / rightH;

    return Math.min(leftEar, rightEar);
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
 *   yawRatio = ((nose.x - minCheekX) / cheekSpan) - 0.5
 *   - Hadap lurus         : |yawRatio| <= YAW_STRAIGHT_MAX (0.18)
 *   - Tengok kanan / kiri : |yawRatio| >= YAW_TURN_MIN (0.14)
 *
 * Eye blink (blendshapes + EAR fallback):
 *   max(eyeBlinkLeft, eyeBlinkRight) >= BLINK_SCORE_MIN (0.30) atau EAR <= EAR_BLINK_MAX
 */
export function evaluateFacePose(result) {
    const landmarks = result?.multiFaceLandmarks?.[0] || result?.faceLandmarks?.[0];
    if (!landmarks || landmarks.length < 455) {
        return {
            hasFace: false,
            faceObscured: false,
            centered: false,
            inGuideBox: false,
            faceSize: 0,
            yawRatio: 0,
            blinkScore: 0,
        };
    }

    const faceObscured = checkFaceObscured(landmarks);

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

    const sizeOk = width >= MIN_FACE_WIDTH_RATIO && width <= 0.85 && height <= 0.92;
    const turnSizeOk = width >= MIN_FACE_TURN_WIDTH_RATIO && width <= 0.85;
    const centered = sizeOk
        && Math.abs(centerX - 0.5) <= CENTER_TOLERANCE
        && Math.abs(centerY - 0.5) <= CENTER_TOLERANCE_Y;

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
    const minEar = computeEyeAspectRatio(landmarks);
    const isBlink = turnSizeOk && (blinkScore >= BLINK_SCORE_MIN || minEar <= EAR_BLINK_MAX);

    return {
        hasFace: true,
        faceObscured,
        centered,
        inGuideBox: centered,
        sizeOk,
        turnSizeOk,
        faceSize: Number(width.toFixed(3)),
        yawRatio: Number(yawRatio.toFixed(3)),
        isStraight: !faceObscured && centered && Math.abs(yawRatio) <= YAW_STRAIGHT_MAX,
        isTurnRight: !faceObscured && turnSizeOk && yawRatio <= -YAW_TURN_MIN,
        isTurnLeft: !faceObscured && turnSizeOk && yawRatio >= YAW_TURN_MIN,
        isTurnEither: !faceObscured && turnSizeOk && Math.abs(yawRatio) >= YAW_TURN_MIN,
        blinkScore: Number(blinkScore.toFixed(3)),
        minEar: Number(minEar.toFixed(3)),
        isBlink: !faceObscured && isBlink,
    };
}

/**
 * Periksa apakah pusat wajah (rata-rata landmark) berada di dalam DOMRect
 * `.face-guide-overlay` pada viewport (memperhitungkan CSS object-fit: cover).
 * Mengembalikan null bila elemen overlay atau ukuran video belum tersedia.
 */
function isFaceCenterInsideGuideOverlay(landmarks, video, guideElement) {
    if (!Array.isArray(landmarks) || landmarks.length === 0 || !video || !guideElement) {
        return null;
    }

    const overlayRect = guideElement.getBoundingClientRect();
    const videoRect = video.getBoundingClientRect();
    if (!overlayRect || overlayRect.width <= 0 || overlayRect.height <= 0 || !videoRect || videoRect.width <= 0 || videoRect.height <= 0) {
        return null;
    }

    let sumX = 0;
    let sumY = 0;
    const count = landmarks.length;
    for (let i = 0; i < count; i += 1) {
        sumX += Number(landmarks[i]?.x ?? 0);
        sumY += Number(landmarks[i]?.y ?? 0);
    }

    const normX = sumX / count;
    const normY = sumY / count;
    const videoW = video.videoWidth || videoRect.width;
    const videoH = video.videoHeight || videoRect.height;
    const scale = Math.max(videoRect.width / videoW, videoRect.height / videoH);
    const renderedW = videoW * scale;
    const renderedH = videoH * scale;
    const offsetX = (videoRect.width - renderedW) / 2;
    const offsetY = (videoRect.height - renderedH) / 2;

    const centerX = videoRect.left + offsetX + normX * renderedW;
    const centerY = videoRect.top + offsetY + normY * renderedH;

    return centerX >= overlayRect.left
        && centerX <= overlayRect.right
        && centerY >= overlayRect.top
        && centerY <= overlayRect.bottom;
}

/**
 * Controller auto-capture + liveness 4 langkah:
 *   0: Hadap lurus ke kamera      -> auto-capture Foto 1 (depan)
 *   1: Tengok ke kanan            -> auto-capture Foto 2 (kanan)
 *   2: Tengok ke kiri             -> auto-capture Foto 3 (kiri)
 *   3: Kedipkan mata (liveness)   -> verifikasi selesai
 *
 * Frame diambil langsung dari elemen <video> ke offscreen canvas,
 * sehingga overlay panduan tidak pernah ikut terekam ke foto JPEG.
 */
export class FaceAutoCaptureSession {
    constructor({ video, onStateChange, onPhotoCaptured, onComplete, onFallback, debug = true }) {
        this.video = video;
        this.onStateChange = onStateChange || (() => {});
        this.onPhotoCaptured = onPhotoCaptured || (() => {});
        this.onComplete = onComplete || (() => {});
        this.onFallback = onFallback || (() => {});
        this.debug = debug && (typeof window === 'undefined' || window.FACE_AUTOCAPTURE_DEBUG !== false);

        this.landmarker = null;
        this.rafId = null;
        this.lastDetectAt = 0;
        this.lastTimestampMs = 0;
        this.lastDebugLogAt = 0;
        this.stepIndex = 0;
        this.insideGuideSince = null;
        this.poseStableSince = null;
        this.firstTurnSign = 0;
        this.cooldownUntil = 0;
        this.stepStartedAt = 0;
        this.timedOut = false;
        this.stopped = false;

        this.loop = this.loop.bind(this);
    }

    async start() {
        this.stopped = false;
        this.timedOut = false;
        this.stepIndex = 0;
        this.insideGuideSince = null;
        this.poseStableSince = null;
        this.firstTurnSign = 0;
        this.stepStartedAt = performance.now();

        this.emitState({
            hint: 'Memuat pendeteksi wajah otomatis…',
            faceDetected: false,
            inGuideBox: false,
            stabilityText: '',
            stabilityReady: false,
            timedOut: false,
        });

        try {
            this.landmarker = await getFaceLandmarker();
            if (this.stopped) {
                return;
            }
            this.stepStartedAt = performance.now();
            this.emitState({
                hint: this.defaultHintForStep(0),
                faceDetected: false,
                inGuideBox: false,
                stabilityText: '',
                stabilityReady: false,
                timedOut: false,
            });
            this.rafId = requestAnimationFrame(this.loop);
        } catch (error) {
            if (!this.stopped) {
                this.onFallback(error);
            }
        }
    }

    retryCurrentStep() {
        this.resetToStep(this.stepIndex);
    }

    resetToStep(step = 0) {
        this.stepIndex = step;
        this.insideGuideSince = null;
        this.poseStableSince = null;
        this.timedOut = false;
        if (step <= 1) {
            this.firstTurnSign = 0;
        }
        const now = performance.now();
        this.stepStartedAt = now;
        this.cooldownUntil = now + 400;
        this.emitState({
            hint: this.defaultHintForStep(this.stepIndex),
            faceDetected: false,
            inGuideBox: false,
            stabilityText: '',
            stabilityReady: false,
            timedOut: false,
        });

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

    formatStabilityText(dwellMs, ready = false) {
        if (ready || dwellMs >= DWELL_TIME_MS) {
            return 'Siap - Capture!';
        }
        if (dwellMs >= 0) {
            return 'Menstabilkan...';
        }

        return '';
    }

    emitState({
        hint,
        faceDetected = false,
        inGuideBox = false,
        faceObscured = false,
        dwellElapsedMs = 0,
        stabilityText = '',
        stabilityReady = false,
        timedOut = this.timedOut,
    }) {
        this.onStateChange({
            stepIndex: this.stepIndex,
            hint,
            faceDetected,
            inGuideBox,
            faceObscured,
            dwellElapsedMs,
            dwellTotalMs: DWELL_TIME_MS,
            stabilityText,
            stabilityReady,
            timedOut,
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

    advanceStep(nextStep, now, capturedSlotIndex = null, jpeg = null) {
        if (capturedSlotIndex !== null && jpeg) {
            this.onPhotoCaptured(capturedSlotIndex, jpeg);
        }
        this.emitState({
            hint: 'Siap - Capture!',
            faceDetected: true,
            inGuideBox: true,
            faceObscured: false,
            dwellElapsedMs: DWELL_TIME_MS,
            stabilityText: 'Siap - Capture!',
            stabilityReady: true,
            timedOut: false,
        });

        this.stepIndex = nextStep;
        this.insideGuideSince = null;
        this.poseStableSince = null;
        this.timedOut = false;
        this.stepStartedAt = now + 500;
        this.cooldownUntil = now + 500;

        setTimeout(() => {
            if (!this.stopped && this.stepIndex === nextStep && !this.timedOut) {
                this.emitState({
                    hint: this.defaultHintForStep(nextStep),
                    faceDetected: true,
                    inGuideBox: true,
                    dwellElapsedMs: 0,
                    stabilityText: '',
                    stabilityReady: false,
                    timedOut: false,
                });
            }
        }, 450);
    }

    logDebugIfDue(now, pose) {
        if (!this.debug || now - this.lastDebugLogAt < DEBUG_LOG_INTERVAL_MS) {
            return;
        }
        this.lastDebugLogAt = now;
    }

    loop(now) {
        if (this.stopped) {
            return;
        }

        this.rafId = requestAnimationFrame(this.loop);

        if (this.timedOut) {
            return;
        }

        if (document.hidden || !this.landmarker || !this.video || this.video.readyState < 2 || !this.video.videoWidth) {
            return;
        }

        if (this.poseStableSince === null && this.stepStartedAt > 0 && now - this.stepStartedAt >= STEP_TIMEOUT_MS) {
            this.timedOut = true;
            this.insideGuideSince = null;
            this.poseStableSince = null;
            this.emitState({
                hint: 'Wajah tidak terdeteksi dengan baik. Silakan coba lagi atau gunakan tombol ambil foto manual.',
                faceDetected: false,
                inGuideBox: false,
                dwellElapsedMs: 0,
                stabilityText: '',
                stabilityReady: false,
                timedOut: true,
            });

            return;
        }

        if (now < this.cooldownUntil || now - this.lastDetectAt < DETECT_INTERVAL_MS) {
            return;
        }

        this.lastDetectAt = now;
        this.lastTimestampMs = Math.max(this.lastTimestampMs + 1, Math.floor(now));

        let result;
        try {
            result = this.landmarker.detectForVideo(this.video, this.lastTimestampMs);
        } catch (error) {
            this.stop();
            this.onFallback(error);

            return;
        }

        const pose = evaluateFacePose(result);
        this.logDebugIfDue(now, pose);

        if (!pose.hasFace) {
            this.insideGuideSince = null;
            this.poseStableSince = null;
            this.emitState({
                hint: 'Wajah belum terlihat, posisikan wajah di dalam kotak panduan',
                faceDetected: false,
                inGuideBox: false,
                faceObscured: false,
                dwellElapsedMs: 0,
                stabilityText: '',
                stabilityReady: false,
            });

            return;
        }

        const rawLandmarks = result?.multiFaceLandmarks?.[0] || result?.faceLandmarks?.[0];
        const guideElement = this.video?.parentElement?.querySelector('.face-guide-overlay') || null;
        const overlayInside = isFaceCenterInsideGuideOverlay(rawLandmarks, this.video, guideElement);
        const inGuideBox = overlayInside !== null ? overlayInside : pose.inGuideBox;

        if (pose.faceObscured) {
            this.poseStableSince = null;
            this.emitState({
                hint: 'Wajah tertutup. Lepas aksesoris penutup wajah.',
                faceDetected: true,
                inGuideBox,
                faceObscured: true,
                dwellElapsedMs: 0,
                stabilityText: 'Wajah tertutup. Lepas aksesoris penutup wajah.',
                stabilityReady: false,
            });

            return;
        }

        if (!inGuideBox) {
            this.insideGuideSince = null;
            this.poseStableSince = null;
            this.emitState({
                hint: 'Geser wajah ke tengah kotak panduan',
                faceDetected: true,
                inGuideBox: false,
                faceObscured: false,
                dwellElapsedMs: 0,
                stabilityText: '',
                stabilityReady: false,
            });

            return;
        }

        if (this.insideGuideSince === null) {
            this.insideGuideSince = now;
        }

        if (this.stepIndex === 0) {
            const straightPose = pose.sizeOk && Math.abs(pose.yawRatio) <= YAW_STRAIGHT_MAX;
            if (straightPose) {
                if (this.poseStableSince === null) {
                    this.poseStableSince = now;
                }
                const dwellElapsedMs = now - this.poseStableSince;
                const ready = dwellElapsedMs >= DWELL_TIME_MS;
                const progressText = this.formatStabilityText(dwellElapsedMs, ready);
                this.emitState({
                    hint: ready ? 'Siap - Capture!' : 'Tahan posisi hadap lurus... (Menstabilkan...)',
                    faceDetected: true,
                    inGuideBox: true,
                    faceObscured: false,
                    dwellElapsedMs,
                    stabilityText: progressText,
                    stabilityReady: ready,
                });
                if (ready) {
                    const jpeg = this.captureVideoFrameJpeg();
                    if (jpeg) {
                        this.advanceStep(1, now, 0, jpeg);
                    }
                }
            } else {
                this.poseStableSince = null;
                this.emitState({
                    hint: 'Langkah 1/4: Hadap lurus ke arah kamera',
                    faceDetected: true,
                    inGuideBox: true,
                    faceObscured: false,
                    dwellElapsedMs: 0,
                    stabilityText: 'Menstabilkan...',
                    stabilityReady: false,
                });
            }

            return;
        }

        if (this.stepIndex === 1) {
            const turnedRight = pose.isTurnRight || (pose.isTurnEither && pose.yawRatio <= -YAW_TURN_MIN);
            const turnedAny = pose.isTurnEither;

            if (turnedRight || turnedAny) {
                const currentSign = pose.yawRatio < 0 ? -1 : 1;
                if (this.poseStableSince === null) {
                    this.poseStableSince = now;
                }
                const dwellElapsedMs = now - this.poseStableSince;
                const ready = dwellElapsedMs >= DWELL_TIME_MS;
                const progressText = this.formatStabilityText(dwellElapsedMs, ready);
                this.emitState({
                    hint: ready ? 'Siap - Capture!' : 'Tahan posisi tengok kanan... (Menstabilkan...)',
                    faceDetected: true,
                    inGuideBox: true,
                    faceObscured: false,
                    dwellElapsedMs,
                    stabilityText: progressText,
                    stabilityReady: ready,
                });
                if (ready) {
                    const jpeg = this.captureVideoFrameJpeg();
                    if (jpeg) {
                        this.firstTurnSign = currentSign;
                        this.advanceStep(2, now, 1, jpeg);
                    }
                }
            } else {
                this.poseStableSince = null;
                this.emitState({
                    hint: 'Langkah 2/4: Tengok perlahan ke kanan',
                    faceDetected: true,
                    inGuideBox: true,
                    faceObscured: false,
                    dwellElapsedMs: 0,
                    stabilityText: 'Menstabilkan...',
                    stabilityReady: false,
                });
            }

            return;
        }

        if (this.stepIndex === 2) {
            const oppositeTurn = this.firstTurnSign !== 0
                ? (this.firstTurnSign < 0 ? pose.yawRatio >= YAW_TURN_MIN : pose.yawRatio <= -YAW_TURN_MIN)
                : pose.isTurnLeft;

            if (oppositeTurn) {
                if (this.poseStableSince === null) {
                    this.poseStableSince = now;
                }
                const dwellElapsedMs = now - this.poseStableSince;
                const ready = dwellElapsedMs >= DWELL_TIME_MS;
                const progressText = this.formatStabilityText(dwellElapsedMs, ready);
                this.emitState({
                    hint: ready ? 'Siap - Capture!' : 'Tahan posisi tengok kiri... (Menstabilkan...)',
                    faceDetected: true,
                    inGuideBox: true,
                    faceObscured: false,
                    dwellElapsedMs,
                    stabilityText: progressText,
                    stabilityReady: ready,
                });
                if (ready) {
                    const jpeg = this.captureVideoFrameJpeg();
                    if (jpeg) {
                        this.advanceStep(3, now, 2, jpeg);
                    }
                }
            } else {
                this.poseStableSince = null;
                this.emitState({
                    hint: 'Langkah 3/4: Tengok perlahan ke arah sebaliknya (kiri)',
                    faceDetected: true,
                    inGuideBox: true,
                    faceObscured: false,
                    dwellElapsedMs: 0,
                    stabilityText: 'Menstabilkan...',
                    stabilityReady: false,
                });
            }

            return;
        }

        if (this.stepIndex === 3) {
            if (pose.isBlink) {
                this.stepIndex = 4;
                this.insideGuideSince = null;
                this.poseStableSince = null;
                this.timedOut = false;
                this.emitState({
                    hint: 'Selesai! Kedipan terdeteksi & 3 foto tersimpan.',
                    faceDetected: true,
                    inGuideBox: true,
                    faceObscured: false,
                    dwellElapsedMs: DWELL_TIME_MS,
                    stabilityText: 'Siap - Capture!',
                    stabilityReady: true,
                    timedOut: false,
                });
                this.stop();
                this.onComplete();
            } else {
                this.emitState({
                    hint: 'Langkah 4/4: Kedipkan kedua mata Anda untuk verifikasi',
                    faceDetected: true,
                    inGuideBox: true,
                    faceObscured: false,
                    dwellElapsedMs: 0,
                    stabilityText: '',
                    stabilityReady: false,
                });
            }
        }
    }
}
