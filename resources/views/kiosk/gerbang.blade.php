<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Absen Gerbang — E-PRESKNBI</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-sekolah.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-sekolah.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-white font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white"
      data-kiosk-face-verify
      x-data="kioskGerbangApp()"
      x-init="initKiosk()"
      @click="focusInput()">

    <!-- Header Kiosk -->
    <header class="p-6 sm:px-12 flex justify-between items-center border-b border-slate-800 bg-slate-900/80 backdrop-blur">
        <div class="flex items-center space-x-4">
            <div class="bg-white rounded-lg p-1 flex items-center justify-center shadow-lg">
                <img src="{{ asset('images/logo-sekolah.png') }}" alt="Logo SMK Karya Nugraha" class="h-10 w-auto object-contain">
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white">E-PRESKNBI — SMK KARYA NUGRAHA</h1>
                <p class="text-xs sm:text-sm text-slate-400 font-medium">Absen Gerbang Tunggal</p>
            </div>
        </div>

        <div class="flex items-center space-x-6">
            <div class="text-right">
                <div class="text-2xl sm:text-3xl font-mono font-bold tracking-wider text-emerald-400" x-text="currentTime">--:--:--</div>
                <div class="text-xs text-slate-400" x-text="currentDate">--</div>
            </div>
            <div class="hidden sm:inline-flex px-4 py-2 rounded-xl bg-indigo-950/80 border border-indigo-500/40 text-indigo-300 font-bold text-sm tracking-wide">
                Gerbang Sekolah
            </div>
        </div>
    </header>

    <!-- Main Kiosk Body -->
    <main class="flex-1 flex flex-col items-center justify-center px-4 py-3 sm:px-6 sm:py-4 text-center w-full">

        <div class="w-full max-w-4xl">

            <!-- Default State: kamera sentral + dua kanal selalu aktif -->
            <div x-show="!result" x-transition.opacity.duration.300ms class="w-full flex flex-col items-center gap-3">

                <div>
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">Absen Gerbang</h2>
                    <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl mx-auto">
                        Tempelkan kartu pelajar ATAU letakkan jari pada sensor — keduanya langsung aktif, tanpa perlu berganti mode.
                    </p>
                </div>

                {{-- Input tersembunyi untuk pembaca kartu (wedge capture): UID diketik reader + Enter --}}
                <input type="text" x-ref="cardInput" x-model="cardUid" @keydown.enter.prevent="submitTap()"
                       class="sr-only" autocomplete="off" aria-label="Tempelkan kartu pelajar" tabindex="-1" />

                {{-- Kamera utama: pusat layar, rasio tetap, video mengisi penuh container --}}
                <div x-ref="cameraBox" x-cloak class="w-full select-none">
                    <div class="relative mx-auto aspect-video w-full rounded-3xl overflow-hidden border-2 border-indigo-500/40 shadow-2xl shadow-indigo-900/30 bg-slate-800"
                         style="max-width: min(48rem, calc(40vh * 16 / 9));">
                        <video x-ref="cameraPreview" autoplay muted playsinline x-show="cameraReady"
                               class="absolute inset-0 h-full w-full object-cover object-center block bg-slate-900"></video>

                        {{-- Fallback ramah: kamera tidak ada / izin ditolak. Kartu & sidik jari tetap aktif. --}}
                        <div x-show="!cameraReady" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-6 text-center">
                            <svg class="w-10 h-10 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M5 18h8a3 3 0 003-3V9a3 3 0 00-3-3H5a3 3 0 00-3 3v6a3 3 0 003 3zM3 3l18 18" />
                            </svg>
                            <p class="text-sm sm:text-base font-bold text-white">Kamera belum terdeteksi</p>
                            <p class="text-[11px] sm:text-xs text-slate-400 max-w-sm leading-relaxed">
                                Absen kartu &amp; jari tetap aktif. Izinkan kamera di browser untuk foto bukti.
                            </p>
                            <p x-show="cameraError" x-text="cameraError" class="text-[10px] text-amber-400/90 max-w-sm leading-relaxed"></p>
                        </div>

                        <div class="absolute top-3 left-3 px-3 py-1 rounded-lg bg-black/60 text-[11px] font-bold text-slate-200 tracking-wide">
                            <span x-text="verifyingFace ? 'Verifikasi Wajah 1:1 (Face ID)' : 'Kamera Bukti Absen'">Kamera Bukti Absen</span>
                        </div>
                        <div x-show="cameraReady" x-cloak class="absolute bottom-3 right-3 flex items-center gap-2 px-3 py-1 rounded-lg bg-black/60 text-[11px] font-bold text-slate-200">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                            <span>LIVE</span>
                        </div>

                        {{-- Panel Status Verifikasi Wajah 1:1 (muncul otomatis setelah tap kartu siswa ber-enrollment) --}}
                        <div x-show="verifyingFace" x-cloak x-transition.opacity.duration.200ms
                             class="absolute inset-x-3 bottom-3 sm:inset-x-6 sm:bottom-4 rounded-2xl bg-slate-950/90 border border-indigo-400/50 px-4 py-3 text-left shadow-2xl backdrop-blur">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <svg class="w-6 h-6 text-indigo-400 animate-spin shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <div class="min-w-0">
                                        <p class="text-xs sm:text-sm font-black text-white truncate">
                                            Verifikasi Wajah: <span class="text-indigo-300" x-text="faceVerifyStudent?.student_name || '-'"></span>
                                            <span x-show="faceVerifyStudent?.card_label" class="font-mono text-[11px] text-emerald-300" x-text="'(' + faceVerifyStudent?.card_label + ')'"></span>
                                        </p>
                                        <p class="text-[11px] sm:text-xs text-slate-300 font-medium mt-0.5" x-text="faceVerifyStatus || 'Memproses landmark wajah…'"></p>
                                    </div>
                                </div>
                                <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-lg bg-amber-500/20 border border-amber-400/40 text-amber-200 text-[11px] font-bold shrink-0">
                                    Pastikan wajah terkena cahaya
                                </span>
                            </div>
                            <p class="sm:hidden text-[10px] text-amber-300 font-semibold mt-1.5">
                                Pastikan wajah terkena cahaya &amp; menghadap kamera
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Petunjuk dua kanal tepat di bawah kamera --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-3xl">
                    <div class="rounded-2xl border-2 border-indigo-500/40 bg-slate-800/60 px-4 py-3 flex items-center justify-center gap-3">
                        <svg class="w-9 h-9 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <div class="text-left">
                            <p class="text-base font-black text-white tracking-wide">Tempelkan Kartu</p>
                            <p class="text-[11px] text-slate-400">Pelajar menempelkan kartu pada scanner</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border-2 border-indigo-500/40 bg-slate-800/60 px-4 py-3 flex items-center justify-center gap-3">
                        <svg class="w-9 h-9 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2m-6.5 1.5A6.5 6.5 0 0112 5a6.5 6.5 0 014.5 11M8 14.5A3.5 3.5 0 0012 17a3.5 3.5 0 003.5-3M5.5 12H3m18 0h-2.5" />
                        </svg>
                        <div class="text-left">
                            <p class="text-base font-black text-white tracking-wide">Tekan Jari pada Sensor</p>
                            {{-- Indikator status sensor (kecil) --}}
                            <div class="flex items-center space-x-2 text-[11px]">
                                <span class="w-2 h-2 rounded-full" :class="sensorBelumAktif ? 'bg-amber-400' : (sensorTimeoutInfo ? 'bg-sky-400 animate-pulse' : 'bg-emerald-400')"></span>
                                <span class="text-slate-400 font-medium" x-text="sensorBelumAktif ? 'Sensor belum aktif — hadir dengan tap kartu' : (sensorTimeoutInfo ? 'Sensor sedang menunggu jari…' : 'Sensor aktif')"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tombol HANYA untuk driver mock (presenter demo). Di produksi (bridge) tidak muncul sama sekali. --}}
                @if($isMockFingerprint)
                    <div class="w-full max-w-md">
                        <button type="button" @click="simulasiJari()"
                                :disabled="demoLoading"
                                class="w-full px-6 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-400 disabled:bg-amber-800 text-slate-900 font-black tracking-wide shadow-lg transition">
                            <span x-text="demoLoading ? 'Membaca sidik jari…' : 'DEMO — Simulasi jari'"></span>
                        </button>
                        <p class="text-[10px] text-amber-500/80 font-mono mt-1">Hanya tampil di driver mock (laptop demo). Sensor nyata tidak menampilkan tombol ini.</p>
                    </div>
                @endif
            </div>

            <!-- Result State -->
            <div x-show="result" x-cloak x-transition.scale.duration.250ms class="w-full max-w-xl mx-auto">
                <div class="rounded-3xl p-6 sm:p-8 shadow-2xl border transition text-center"
                     :class="{
                         'bg-emerald-950/90 border-emerald-500/50 text-white': result && result.status === 'HADIR',
                         'bg-amber-950/90 border-amber-500/50 text-white': result && result.status === 'TERLAMBAT' && result.type === 'check_in',
                         'bg-blue-950/90 border-blue-500/50 text-white': result && result.type === 'check_out',
                         'bg-red-950/90 border-red-500/50 text-white': result && !result.success
                     }">

                    <!-- Foto preview (bila kamera sempat menangkap) -->
                    <img x-show="fotoBukti" :src="fotoBukti" alt="Foto bukti" class="w-32 h-20 object-cover rounded-xl border-2 border-white/20 mx-auto mb-4 shadow-lg">

                    <!-- Icon Status -->
                    <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-4 shadow-lg"
                         :class="{
                             'bg-emerald-500 text-white': result && result.status === 'HADIR',
                             'bg-amber-500 text-white': result && result.status === 'TERLAMBAT' && result.type === 'check_in',
                             'bg-blue-500 text-white': result && result.type === 'check_out',
                             'bg-red-500 text-white': result && !result.success
                         }">
                        <template x-if="result && result.success && result.type === 'check_in'">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </template>
                        <template x-if="result && result.success && result.type === 'check_out'">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7" />
                            </svg>
                        </template>
                        <template x-if="result && !result.success">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </template>
                    </div>

                    <!-- Judul Status -->
                    <div class="text-xs uppercase tracking-widest font-extrabold opacity-80" x-text="result?.status_label || (result?.success ? 'Berhasil' : 'Peringatan')"></div>
                    <h3 class="text-3xl sm:text-4xl font-black mt-2" x-text="result?.student_name || 'Tidak Dikenali'"></h3>
                    <p class="text-sm mt-1 opacity-75 font-mono" x-show="result?.nis" x-text="'NIS: ' + result?.nis + (result?.kelas ? '  •  ' + result?.kelas : '') + (result?.card_label ? '  •  ' + result?.card_label : '')"></p>

                    <!-- Pesan -->
                    <div class="mt-4 p-3 rounded-xl bg-black/20 text-sm sm:text-base font-medium"
                         x-text="result?.success && result?.card_label ? ('Halo, ' + result.student_name + ' (' + result.card_label + ')') : result?.message"></div>

                    <!-- Tombol Daftar Mandiri untuk Kartu / Sidik Jari Tidak Dikenal -->
                    <template x-if="result && (result.registration_url || result.type === 'not_found' || result.type === 'fingerprint_not_found' || result.type === 'fingerprint_unknown')">
                        <div class="mt-4">
                            <a :href="result?.registration_url || '{{ route('pendaftaran.index') }}'"
                               class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-base shadow-xl shadow-indigo-600/40 border border-indigo-400/30 transition transform hover:scale-105">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                <span>Daftar dulu</span>
                            </a>
                        </div>
                    </template>

                    <!-- Waktu Server -->
                    <div class="mt-3 text-xs font-mono opacity-60" x-show="result?.time">
                        Waktu Server: <span x-text="result?.time"></span> WIB
                    </div>
                </div>
            </div>

        </div>

    </main>

    <footer class="p-6 border-t border-slate-800 bg-slate-900/90 text-center">
        <p class="text-xs text-slate-500 font-medium">
            Kiosk Gerbang Sekolah — satu titik untuk semua kelas. Jam masuk/pulang mengikuti kelas masing-masing siswa.
        </p>
    </footer>

    <script>
        function kioskGerbangApp() {
            return {
                isMockFingerprint: @js($isMockFingerprint),
                cardUid: '',
                result: null,
                fotoBukti: '',
                currentTime: '',
                currentDate: '',
                resetTimer: null,
                cameraStream: null,
                cameraReady: false,
                cameraError: '',
                cameraRetryTimer: null,
                cameraStarting: false,
                loopTimer: null,
                sensorBelumAktif: false,
                sensorTimeoutInfo: false,
                demoLoading: false,
                verifyingFace: false,
                faceVerifyStudent: null,
                faceVerifyStatus: '',

                initKiosk() {
                    this.updateClock();
                    setInterval(() => this.updateClock(), 1000);
                    this.$nextTick(() => this.focusInput());
                    this.startCamera();
                    window.addEventListener('beforeunload', () => {
                        this.stopCamera();
                        this.stopAutoLoop();
                        if (this.cameraRetryTimer) {
                            clearTimeout(this.cameraRetryTimer);
                            this.cameraRetryTimer = null;
                        }
                    });
                    // Loop identify otomatis HANYA di driver bridge (produksi).
                    // Mock tidak boleh loop (identify mock selalu "menemukan" siswa).
                    if (!this.isMockFingerprint) {
                        this.loopFingerprint();
                    }
                },

                stopCamera() {
                    if (this.cameraStream) {
                        this.cameraStream.getTracks().forEach(track => track.stop());
                        this.cameraStream = null;
                    }
                    this.cameraReady = false;
                },

                // Kamera gagal-aman: container tetap tampil dengan pesan ramah.
                // Tanpa stream, coba lagi tiap ~10 detik; kartu & sidik jari tidak terblokir.
                async startCamera() {
                    if (this.cameraReady || this.cameraStarting) return;
                    this.cameraStarting = true;

                    try {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            this.cameraError = 'Browser ini belum mendukung akses kamera.';
                            this.scheduleCameraRetry();
                            return;
                        }

                        const stream = await navigator.mediaDevices.getUserMedia({
                            video: { width: { ideal: 640 }, height: { ideal: 360 }, facingMode: 'user' },
                            audio: false
                        });

                        this.cameraStream = stream;
                        const preview = this.$refs.cameraPreview;
                        if (preview) {
                            preview.srcObject = stream;
                        }

                        stream.getTracks().forEach(track => {
                            track.addEventListener('ended', () => {
                                this.stopCamera();
                                this.cameraError = 'Kamera terputus — absen kartu & jari tetap aktif.';
                                this.scheduleCameraRetry();
                            });
                        });

                        if (this.cameraRetryTimer) {
                            clearTimeout(this.cameraRetryTimer);
                            this.cameraRetryTimer = null;
                        }
                        this.cameraError = '';
                        this.cameraReady = true;
                    } catch (e) {
                        this.cameraStream = null;
                        this.cameraReady = false;
                        const izinDitolak = e && (e.name === 'NotAllowedError' || e.name === 'SecurityError');
                        this.cameraError = izinDitolak
                            ? 'Izin kamera ditolak. Izinkan kamera di browser untuk foto bukti.'
                            : 'Periksa kamera yang terpasang — percobaan ulang otomatis tiap 10 detik.';
                        this.scheduleCameraRetry();
                    } finally {
                        this.cameraStarting = false;
                    }
                },

                scheduleCameraRetry() {
                    if (this.cameraRetryTimer) return;
                    this.cameraRetryTimer = setTimeout(() => {
                        this.cameraRetryTimer = null;
                        if (!this.cameraReady) {
                            this.startCamera();
                        }
                    }, 10000);
                },

                updateClock() {
                    const now = new Date();
                    this.currentTime = now.toLocaleTimeString('id-ID', { hour12: false });
                    this.currentDate = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                },

                focusInput() {
                    if (this.$refs.cardInput) {
                        this.$refs.cardInput.focus();
                    }
                },

                stopAutoLoop() {
                    if (this.loopTimer) {
                        clearTimeout(this.loopTimer);
                        this.loopTimer = null;
                    }
                },

                // Loop identify otomatis (bridge): panggil terus-menerus;
                // satu panggilan boleh berjalan sampai ~25 detik (menunggu jari);
                // jeda antar-panggilan >= 2 detik setelah response. Mock TIDAK memanggil ini.
                async loopFingerprint() {
                    // Jangan panggil server saat kartu sedang menampilkan hasil (4/8 dtk) atau sedang verifikasi wajah
                    if (this.result || this.verifyingFace) {
                        this.loopTimer = setTimeout(() => this.loopFingerprint(), 2000);
                        return;
                    }

                    const foto = this.ambilFotoBukti();
                    try {
                        const res = await fetch("{{ route('kiosk.fingerprint.identificar') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ photo: foto, auto: 1 })
                        });

                        const data = await res.json();

                        // Bridge mati / sensor belum siap (503 / bridge_mati) -> tampil pesan,
                        // JANGAN di-poll cepat (cukup tiap ~15 detik)
                        if (res.status === 503 || data.type === 'bridge_mati') {
                            this.sensorBelumAktif = true;
                            this.sensorTimeoutInfo = false;
                            this.loopTimer = setTimeout(() => this.loopFingerprint(), 15000);
                            return;
                        }

                        this.sensorBelumAktif = false;

                        // Timeout menunggu jari -> pesan halus "Sensor sedang menunggu jari…", retry 5 detik
                        if (data.type === 'sensor_timeout') {
                            this.sensorTimeoutInfo = true;
                            this.loopTimer = setTimeout(() => this.loopFingerprint(), 5000);
                            return;
                        }

                        this.sensorTimeoutInfo = false;

                        // Jari ditempel tapi tidak dikenali -> tampilkan banner pesan + link "Daftar dulu",
                        // auto-hide 8 detik, pause loop 8 detik
                        if (data.type === 'fingerprint_unknown' || data.type === 'fingerprint_not_found') {
                            this.fotoBukti = foto || '';
                            this.showResult(data);
                            this.loopTimer = setTimeout(() => this.loopFingerprint(), 8000);
                            return;
                        }

                        // Hasil tampil (sukses / cooldown / sudah lengkap) -> pause loop sejenak
                        if (data.success || data.type === 'cooldown' || data.type === 'already_completed') {
                            this.fotoBukti = foto || '';
                            this.showResult(data);
                            this.loopTimer = setTimeout(() => this.loopFingerprint(), 4500);
                            return;
                        }

                        // sensor_idle (tidak ada jari saat window tunggu) -> DIAM, panggil lagi ~1,5 detik
                        this.loopTimer = setTimeout(() => this.loopFingerprint(), 1500);
                    } catch (e) {
                        // Network error / bridge mati -> pesan tenang, coba ~15 detik lagi
                        this.sensorBelumAktif = true;
                        this.sensorTimeoutInfo = false;
                        this.loopTimer = setTimeout(() => this.loopFingerprint(), 15000);
                    }
                },

                // HANYA driver mock: satu panggilan identify tanpa auto=1
                // (menghasilkan siswa terpilih = bahan demo presenter).
                async simulasiJari() {
                    if (!this.isMockFingerprint || this.demoLoading || this.result) return;
                    this.demoLoading = true;
                    const foto = this.ambilFotoBukti();
                    try {
                        const res = await fetch("{{ route('kiosk.fingerprint.identificar') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ photo: foto })
                        });

                        const data = await res.json();
                        this.fotoBukti = foto || '';
                        this.showResult(data);
                    } catch (e) {
                        this.showResult({
                            success: false,
                            type: 'ERROR',
                            message: 'Gagal menghubungi server kiosk. Periksa koneksi.'
                        });
                    } finally {
                        this.demoLoading = false;
                    }
                },

                // Ambil 1 frame dari kamera sebagai foto bukti absen (PRD F1).
                // Tanpa kamera / belum siap -> null; tap tetap jalan.
                ambilFotoBukti() {
                    const video = this.$refs.cameraPreview;
                    if (!this.cameraStream || !video || !video.videoWidth) return null;
                    try {
                        const canvas = document.createElement('canvas');
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        canvas.getContext('2d').drawImage(video, 0, 0);
                        return canvas.toDataURL('image/jpeg', 0.7);
                    } catch (e) {
                        return null;
                    }
                },

                async submitTap() {
                    const uid = this.cardUid.trim();
                    if (!uid || this.verifyingFace) return;

                    this.cardUid = '';
                    const foto = this.ambilFotoBukti();

                    try {
                        const res = await fetch("{{ route('kiosk.tap') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ card_uid: uid, photo: foto })
                        });

                        const data = await res.json();
                        this.fotoBukti = foto || '';

                        if (data && data.requires_face_verify && Array.isArray(data.face_photos) && data.face_photos.length === 3) {
                            await this.mulaiVerifikasiWajah(data, foto);
                            return;
                        }

                        this.showResult(data);
                    } catch (e) {
                        this.fotoBukti = '';
                        this.showResult({
                            success: false,
                            status: 'ERROR',
                            message: 'Gagal menghubungi server kiosk. Periksa koneksi.'
                        });
                    }
                },

                async mulaiVerifikasiWajah(tapData, fotoAwal = null) {
                    this.verifyingFace = true;
                    this.faceVerifyStudent = tapData;
                    this.faceVerifyStatus = 'Memproses landmark wajah… Pastikan wajah terkena cahaya.';

                    try {
                        if (!this.cameraReady) {
                            await this.startCamera();
                        }

                        if (!this.cameraReady || !this.$refs.cameraPreview) {
                            this.showResult({
                                success: false,
                                type: 'face_mismatch',
                                status_label: 'Kamera Belum Siap',
                                student_name: tapData.student_name,
                                nis: tapData.nis,
                                kelas: tapData.kelas,
                                card_label: tapData.card_label,
                                message: 'Kamera belum aktif untuk verifikasi wajah. Silakan gunakan sidik jari atau izinkan kamera.'
                            });
                            return;
                        }

                        let verifier = window.FaceVerification;
                        if (!verifier) {
                            await new Promise((resolve) => setTimeout(resolve, 400));
                            verifier = window.FaceVerification;
                        }

                        if (!verifier || typeof verifier.verifyStudentFace1to1 !== 'function') {
                            this.showResult({
                                success: false,
                                type: 'face_mismatch',
                                status_label: 'Verifikasi Wajah Gagal',
                                student_name: tapData.student_name,
                                nis: tapData.nis,
                                kelas: tapData.kelas,
                                card_label: tapData.card_label,
                                message: 'Modul verifikasi wajah belum siap. Silakan tap ulang atau gunakan sidik jari.'
                            });
                            return;
                        }

                        const hasil = await verifier.verifyStudentFace1to1({
                            facePhotos: tapData.face_photos,
                            video: this.$refs.cameraPreview,
                            timeoutMs: 5000,
                            onProgress: (info) => {
                                if (info && info.message) {
                                    this.faceVerifyStatus = info.message;
                                }
                            }
                        });

                        if (!hasil.matched) {
                            this.showResult({
                                success: false,
                                type: 'face_mismatch',
                                status_label: 'Wajah Tidak Cocok',
                                student_name: tapData.student_name,
                                nis: tapData.nis,
                                kelas: tapData.kelas,
                                card_label: tapData.card_label,
                                message: 'Wajah tidak cocok. Pastikan wajah terkena cahaya, lalu tap ulang kartu atau gunakan sidik jari.'
                            });
                            return;
                        }

                        const fotoBuktiTerbaru = this.ambilFotoBukti() || fotoAwal;
                        this.fotoBukti = fotoBuktiTerbaru || '';

                        const verifyRes = await fetch("{{ route('kiosk.face.verify') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                student_id: tapData.student_id,
                                match_score: hasil.matchScore,
                                photo: fotoBuktiTerbaru
                            })
                        });

                        const verifyData = await verifyRes.json();
                        this.showResult(verifyData);
                    } catch (err) {
                        console.warn('[face-verify] Terjadi kendala saat verifikasi wajah:', err);
                        this.showResult({
                            success: false,
                            type: 'face_mismatch',
                            status_label: 'Verifikasi Wajah Gagal',
                            student_name: tapData.student_name,
                            nis: tapData.nis,
                            kelas: tapData.kelas,
                            card_label: tapData.card_label,
                            message: 'Wajah tidak cocok atau gagal diverifikasi. Silakan tap ulang atau gunakan sidik jari.'
                        });
                    } finally {
                        this.verifyingFace = false;
                        this.faceVerifyStudent = null;
                        this.faceVerifyStatus = '';
                    }
                },

                showResult(data) {
                    this.result = data;

                    if (this.resetTimer) clearTimeout(this.resetTimer);
                    const resetDuration = (data?.type === 'not_found' || data?.type === 'fingerprint_not_found' || data?.type === 'fingerprint_unknown') ? 8000 : 4000;
                    this.resetTimer = setTimeout(() => {
                        this.result = null;
                        this.fotoBukti = '';
                        this.$nextTick(() => this.focusInput());
                    }, resetDuration);
                }
            }
        }
    </script>
</body>
</html>
