

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Overlay deteksi posisi wajah hanya dimuat di halaman pendaftaran wajah ([data-face-autocapture]).
// Kiosk tidak memakai face matching maupun face overlay.
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
