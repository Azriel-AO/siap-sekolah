<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Absen Gerbang — SIAP KNBI</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-white font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white"
      x-data="kioskGerbangApp()"
      x-init="initKiosk()"
      @click="focusInput()">

    <!-- Header Kiosk -->
    <header class="p-6 sm:px-12 flex justify-between items-center border-b border-slate-800 bg-slate-900/80 backdrop-blur">
        <div class="flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-2xl shadow-lg shadow-indigo-500/30">
                S
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white">SIAP — SMK KARYA NUGRAHA</h1>
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
    <main class="flex-1 flex flex-col items-center justify-center p-6 text-center max-w-4xl mx-auto w-full">

        <!-- Default State: Pilih Metode -->
        <div x-show="!result" x-transition.opacity.duration.300ms class="w-full space-y-8">
            <div>
                <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">Absen Gerbang</h2>
                <p class="text-slate-400 text-sm sm:text-lg mt-3 max-w-md mx-auto">
                    Pilih cara absen: tempelkan kartu pelajar atau letakkan jari pada sensor sidik jari.
                </p>
            </div>

            <!-- Tombol Metode -->
            <div class="flex justify-center gap-4">
                <button type="button" @click="setMetode('kartu')"
                        :class="metode === 'kartu' ? 'bg-indigo-600 border-indigo-400 shadow-indigo-500/30' : 'bg-slate-800 border-slate-700'"
                        class="flex flex-col items-center gap-2 px-8 py-5 rounded-2xl border-2 shadow-lg transition">
                    <svg class="w-10 h-10 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span class="text-sm font-bold text-white">Kartu</span>
                </button>
                <button type="button" @click="setMetode('sidik-jari')"
                        :class="metode === 'sidik-jari' ? 'bg-indigo-600 border-indigo-400 shadow-indigo-500/30' : 'bg-slate-800 border-slate-700'"
                        class="flex flex-col items-center gap-2 px-8 py-5 rounded-2xl border-2 shadow-lg transition">
                    <svg class="w-10 h-10 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2m-6.5 1.5A6.5 6.5 0 0112 5a6.5 6.5 0 014.5 11M8 14.5A3.5 3.5 0 0012 17a3.5 3.5 0 003.5-3M5.5 12H3m18 0h-2.5" />
                    </svg>
                    <span class="text-sm font-bold text-white">Sidik Jari</span>
                </button>
                <button type="button" disabled title="Segera hadir"
                        class="flex flex-col items-center gap-2 px-8 py-5 rounded-2xl border-2 border-slate-800 bg-slate-800/40 opacity-60 cursor-not-allowed">
                    <svg class="w-10 h-10 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0" />
                    </svg>
                    <span class="text-sm font-bold text-slate-500">Wajah</span>
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 bg-amber-950/60 border border-amber-500/40 rounded-full px-2 py-0.5 -mt-1">Segera</span>
                </button>
            </div>

            {{-- Panel: Kartu --}}
            <template x-if="metode === 'kartu'">
                <form @submit.prevent="submitTap()" class="max-w-md mx-auto">
                    <div class="relative">
                        <input type="text"
                               x-ref="cardInput"
                               x-model="cardUid"
                               placeholder="Tempelkan kartu ke scanner..."
                               class="w-full px-5 py-4 bg-slate-800/80 border-2 border-slate-700 focus:border-indigo-500 rounded-2xl text-center text-lg font-mono text-white placeholder-slate-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/20 transition"
                               autocomplete="off" />
                    </div>
                    <button type="submit" class="mt-3 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md transition">
                        Simulasi Tap Kartu (Enter)
                    </button>
                </form>
            </template>

            {{-- Panel: Sidik Jari --}}
            <template x-if="metode === 'sidik-jari'">
                <div class="max-w-md mx-auto space-y-4">
                    <div class="rounded-3xl border-2 border-indigo-500/40 bg-indigo-950/40 p-6">
                        <div class="w-20 h-20 mx-auto rounded-full border-4 border-indigo-400/60 border-t-transparent animate-spin"></div>
                        <p class="mt-4 text-xl font-bold text-indigo-200">Letakkan jari pada sensor…</p>
                        <p class="mt-1 text-xs text-slate-400" x-text="fingerprintStatus"></p>
                    </div>
                    <button type="button" @click="setMetode('kartu')" class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold transition">
                        Batal / pakai Kartu
                    </button>
                </div>
            </template>
        </div>

        <!-- Result State -->
        <div x-show="result" x-cloak x-transition.scale.duration.250ms class="w-full max-w-xl mx-auto">
            <div class="rounded-3xl p-8 sm:p-10 shadow-2xl border transition text-center"
                 :class="{
                     'bg-emerald-950/90 border-emerald-500/50 text-white': result && result.status === 'HADIR',
                     'bg-amber-950/90 border-amber-500/50 text-white': result && result.status === 'TERLAMBAT' && result.type === 'check_in',
                     'bg-blue-950/90 border-blue-500/50 text-white': result && result.type === 'check_out',
                     'bg-red-950/90 border-red-500/50 text-white': result && !result.success
                 }">

                <!-- Foto preview (bila kamera sempat menangkap) -->
                <img x-show="fotoBukti" :src="fotoBukti" alt="Foto bukti" class="w-40 h-28 object-cover rounded-xl border-2 border-white/20 mx-auto mb-5 shadow-lg">

                <!-- Icon Status -->
                <div class="w-20 h-20 mx-auto rounded-2xl flex items-center justify-center mb-6 shadow-lg"
                     :class="{
                         'bg-emerald-500 text-white': result && result.status === 'HADIR',
                         'bg-amber-500 text-white': result && result.status === 'TERLAMBAT' && result.type === 'check_in',
                         'bg-blue-500 text-white': result && result.type === 'check_out',
                         'bg-red-500 text-white': result && !result.success
                     }">
                    <template x-if="result && result.success && result.type === 'check_in'">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </template>
                    <template x-if="result && result.success && result.type === 'check_out'">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7" />
                        </svg>
                    </template>
                    <template x-if="result && !result.success">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </template>
                </div>

                <!-- Judul Status -->
                <div class="text-xs uppercase tracking-widest font-extrabold opacity-80" x-text="result?.status_label || (result?.success ? 'Berhasil' : 'Peringatan')"></div>
                <h3 class="text-3xl sm:text-4xl font-black mt-2" x-text="result?.student_name || 'Tidak Dikenali'"></h3>
                <p class="text-sm mt-1 opacity-75 font-mono" x-show="result?.nis" x-text="'NIS: ' + result?.nis + (result?.kelas ? '  •  ' + result?.kelas : '')"></p>

                <!-- Pesan -->
                <div class="mt-6 p-4 rounded-xl bg-black/20 text-base font-medium" x-text="result?.message"></div>

                <!-- Tombol Daftar Mandiri untuk Kartu / Sidik Jari Tidak Dikenal -->
                <template x-if="result && (result.registration_url || result.type === 'not_found' || result.type === 'fingerprint_not_found')">
                    <div class="mt-6">
                        <a :href="result?.registration_url || '{{ route('pendaftaran.index') }}'"
                           class="inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-base shadow-xl shadow-indigo-600/40 border border-indigo-400/30 transition transform hover:scale-105">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                            <span>Daftar dulu</span>
                        </a>
                    </div>
                </template>

                <!-- Waktu Server -->
                <div class="mt-4 text-xs font-mono opacity-60" x-show="result?.time">
                    Waktu Server: <span x-text="result?.time"></span> WIB
                </div>
            </div>
        </div>

    </main>

    <!-- Pratinjau Kamera (pojok kanan bawah) — tersembunyi aman bila kamera tidak tersedia -->
    <div x-ref="cameraBox" x-cloak class="hidden fixed bottom-24 right-6 z-40 select-none">
        <div class="rounded-2xl overflow-hidden border-2 border-slate-700 shadow-2xl bg-slate-800 relative">
            <video x-ref="cameraPreview" autoplay muted playsinline class="w-44 sm:w-56 aspect-video object-cover block"></video>
            <div class="absolute top-1.5 left-2 px-2 py-0.5 rounded-md bg-black/60 text-[10px] font-bold text-slate-200 tracking-wide">
                Kamera Bukti Absen
            </div>
            <div class="absolute bottom-1.5 right-2 w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></div>
        </div>
    </div>

    <!-- Footer Kiosk -->
    <footer class="p-6 border-t border-slate-800 bg-slate-900/90 text-center">
        <p class="text-xs text-slate-400 font-medium mb-3">
            Simulasi Cepat untuk Evaluator Demo (Klik untuk otomatis tap):
        </p>
        <div class="flex flex-wrap justify-center gap-2 max-w-3xl mx-auto">
            <button type="button" @click="testCard('CARD-TKJ-009')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-mono text-emerald-300 border border-slate-700">
                CARD-TKJ-009
            </button>
            <button type="button" @click="testCard('CARD-TKJ-010')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-mono text-emerald-300 border border-slate-700">
                CARD-TKJ-010
            </button>
            <button type="button" @click="testCard('CARD-TKJ-001')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-mono text-blue-300 border border-slate-700">
                CARD-TKJ-001
            </button>
            <button type="button" @click="testCard('CARD-SALAH-999')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-mono text-red-300 border border-slate-700">
                CARD-SALAH-999
            </button>
        </div>
        <div class="mt-4 text-[11px] text-slate-500">
            Kiosk Gerbang Sekolah — satu titik untuk semua kelas. Jam masuk/pulang mengikuti kelas masing-masing siswa.
        </div>
    </footer>

    <script>
        function kioskGerbangApp() {
            return {
                metode: 'kartu',
                cardUid: '',
                result: null,
                fotoBukti: '',
                currentTime: '',
                currentDate: '',
                resetTimer: null,
                cameraStream: null,
                fingerprintPolling: null,
                fingerprintStatus: 'Menunggu jari di sensor…',

                initKiosk() {
                    this.updateClock();
                    setInterval(() => this.updateClock(), 1000);
                    this.$nextTick(() => this.focusInput());
                    this.startCamera();
                    window.addEventListener('beforeunload', () => this.stopCamera());
                },

                stopCamera() {
                    if (this.cameraStream) {
                        this.cameraStream.getTracks().forEach(track => track.stop());
                        this.cameraStream = null;
                    }
                },

                // Kamera gagal-aman: tanpa kamera/izin ditolak -> pratinjau disembunyikan,
                // alur kartu & sidik jari tetap berjalan.
                async startCamera() {
                    try {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            this.$refs.cameraBox?.classList.add('hidden');
                            return;
                        }

                        this.cameraStream = await navigator.mediaDevices.getUserMedia({
                            video: { width: { ideal: 640 }, height: { ideal: 360 }, facingMode: 'user' },
                            audio: false
                        });

                        const preview = this.$refs.cameraPreview;
                        preview.srcObject = this.cameraStream;
                        this.$refs.cameraBox.classList.remove('hidden');
                    } catch (e) {
                        this.cameraStream = null;
                        this.$refs.cameraBox?.classList.add('hidden');
                    }
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

                setMetode(m) {
                    this.metode = m;
                    this.stopFingerprintPolling();
                    if (m === 'sidik-jari') {
                        this.mulaiPollingSidikJari();
                    } else {
                        this.$nextTick(() => this.focusInput());
                    }
                },

                stopFingerprintPolling() {
                    if (this.fingerprintPolling) {
                        clearInterval(this.fingerprintPolling);
                        this.fingerprintPolling = null;
                    }
                },

                // Polling server (server memanggil sensor/mock) tiap 1,5 detik
                mulaiPollingSidikJari() {
                    this.fingerprintStatus = 'Menunggu jari di sensor…';
                    this.stopFingerprintPolling();
                    this.fingerprintPolling = setInterval(() => this.pollSidikJari(), 1500);
                },

                async pollSidikJari() {
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

                        // Respons gagal "sensor tidak tersedia" (503) -> tampilkan,
                        // tapi jangan bombardir layar setiap 1,5 detik
                        if (res.status === 503) {
                            this.fingerprintStatus = data.message ?? 'Sensor tidak tersedia.';
                            return;
                        }

                        if (res.status === 404 && data.type === 'fingerprint_not_found') {
                            this.stopFingerprintPolling();
                            this.fotoBukti = foto || '';
                            this.showResult(data);
                            return;
                        }

                        if (data.success) {
                            this.stopFingerprintPolling();
                            this.fotoBukti = foto || '';
                            this.showResult(data);
                        } else if (data.type === 'cooldown' || data.type === 'already_completed') {
                            this.stopFingerprintPolling();
                            this.fotoBukti = foto || '';
                            this.showResult(data);
                        }
                    } catch (e) {
                        this.fingerprintStatus = 'Gagal menghubungi server. Memeriksa lagi…';
                    }
                },

                testCard(uid) {
                    this.cardUid = uid;
                    this.submitTap();
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
                    if (!uid) return;

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

                showResult(data) {
                    this.result = data;

                    if (this.resetTimer) clearTimeout(this.resetTimer);
                    const resetDuration = (data?.type === 'not_found' || data?.type === 'fingerprint_not_found') ? 8000 : 4000;
                    this.resetTimer = setTimeout(() => {
                        this.result = null;
                        this.fotoBukti = '';
                        this.metode = 'kartu';
                        this.$nextTick(() => this.focusInput());
                    }, resetDuration);
                }
            }
        }
    </script>
</body>
</html>
