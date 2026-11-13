

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Overlay deteksi posisi wajah hanya dimuat di halaman pendaftaran wajah ([data-face-autocapture]).
if (document.querySelector('[data-face-autocapture] canvas[data-face-overlay]')) {
    import('./face-overlay.js')
        .then(({ initFaceOverlays }) => initFaceOverlays())
        .catch((error) => console.warn('[face-overlay] Modul overlay gagal dimuat.', error));
}

// Modul auto-capture + liveness (FaceLandmarker + blendshapes) untuk pendaftaran wajah.
if (document.querySelector('[data-face-autocapture]')) {
    import('./face-autocapture.js')
        .then((mod) => {
            window.FaceAutoCapture = mod;
            window.dispatchEvent(new CustomEvent('face-autocapture:ready'));
        })
        .catch((error) => {
            console.warn('[face-autocapture] Modul gagal dimuat; fallback ke mode manual.', error);
            window.dispatchEvent(new CustomEvent('face-autocapture:failed'));
        });
}

// Modul verifikasi wajah 1:1 (FaceLandmarker 478 landmarks + Euclidean distance) untuk Kiosk Gerbang.
if (document.querySelector('[data-kiosk-face-verify]')) {
    import('./face-utils.js')
        .then((mod) => {
            window.FaceVerification = mod;
            window.dispatchEvent(new CustomEvent('face-verification:ready'));
        })
        .catch((error) => {
            console.warn('[face-verify] Modul verifikasi wajah gagal dimuat.', error);
            window.dispatchEvent(new CustomEvent('face-verification:failed'));
        });
}
