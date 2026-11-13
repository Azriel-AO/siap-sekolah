<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Pendaftaran Wajah
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    Ambil 3 foto wajah Anda sebagai data referensi absensi — bukan pengenalan wajah.
                </p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                Siswa: {{ $student?->name }}
            </span>
        </div>
    </x-slot>

    <div class="py-8" x-data="faceEnrollmentApp()" x-init="init()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Pesan hasil simpan --}}
            <div x-show="message" x-cloak x-transition
                 class="rounded-md p-4 border shadow-sm flex items-start justify-between"
                 :class="messageOk ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'">
                <p class="text-sm font-medium" x-text="message"></p>
                <button @click="message = ''" class="text-xs font-bold px-2 py-1 rounded hover:bg-black/5">Tutup</button>
            </div>

            <template x-if="mode === 'status' && sudahTerdaftar">
                <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-6 sm:p-8 space-y-6">
                    <div class="text-center space-y-3">
                        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 flex items-center justify-center">
                            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-extrabold text-emerald-700">Wajah Anda Sudah Terdaftar</h2>
                        <div class="text-sm text-gray-600 space-y-1">
                            <p>Terdaftar sejak: <strong x-text="tanggalDaftar"></strong></p>
                            <p x-show="tanggalDaftarUlang">Daftar ulang terakhir: <strong x-text="tanggalDaftarUlang"></strong></p>
                        </div>
                    </div>

                    {{-- Pratinjau 3 foto milik siswa sendiri (embedded base64, hanya halaman ber-login ini) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <template x-for="(foto, index) in photosEmbedded" :key="index">
                            <div class="rounded-xl border border-gray-200 overflow-hidden">
                                <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center overflow-hidden">
                                    <img x-show="foto" :src="foto" class="w-full h-full object-cover" :alt="'Foto wajah terdaftar ke-' + (index + 1)">
                                    <span x-show="!foto" class="text-xs text-gray-400 font-semibold">Foto tidak tersedia</span>
                                </div>
                                <div class="p-2 bg-white border-t border-gray-100 text-center text-xs font-bold text-gray-600" x-text="'Foto ' + (index + 1)"></div>
                            </div>
                        </template>
                    </div>

                    <div class="text-center">
                        <button type="button" @click="showConfirmDaftarUlang = true"
                                class="inline-flex items-center px-5 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold text-sm shadow-sm transition">
                            <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.6 9a8 8 0 0113.9-2M18.4 15a8 8 0 01-13.9 2" />
                            </svg>
                            Daftar Ulang
                        </button>
                        <p class="text-xs text-gray-400 mt-2">Foto lama akan dihapus dan diganti dengan 3 foto baru.</p>
                    </div>
                </div>
            </template>

            {{-- Dialog konfirmasi Daftar Ulang --}}
            <div x-show="showConfirmDaftarUlang" x-cloak
                 class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-6"
                 @keydown.escape.window="showConfirmDaftarUlang = false">
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4" @click.outside="showConfirmDaftarUlang = false">
                    <h3 class="text-lg font-extrabold text-gray-900">Daftar Ulang Wajah?</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Seluruh foto wajah lama Anda akan <strong>dihapus permanen</strong> dan diganti dengan
                        3 foto baru. Tindakan ini tidak bisa dibatalkan setelah disimpan.
                    </p>
                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="showConfirmDaftarUlang = false"
                                class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-sm transition">
                            Batal
                        </button>
                        <button type="button" @click="yaDaftarUlang()"
                                class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold text-sm transition">
                            Ya, Daftar Ulang
                        </button>
                    </div>
                </div>
            </div>

            <template x-if="mode === 'form'">
                <div class="space-y-6" data-face-autocapture>

                    {{-- Kartu pernyataan izin orang tua/wali (FR-012) --}}
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <h2 class="text-lg font-bold text-gray-900">Pernyataan Izin Orang Tua / Wali</h2>
                        <p class="text-sm text-gray-600 mt-2 leading-relaxed">
                            Saya menyatakan bahwa saya telah mendapat <strong>izin orang tua atau wali</strong>
                            untuk mendaftarkan foto wajah saya. Foto ini hanya dipakai sebagai
                            <strong>data referensi absensi</strong>, disimpan di penyimpanan privat sekolah,
                            dan <strong>tidak digunakan untuk pengenalan/pencocokan wajah</strong> otomatis.
                        </p>
                        <label class="mt-4 flex items-start space-x-3 p-4 rounded-lg border cursor-pointer transition"
                               :class="consent ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-50 hover:border-indigo-400'">
                            <input type="checkbox" x-model="consent" @change="onConsentChange()"
                                   class="mt-1 h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-800">
                                Saya sudah mendapat izin orang tua/wali dan menyetujui pernyataan di atas.
                            </span>
                        </label>
                    </div>

                    {{-- Area kamera (gagal-aman) --}}
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-lg font-bold text-gray-900">Kamera Wajah</h2>
                            <span x-show="consent && !kameraGagal" x-cloak
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                                  :class="manualMode ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'"
                                  x-text="manualMode ? 'Mode Manual (Fallback)' : 'Auto-Capture + Liveness'"></span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Kamera menyala setelah Anda mencentang pernyataan izin di atas. Ikuti panduan gerakan wajah agar 3 foto diambil otomatis.</p>

                        {{-- Indikator 4 langkah otomatis --}}
                        <div x-show="consent && !kameraGagal && !manualMode" x-cloak class="mt-3 rounded-xl border border-indigo-100 bg-indigo-50/70 p-3">
                            <div class="grid grid-cols-4 gap-2 text-xs font-bold text-center">
                                <div class="px-2 py-1.5 rounded-lg border transition"
                                     :class="autoStep > 0 ? 'bg-emerald-600 text-white border-emerald-600' : (autoStep === 0 ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300' : 'bg-white text-gray-500 border-gray-200')">
                                    1. Hadap Lurus
                                </div>
                                <div class="px-2 py-1.5 rounded-lg border transition"
                                     :class="autoStep > 1 ? 'bg-emerald-600 text-white border-emerald-600' : (autoStep === 1 ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300' : 'bg-white text-gray-500 border-gray-200')">
                                    2. Tengok Kanan
                                </div>
                                <div class="px-2 py-1.5 rounded-lg border transition"
                                     :class="autoStep > 2 ? 'bg-emerald-600 text-white border-emerald-600' : (autoStep === 2 ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300' : 'bg-white text-gray-500 border-gray-200')">
                                    3. Tengok Kiri
                                </div>
                                <div class="px-2 py-1.5 rounded-lg border transition"
                                     :class="autoStep > 3 ? 'bg-emerald-600 text-white border-emerald-600' : (autoStep === 3 ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300' : 'bg-white text-gray-500 border-gray-200')">
                                    4. Kedip Mata
                                </div>
                            </div>
                            <p class="mt-2 text-center text-xs font-bold text-indigo-900" x-text="autoHint"></p>
                        </div>

                        {{-- Pesan gagal-aman: tanpa kamera / izin ditolak / browser tidak mendukung --}}
                        <div x-show="consent && kameraGagal" x-cloak class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                            <p class="text-sm font-semibold text-amber-800">Kamera tidak dapat dipakai.</p>
                            <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                                Yang terjadi: perangkat tidak punya kamera, sedang dipakai aplikasi lain,
                                izin kamera ditolak, atau browser tidak mendukung.<br>
                                Yang harus Anda lakukan: izinkan kamera lewat ikon gembok/kamera di bilah alamat browser
                                (pilih "Izinkan"), tutup aplikasi lain yang memakai kamera, lalu muat ulang halaman ini.
                                Absen kartu RFID tetap bisa berjalan seperti biasa.
                            </p>
                        </div>

                        {{-- Pratinjau kamera --}}
                        <div x-show="consent && !kameraGagal" class="mt-4 relative rounded-xl overflow-hidden bg-gray-900 border border-gray-300" style="aspect-ratio: 16/9;">
                            <video x-ref="cameraPreview" autoplay muted playsinline
                                   class="w-full h-full object-cover scale-x-[-1]"></video>
                            <canvas data-face-overlay aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full"></canvas>
                            <div x-show="!kameraGagal && !manualMode" x-cloak class="pointer-events-none absolute inset-x-0 top-2 flex justify-center px-3">
                                <p class="rounded-full bg-slate-900/75 px-3.5 py-1 text-xs font-extrabold text-emerald-300 shadow" x-text="autoHint"></p>
                            </div>

                            {{-- Overlay hitungan mundur --}}
                            <div x-show="countdown !== null" x-cloak
                                 class="absolute inset-0 flex items-center justify-center bg-black/40">
                                <div class="text-8xl font-black text-white drop-shadow-lg" x-text="countdown"></div>
                            </div>
                        </div>
                    </div>

                    {{-- 3 Slot Foto --}}
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <h2 class="text-lg font-bold text-gray-900">3 Foto Wajah</h2>
                        <p class="text-xs text-gray-500 mt-1">Foto diambil otomatis saat Anda mengikuti panduan gerakan wajah (atau tekan "Ambil Foto" pada mode manual). Ulangi foto mana pun yang kurang bagus sebelum menyimpan.</p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                            <template x-for="(foto, index) in photos" :key="index">
                                <div class="rounded-xl border-2 overflow-hidden"
                                     :class="foto ? 'border-emerald-400' : 'border-dashed border-gray-300 bg-gray-50'">
                                    <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center overflow-hidden">
                                        <img x-show="foto" :src="foto" class="w-full h-full object-cover" :alt="'Foto wajah ke-' + (index + 1)">
                                        <div x-show="!foto" class="text-center text-gray-400">
                                            <div class="text-3xl font-black" x-text="index + 1"></div>
                                            <div class="text-[10px] font-semibold uppercase tracking-wider mt-1">Belum ada foto</div>
                                        </div>
                                    </div>
                                    <div class="p-2 flex items-center justify-between bg-white border-t border-gray-100">
                                        <span class="text-xs font-bold text-gray-600" x-text="photoLabels[index] || ('Foto ' + (index + 1))"></span>
                                        <button type="button" x-show="foto" @click="ulangFoto(index)"
                                                class="text-xs font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 px-2 py-1 rounded transition">
                                            Ulangi
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-3 mt-6">
                            <button type="button" x-show="manualMode" @click="ambilFoto()"
                                    :disabled="!consent || kameraGagal || countdown !== null || saving || photos.every(p => p)"
                                    class="inline-flex items-center px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold text-sm shadow-sm transition">
                                <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span x-text="countdown !== null ? 'Bersiap... ' + countdown : 'Ambil Foto'"></span>
                            </button>

                            <button type="button" @click="simpan()"
                                    :disabled="saving || photos.some(p => !p)"
                                    class="inline-flex items-center px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold text-sm shadow-sm transition">
                                <span x-text="saving ? 'Menyimpan...' : 'Simpan Pendaftaran'"></span>
                            </button>

                            <button type="button" x-show="consent && !kameraGagal && !manualMode && !photos.every(p => p)" @click="aktifManualFallback()"
                                    class="text-xs font-semibold text-gray-500 hover:text-gray-800 underline">
                                Gunakan tombol ambil foto manual
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-2" x-show="photos.some(p => !p)">
                            Tersimpan: <span x-text="photos.filter(p => p).length"></span> dari 3 foto.
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <script>
        function faceEnrollmentApp() {
            return {
                consent: false,
                kameraGagal: false,
                cameraStream: null,
                photos: [null, null, null],
                photoLabels: ['Foto 1 (Depan)', 'Foto 2 (Kanan)', 'Foto 3 (Kiri)'],
                countdown: null,
                manualMode: false,
                autoStep: 0,
                autoHint: 'Langkah 1/4: Hadap lurus ke tengah kamera',
                autoSession: null,
                saving: false,
                message: '',
                messageOk: true,
                sudahTerdaftar: {{ $wajahTervalidasi ? 'true' : 'false' }},
                mode: '{{ $wajahTervalidasi ? 'status' : 'form' }}',
                tanggalDaftar: @js($tanggalDaftar),
                tanggalDaftarUlang: @js($tanggalDaftarUlang),
                photosEmbedded: @js($photosEmbedded),
                showConfirmDaftarUlang: false,

                init() {
                    window.addEventListener('beforeunload', () => this.stopCamera());
                    window.addEventListener('face-autocapture:ready', () => {
                        if (this.consent && this.cameraStream && !this.manualMode && !this.autoSession) {
                            this.startAutoCapture();
                        }
                    });
                    window.addEventListener('face-autocapture:failed', () => {
                        this.aktifManualFallback();
                    });
                },

                onConsentChange() {
                    if (this.consent) {
                        this.startCamera();
                    } else {
                        this.stopCamera();
                    }
                },

                stopCamera() {
                    if (this.autoSession) {
                        this.autoSession.stop();
                        this.autoSession = null;
                    }
                    if (this.cameraStream) {
                        this.cameraStream.getTracks().forEach(track => track.stop());
                        this.cameraStream = null;
                    }
                },

                // Pratinjau kamera gagal-aman: kamera tidak ada / izin ditolak /
                // browser tidak mendukung → pratinjau disembunyikan, halaman utuh.
                async startCamera() {
                    this.kameraGagal = false;
                    try {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            this.kameraGagal = true;
                            this.manualMode = true;
                            return;
                        }

                        this.cameraStream = await navigator.mediaDevices.getUserMedia({
                            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                            audio: false
                        });

                        this.$nextTick(() => {
                            const video = this.$refs.cameraPreview;
                            if (video) {
                                video.srcObject = this.cameraStream;
                                if (!this.manualMode) {
                                    this.startAutoCapture();
                                }
                            }
                        });
                    } catch (e) {
                        this.cameraStream = null;
                        this.kameraGagal = true;
                        this.manualMode = true;
                    }
                },

                startAutoCapture() {
                    if (this.manualMode || !this.$refs.cameraPreview) return;
                    if (!window.FaceAutoCapture || !window.FaceAutoCapture.FaceAutoCaptureSession) {
                        return;
                    }
                    if (this.autoSession) {
                        this.autoSession.stop();
                    }
                    const firstEmpty = this.photos.findIndex(p => !p);
                    const startStep = firstEmpty === -1 ? 3 : firstEmpty;

                    this.autoSession = new window.FaceAutoCapture.FaceAutoCaptureSession({
                        video: this.$refs.cameraPreview,
                        onStateChange: ({ stepIndex, hint }) => {
                            this.autoStep = stepIndex;
                            this.autoHint = hint;
                        },
                        onPhotoCaptured: (slotIndex, jpegDataUrl) => {
                            if (slotIndex >= 0 && slotIndex < 3) {
                                this.photos[slotIndex] = jpegDataUrl;
                            }
                        },
                        onComplete: () => {
                            this.autoStep = 4;
                            this.autoHint = 'Selesai! 3 foto wajah & kedipan mata terverifikasi.';
                            this.stopCamera();
                        },
                        onFallback: () => {
                            this.aktifManualFallback();
                        },
                    });
                    this.autoSession.start().then(() => {
                        if (this.autoSession && startStep > 0) {
                            this.autoSession.resetToStep(startStep);
                        }
                    });
                },

                aktifManualFallback() {
                    if (this.autoSession) {
                        this.autoSession.stop();
                        this.autoSession = null;
                    }
                    this.manualMode = true;
                },

                // Hitung mundur 3 → 2 → 1, lalu tangkap frame ke slot kosong pertama
                ambilFoto() {
                    if (this.countdown !== null || this.photos.every(p => p)) return;

                    this.countdown = 3;
                    const timer = setInterval(() => {
                        this.countdown -= 1;
                        if (this.countdown <= 0) {
                            clearInterval(timer);
                            this.countdown = null;
                            this.tangkapFrame();
                        }
                    }, 1000);
                },

                tangkapFrame() {
                    const video = this.$refs.cameraPreview;
                    if (!this.cameraStream || !video || !video.videoWidth) {
                        this.tampilkanPesan('Kamera belum siap. Coba lagi.', false);
                        return;
                    }

                    try {
                        const canvas = document.createElement('canvas');
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        canvas.getContext('2d').drawImage(video, 0, 0);
                        const dataUrl = canvas.toDataURL('image/jpeg', 0.7);

                        const slotKosong = this.photos.findIndex(p => !p);
                        if (slotKosong !== -1) {
                            this.photos[slotKosong] = dataUrl;
                        }

                        // Setelah 3 slot terisi, matikan kamera
                        if (this.photos.every(p => p)) {
                            this.stopCamera();
                        }
                    } catch (e) {
                        this.tampilkanPesan('Gagal mengambil foto. Coba lagi.', false);
                    }
                },

                // Dialog "Ya": pendaftaran lama belum dihapus; alur 3 foto baru dimulai
                // (penghapusan terjadi saat simpan baru, dalam satu transaksi).
                yaDaftarUlang() {
                    this.showConfirmDaftarUlang = false;
                    this.mode = 'form';
                    this.consent = false;
                    this.photos = [null, null, null];
                    this.kameraGagal = false;
                },

                ulangFoto(index) {
                    this.photos[index] = null;
                    this.autoStep = index;
                    // Kamera dinyalakan lagi bila sebelumnya dimatikan karena 3 slot penuh
                    if (this.consent && !this.cameraStream) {
                        this.startCamera();
                    } else if (this.autoSession && !this.manualMode) {
                        this.autoSession.resetToStep(index);
                    }
                },

                tampilkanPesan(teks, ok) {
                    this.message = teks;
                    this.messageOk = ok;
                },

                async simpan() {
                    if (this.photos.some(p => !p)) {
                        this.tampilkanPesan('Foto wajah wajib lengkap 3 foto.', false);
                        return;
                    }

                    this.saving = true;
                    try {
                        const res = await fetch("{{ route('siswa.wajah.simpan') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ consent: this.consent, photos: this.photos })
                        });

                        const data = await res.json();
                        this.tampilkanPesan(data.message ?? (res.ok ? 'Pendaftaran wajah berhasil disimpan.' : 'Gagal menyimpan.'), res.ok && data.success !== false);

                        if (res.ok && data.success) {
                            this.stopCamera();
                            this.sudahTerdaftar = true;
                            this.mode = 'status';
                            this.tanggalDaftar = data.tanggal_daftar ?? this.tanggalDaftar;
                            this.tanggalDaftarUlang = data.tanggal_daftar_ulang ?? this.tanggalDaftarUlang;
                            this.photosEmbedded = [...this.photos];
                        }
                    } catch (e) {
                        this.tampilkanPesan('Gagal menghubungi server. Periksa koneksi Anda.', false);
                    } finally {
                        this.saving = false;
                    }
                }
            }
        }
    </script>
</x-app-layout>
