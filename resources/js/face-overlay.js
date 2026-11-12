import { FaceDetector, FilesetResolver } from '@mediapipe/tasks-vision';

const WASM_PATH = '/vendor/mediapipe/wasm';
const MODEL_PATH = '/vendor/mediapipe/models/blaze_face_short_range.tflite';
const DETECT_INTERVAL_MS = 200;
const MAX_DEVICE_PIXEL_RATIO = 2;
const CENTER_TOLERANCE = 0.18;
const MIN_FACE_WIDTH_RATIO = 0.12;

const GUIDE_COLOR = '#f59e0b';
const DETECTED_COLOR = '#22c55e';

let detectorPromise = null;

/**
 * Satu instance FaceDetector dipakai ulang oleh semua overlay di halaman.
 * GPU dicoba lebih dulu; bila gagal, fallback ke CPU. Bila keduanya gagal,
 * overlay memakai panduan framing statis.
 */
function getFaceDetector() {
    if (!detectorPromise) {
        detectorPromise = (async () => {
            const fileset = await FilesetResolver.forVisionTasks(WASM_PATH);
            const baseOptions = {
                modelAssetPath: MODEL_PATH,
                delegate: 'GPU',
            };
            const options = {
                baseOptions,
                runningMode: 'VIDEO',
                minDetectionConfidence: 0.5,
                minSuppressionThreshold: 0.3,
            };

            try {
                return await FaceDetector.createFromOptions(fileset, options);
            } catch (gpuError) {
                baseOptions.delegate = 'CPU';

                return FaceDetector.createFromOptions(fileset, options);
            }
        })().catch((error) => {
            detectorPromise = null;
            throw error;
        });
    }

    return detectorPromise;
}

class FaceOverlay {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.video = canvas.parentElement ? canvas.parentElement.querySelector('video') : null;
        this.detector = null;
        this.rafId = null;
        this.lastDetectAt = 0;
        this.lastFace = null;
        this.destroyed = false;

