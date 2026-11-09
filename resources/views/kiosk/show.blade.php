<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kiosk Absensi — {{ $schoolClass->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-white font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white"
      x-data="kioskApp()"
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
                <p class="text-xs sm:text-sm text-slate-400 font-medium">Titik Absensi Kiosk Kelas</p>
            </div>
        </div>

        <div class="flex items-center space-x-6">
            <div class="text-right">
                <div class="text-2xl sm:text-3xl font-mono font-bold tracking-wider text-emerald-400" x-text="currentTime">--:--:--</div>
                <div class="text-xs text-slate-400" x-text="currentDate">--</div>
            </div>
            <div class="hidden sm:inline-flex px-4 py-2 rounded-xl bg-indigo-950/80 border border-indigo-500/40 text-indigo-300 font-bold text-sm tracking-wide">
                {{ $schoolClass->name }}
            </div>
        </div>
    </header>

    <!-- Main Kiosk Body -->
    <main class="flex-1 flex flex-col items-center justify-center p-6 text-center max-w-4xl mx-auto w-full">

        <!-- Default State: Menunggu Tap Kartu -->
        <div x-show="!result" x-transition.opacity.duration.300ms class="w-full space-y-8">
            <div class="relative inline-block">
                <div class="w-28 h-28 sm:w-36 sm:h-36 mx-auto rounded-3xl bg-slate-800 border-2 border-indigo-500/50 flex items-center justify-center shadow-2xl shadow-indigo-500/10 animate-pulse">
                    <svg class="w-16 h-16 sm:w-20 sm:h-20 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
            </div>

            <div>
                <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
                    Tempelkan Kartu Pelajar
                </h2>
                <p class="text-slate-400 text-sm sm:text-lg mt-3 max-w-md mx-auto">
                    Dekatkan kartu RFID ke scanner reader di samping layar untuk mencatat absensi.
                </p>
            </div>

            <!-- Form Hidden/Focused Input -->
            <form @submit.prevent="submitTap()" class="max-w-md mx-auto mt-4">
                <div class="relative">
                    <input type="text"
                           x-ref="cardInput"
                           x-model="cardUid"
                           placeholder="Ketik UID kartu jika menguji manual..."
                           class="w-full px-5 py-4 bg-slate-800/80 border-2 border-slate-700 focus:border-indigo-500 rounded-2xl text-center text-lg font-mono text-white placeholder-slate-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/20 transition"
                           autocomplete="off" />
                </div>
                <button type="submit" class="mt-3 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md transition">
                    Simulasi Tap Kartu (Enter)
                </button>
            </form>
        </div>

        <!-- Result State: Feedback Visual Besar -->
        <div x-show="result" x-cloak x-transition.scale.duration.250ms class="w-full max-w-xl mx-auto">
            <div class="rounded-3xl p-8 sm:p-10 shadow-2xl border transition text-center"
                 :class="{
                     'bg-emerald-950/90 border-emerald-500/50 text-white': result && result.status === 'HADIR',
                     'bg-amber-950/90 border-amber-500/50 text-white': result && result.status === 'TERLAMBAT' && result.type === 'check_in',
                     'bg-blue-950/90 border-blue-500/50 text-white': result && result.type === 'check_out',
                     'bg-red-950/90 border-red-500/50 text-white': result && !result.success
                 }">

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
                <h3 class="text-3xl sm:text-4xl font-black mt-2" x-text="result?.student_name || 'Kartu Tidak Sah'"></h3>
                <p class="text-sm mt-1 opacity-75 font-mono" x-show="result?.nis" x-text="'NIS: ' + result?.nis"></p>

                <!-- Pesan -->
                <div class="mt-6 p-4 rounded-xl bg-black/20 text-base font-medium" x-text="result?.message"></div>

                <!-- Waktu Server -->
                <div class="mt-4 text-xs font-mono opacity-60" x-show="result?.time">
                    Waktu Server: <span x-text="result?.time"></span> WIB
                </div>
            </div>
        </div>

    </main>

    <!-- Pratinjau Kamera (pojok kanan bawah) — disembunyikan otomatis jika kamera tidak tersedia -->
    <div x-ref="cameraBox" x-cloak class="hidden fixed bottom-24 right-6 z-40 select-none">
        <div class="rounded-2xl overflow-hidden border-2 border-slate-700 shadow-2xl bg-slate-800 relative">
            <video x-ref="cameraPreview" autoplay muted playsinline class="w-44 sm:w-56 aspect-video object-cover block"></video>
            <div class="absolute top-1.5 left-2 px-2 py-0.5 rounded-md bg-black/60 text-[10px] font-bold text-slate-200 tracking-wide">
                Kamera Bukti Absen
            </div>
            <div class="absolute bottom-1.5 right-2 w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></div>
        </div>
    </div>

    <!-- Footer Kiosk & Simulator Chips untuk Penguji -->
    <footer class="p-6 border-t border-slate-800 bg-slate-900/90 text-center">
        <p class="text-xs text-slate-400 font-medium mb-3">
            Simulasi Cepat untuk Evaluator Demo (Klik untuk otomatis tap):
        </p>
        <div class="flex flex-wrap justify-center gap-2 max-w-3xl mx-auto">
            <button type="button" @click="testCard('CARD-TKJ-009')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-mono text-emerald-300 border border-slate-700">
                CARD-TKJ-009 (Hendra - Belum Absen)
            </button>
            <button type="button" @click="testCard('CARD-TKJ-010')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-mono text-emerald-300 border border-slate-700">
                CARD-TKJ-010 (Intan - Belum Absen)
            </button>
            <button type="button" @click="testCard('CARD-TKJ-001')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-mono text-blue-300 border border-slate-700">
                CARD-TKJ-001 (Raka - Tap Pulang)
            </button>
            <button type="button" @click="testCard('CARD-SALAH-999')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-mono text-red-300 border border-slate-700">
                CARD-SALAH-999 (Kartu Palsu)
            </button>
        </div>
        <div class="mt-4 text-[11px] text-slate-500">
            Kiosk ID: kiosk-{{ $schoolClass->slug }} • Jam Masuk: {{ substr($schoolClass->entry_time, 0, 5) }} • Jam Pulang: {{ substr($schoolClass->exit_time, 0, 5) }}
        </div>
    </footer>

    <script>
        function kioskApp() {
            return {
                cardUid: '',
                result: null,
                currentTime: '',
                currentDate: '',
                resetTimer: null,
                cameraStream: null,

                initKiosk() {
                    this.updateClock();
                    setInterval(() => this.updateClock(), 1000);
                    this.$nextTick(() => this.focusInput());
                    this.startCamera();
                    window.addEventListener('beforeunload', () => this.stopCamera());
                },

                // Matikan kamera saat halaman ditutup agar lampu indikator kamera tidak menyala terus
                stopCamera() {
                    if (this.cameraStream) {
                        this.cameraStream.getTracks().forEach(track => track.stop());
                        this.cameraStream = null;
                    }
                },

                // Nyalakan pratinjau kamera (pojok). Kegagalan APA PUN (kamera tidak ada,
                // izin ditolak, browser tanpa dukungan) hanya menyembunyikan pratinjau —
                // alur tap kiosk tetap berjalan normal tanpa kamera.
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
                        // Kamera tidak ada / izin ditolak → kiosk tetap berfungsi normal
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

                testCard(uid) {
                    this.cardUid = uid;
                    this.submitTap();
                },

                // Ambil 1 frame dari kamera sebagai foto bukti absen (PRD F1).
                // Tanpa kamera / kamera belum siap → kembalikan null; tap tetap jalan.
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
                        const res = await fetch("{{ route('kiosk.tap', $schoolClass->slug) }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ card_uid: uid, photo: foto })
                        });

                        const data = await res.json();
                        this.showResult(data);
                    } catch (e) {
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
                    this.resetTimer = setTimeout(() => {
                        this.result = null;
                        this.$nextTick(() => this.focusInput());
                    }, 4000);
                }
            }
        }
    </script>
</body>
</html>
