

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Overlay deteksi posisi wajah hanya dimuat di halaman kamera (kiosk & pendaftaran).
// Dynamic import menjaga bundle halaman admin tetap ringan.
if (document.querySelector('canvas[data-face-overlay]')) {
    import('./face-overlay.js')
        .then(({ initFaceOverlays }) => initFaceOverlays())
        .catch((error) => console.warn('[face-overlay] Modul overlay gagal dimuat.', error));
}