        this.loop = this.loop.bind(this);
        this.onVisibilityChange = this.onVisibilityChange.bind(this);
        this.onPageHide = this.onPageHide.bind(this);
    }

    start() {
        if (!this.video || !this.ctx) {
            return;
        }

        document.addEventListener('visibilitychange', this.onVisibilityChange);
        window.addEventListener('pagehide', this.onPageHide, { once: true });
        this.rafId = requestAnimationFrame(this.loop);

        getFaceDetector()
            .then((detector) => {
                if (!this.destroyed) {
                    this.detector = detector;
                }
            })
            .catch((error) => {
                // Model/WASM gagal dimuat: panduan statis tetap tampil.
                console.warn('[face-overlay] Detektor wajah tidak tersedia; memakai panduan statis.', error);
            });
    }

    loop(now) {
        if (this.destroyed) {
            return;
        }

        this.rafId = requestAnimationFrame(this.loop);

        if (document.hidden) {
            return;
        }

        const rect = this.canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) {
            return;
        }

        this.syncCanvas(rect);

        const videoReady = this.video
            && this.video.readyState >= 2
            && this.video.videoWidth > 0
            && this.video.videoHeight > 0;

        if (!videoReady || !this.detector) {
            this.lastFace = null;
            this.draw(null, rect);

            return;
        }

        if (now - this.lastDetectAt >= DETECT_INTERVAL_MS) {
            this.lastDetectAt = now;

            try {
                const result = this.detector.detectForVideo(this.video, now);
                this.lastFace = this.pickLargestFace(result?.detections || []);
            } catch (error) {
                // Deteksi gagal saat runtime: jangan re-init tiap frame.
                this.detector = null;
                this.lastFace = null;
                console.warn('[face-overlay] Deteksi gagal; memakai panduan statis.', error);
            }
        }

        this.draw(this.lastFace, rect);
    }

    pickLargestFace(detections) {
        let largest = null;

        for (const detection of detections) {
            const box = detection.boundingBox;
            if (!box || box.width <= 0 || box.height <= 0) {
                continue;
            }

            if (!largest || box.width * box.height > largest.width * largest.height) {
                largest = box;
            }
        }

        return largest;
    }

    syncCanvas(rect) {
        const pixelRatio = Math.min(window.devicePixelRatio || 1, MAX_DEVICE_PIXEL_RATIO);
        const width = Math.max(1, Math.round(rect.width * pixelRatio));
        const height = Math.max(1, Math.round(rect.height * pixelRatio));

        if (this.canvas.width !== width || this.canvas.height !== height) {
            this.canvas.width = width;
            this.canvas.height = height;
        }
    }

    draw(face, rect) {
        const ctx = this.ctx;
        const width = rect.width;
        const height = rect.height;
        const pixelRatio = this.canvas.width / width;

        ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        ctx.clearRect(0, 0, width, height);

        if (face) {
            const centered = this.isCentered(face);
            const color = centered ? DETECTED_COLOR : GUIDE_COLOR;
            const box = this.mapVideoBoxToCanvas(face, width, height);

            this.drawFaceBrackets(box, color);
            this.drawStatus(centered ? 'Wajah terdeteksi' : 'Posisikan wajah di tengah', color, width, height);

            return;
        }

        this.drawFramingGuide(width, height, GUIDE_COLOR);
        this.drawStatus('Posisikan wajah di tengah', GUIDE_COLOR, width, height);
    }

    /**
     * Video memakai object-fit: cover. Skala tampil = max(lebar/lebarVideo,
     * tinggi/tinggiVideo); sisa crop dibagi rata ke kiri/kanan atau atas/bawah.
     */
    mapVideoBoxToCanvas(box, width, height) {
        const videoWidth = this.video.videoWidth || 1;
        const videoHeight = this.video.videoHeight || 1;
        const scale = Math.max(width / videoWidth, height / videoHeight);
        const renderedWidth = videoWidth * scale;
        const renderedHeight = videoHeight * scale;
        const offsetX = (width - renderedWidth) / 2;
        const offsetY = (height - renderedHeight) / 2;
        const padding = Math.min(box.width, box.height) * scale * 0.08;

        return {
            x: offsetX + box.originX * scale - padding,
            y: offsetY + box.originY * scale - padding,
            width: box.width * scale + padding * 2,
            height: box.height * scale + padding * 2,
        };
    }

    isCentered(face) {
        const videoWidth = this.video.videoWidth || 1;
        const videoHeight = this.video.videoHeight || 1;
        const centerX = (face.originX + face.width / 2) / videoWidth;
        const centerY = (face.originY + face.height / 2) / videoHeight;
        const bigEnough = face.width / videoWidth >= MIN_FACE_WIDTH_RATIO;

        return bigEnough
            && Math.abs(centerX - 0.5) <= CENTER_TOLERANCE
            && Math.abs(centerY - 0.5) <= CENTER_TOLERANCE;
    }

    drawFaceBrackets(box, color) {
        const ctx = this.ctx;
        const x = box.x;
        const y = box.y;
        const width = box.width;
        const height = box.height;

        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.5;
        ctx.globalAlpha = 0.3;
        ctx.strokeRect(x, y, width, height);
        ctx.restore();

        this.strokeCorners(x, y, width, height, color, 1, 3);
    }

    drawFramingGuide(width, height, color) {
        const ctx = this.ctx;
        const radiusX = Math.min(width * 0.2, 150);
        const radiusY = Math.min(height * 0.34, 150);
        const centerX = width / 2;
        const centerY = height / 2;

        ctx.save();
        ctx.globalAlpha = 0.55;
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.ellipse(centerX, centerY, radiusX, radiusY, 0, 0, Math.PI * 2);
        ctx.stroke();
        ctx.restore();

        this.strokeCorners(
            centerX - radiusX,
            centerY - radiusY,
            radiusX * 2,
            radiusY * 2,
            color,
            0.75,
            2.5,
        );
    }

    strokeCorners(x, y, width, height, color, alpha, lineWidth) {
        const ctx = this.ctx;
        const corner = Math.max(14, Math.min(30, Math.min(width, height) * 0.22));

        ctx.save();
        ctx.strokeStyle = color;
        ctx.globalAlpha = alpha;
        ctx.lineWidth = lineWidth;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();

        ctx.moveTo(x, y + corner);
        ctx.lineTo(x, y);
        ctx.lineTo(x + corner, y);

        ctx.moveTo(x + width - corner, y);
        ctx.lineTo(x + width, y);
        ctx.lineTo(x + width, y + corner);

        ctx.moveTo(x + width, y + height - corner);
        ctx.lineTo(x + width, y + height);
        ctx.lineTo(x + width - corner, y + height);

        ctx.moveTo(x + corner, y + height);
        ctx.lineTo(x, y + height);
        ctx.lineTo(x, y + height - corner);

        ctx.stroke();
        ctx.restore();
    }

    drawStatus(text, color, width, height) {
        const ctx = this.ctx;
        const paddingX = 10;
        const pillHeight = 24;
        const bottomMargin = 12;

        ctx.save();
        ctx.font = '600 12px Figtree, system-ui, -apple-system, sans-serif';
        const pillWidth = ctx.measureText(text).width + paddingX * 2;
        const x = (width - pillWidth) / 2;
        const y = height - pillHeight - bottomMargin;

        ctx.fillStyle = 'rgba(15, 23, 42, 0.66)';
        ctx.beginPath();
        if (typeof ctx.roundRect === 'function') {
            ctx.roundRect(x, y, pillWidth, pillHeight, 12);
        } else {
            ctx.rect(x, y, pillWidth, pillHeight);
        }
        ctx.fill();

        ctx.fillStyle = color;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, width / 2, y + pillHeight / 2 + 0.5);
        ctx.restore();
    }

    onVisibilityChange() {
        if (document.hidden) {
            if (this.rafId) {
                cancelAnimationFrame(this.rafId);
                this.rafId = null;
            }

            return;
        }

        if (!this.rafId && !this.destroyed) {
            this.lastDetectAt = 0;
            this.rafId = requestAnimationFrame(this.loop);
        }
    }

    onPageHide() {
        this.destroy();
    }

    destroy() {
        if (this.destroyed) {
            return;
        }

        this.destroyed = true;
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
        window.removeEventListener('pagehide', this.onPageHide);

        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = null;
        }
    }
}

export function initFaceOverlays() {
    document.querySelectorAll('canvas[data-face-overlay]').forEach((canvas) => {
        if (canvas.dataset.faceOverlayStarted === '1') {
            return;
        }

        canvas.dataset.faceOverlayStarted = '1';
        new FaceOverlay(canvas).start();
    });
}
