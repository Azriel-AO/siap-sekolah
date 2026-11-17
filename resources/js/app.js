

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const THEME_STORAGE_KEY = 'epresknbi-theme';

function readInitialDarkPreference() {
    try {
        const saved = localStorage.getItem(THEME_STORAGE_KEY);
        if (saved === 'dark') {
            return true;
        }
        if (saved === 'light') {
            return false;
        }
        return Boolean(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    } catch {
        return false;
    }
}

function applyThemeClass(isDark) {
    document.documentElement.classList.toggle('dark', Boolean(isDark));
}

document.addEventListener('alpine:init', () => {
    const initialDark = readInitialDarkPreference();
    applyThemeClass(initialDark);

    Alpine.store('theme', {
        dark: initialDark,
        toggle() {
            this.dark = !this.dark;
            applyThemeClass(this.dark);
            try {
                localStorage.setItem(THEME_STORAGE_KEY, this.dark ? 'dark' : 'light');
            } catch {
                // Abaikan bila localStorage dibatasi browser
            }
            window.dispatchEvent(new CustomEvent('theme:changed', { detail: { dark: this.dark } }));
        },
        set(isDark) {
            this.dark = Boolean(isDark);
            applyThemeClass(this.dark);
            try {
                localStorage.setItem(THEME_STORAGE_KEY, this.dark ? 'dark' : 'light');
            } catch {
                // Abaikan bila localStorage dibatasi browser
            }
            window.dispatchEvent(new CustomEvent('theme:changed', { detail: { dark: this.dark } }));
        },
    });
});

window.toggleTheme = () => {
    if (window.Alpine && Alpine.store('theme')) {
        Alpine.store('theme').toggle();
        return Alpine.store('theme').dark;
    }
    const nextDark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem(THEME_STORAGE_KEY, nextDark ? 'dark' : 'light');
    } catch {
        // Abaikan bila localStorage dibatasi browser
    }
    window.dispatchEvent(new CustomEvent('theme:changed', { detail: { dark: nextDark } }));
    return nextDark;
};

Alpine.start();

// Overlay deteksi posisi wajah hanya dimuat di halaman pendaftaran wajah ([data-face-autocapture]).
if (document.querySelector('[data-face-autocapture] canvas[data-face-overlay]')) {
    import('./face-overlay.js')
        .then(({ initFaceOverlays }) => initFaceOverlays())
        .catch((error) => console.warn('[face-overlay] Modul overlay gagal dimuat.', error));
}

// Modul auto-capture + liveness (FaceLandmarker + blendshapes) untuk pendaftaran wajah.
if (document.querySelector('[data-face-autocapture], [data-face-enrollment]')) {
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

// Modul verifikasi wajah 1:1 & ekstraksi vektor 478 landmarks (dipakai di Kiosk & Pendaftaran Wajah).
if (document.querySelector('[data-kiosk-face-verify], [data-face-autocapture], [data-face-enrollment]')) {
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
