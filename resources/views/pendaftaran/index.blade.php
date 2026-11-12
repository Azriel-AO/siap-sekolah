<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pendaftaran Siswa Baru — E-PRESKNBI</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-gray-900 font-sans antialiased min-h-screen" x-data="pendaftaranApp()" x-init="init()">

    <header class="bg-white border-b border-gray-200">
        <div class="max-w-2xl mx-auto px-4 py-4 flex items-center space-x-3">
            <div class="w-11 h-11 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-xl">E</div>
            <div>
                <h1 class="text-base sm:text-lg font-extrabold text-gray-900 leading-tight">Pendaftaran Siswa Baru</h1>
                <p class="text-[11px] sm:text-xs text-gray-500">E-PRESKNBI — SMK KARYA NUGRAHA</p>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6">

        {{-- Pendaftaran SELALU terbuka — pengawasan guru piket --}}

        {{-- Daftar error dari server --}}
            <div x-show="message" x-cloak class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold" x-text="message"></p>
                <ul x-show="errorList.length" class="list-disc list-inside mt-1">
                    <template x-for="e in errorList" :key="e"><li x-text="e"></li></template>
                </ul>
            </div>

            <form @submit.prevent="simpan()" class="space-y-5">

                {{-- Langkah 1: Data Diri --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-black flex items-center justify-center">1</span>
                        <h2 class="text-base font-bold text-gray-900">Data Diri</h2>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="form.name" required maxlength="100" placeholder="Contoh: Intan Permata"
                                   class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Jenis Kelamin <span class="text-rose-500">*</span></label>
                            <div class="flex gap-3">
                                <label class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border cursor-pointer transition"
                                       :class="form.gender === 'L' ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-gray-300 text-gray-600'">
                                    <input type="radio" name="gender" value="L" x-model="form.gender" class="sr-only"> L — Laki-laki
                                </label>
                                <label class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border cursor-pointer transition"
                                       :class="form.gender === 'P' ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-gray-300 text-gray-600'">
                                    <input type="radio" name="gender" value="P" x-model="form.gender" class="sr-only"> P — Perempuan
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Kelas <span class="text-rose-500">*</span></label>
                            <select x-model="form.school_class_id" required class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Pilih kelas Anda —</option>
                                @foreach($classes as $kelas)
                                    <option value="{{ $kelas->id }}">{{ $kelas->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">NISN <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="form.nisn" required inputmode="numeric" maxlength="20" placeholder="Contoh: 0098765432"
                                   class="w-full rounded-lg border-gray-300 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="text-[11px] text-gray-400 mt-1">Nomor Induk Siswa Nasional Anda (hanya angka).</p>
                        </div>

                        <label class="flex items-start space-x-3 p-4 rounded-xl border cursor-pointer transition"
                               :class="form.setuju ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-50'">
                            <input type="checkbox" x-model="form.setuju" @change="onSetujuChange()"
                                   class="mt-0.5 h-5 w-5 shrink-0 cursor-pointer appearance-none rounded-md border-2 border-gray-500 bg-white text-indigo-600 checked:border-indigo-600 checked:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <span class="text-xs text-gray-700 leading-relaxed">Saya menyetujui data <strong>biometrik</strong> (foto wajah dan sidik jari) saya direkam dan dipakai <strong>hanya untuk keperluan absensi</strong> SMK KNBI.</span>
                        </label>
                    </div>
                </section>

                {{-- Langkah 2: Foto Wajah --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center space-x-3 mb-1">
                        <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-black flex items-center justify-center">2</span>
                        <h2 class="text-base font-bold text-gray-900">Foto Wajah (3 foto)</h2>
                    </div>
                    <p class="text-[11px] text-gray-400 ml-10 mb-4">Kamera menyala setelah Anda mencentang persetujuan di atas.</p>

                    <div x-show="form.setuju && kameraGagal" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 mb-3">
                        Kamera tidak dapat dipakai (perangkat tidak ada / izin ditolak / browser tidak mendukung). Izinkan kamera lewat ikon gembok di bilah alamat browser, lalu muat ulang halaman ini.
                    </div>

                    <div x-show="form.setuju" class="relative rounded-xl overflow-hidden bg-gray-900 border border-gray-300" style="aspect-ratio: 16/9;">
                        <video x-ref="cameraPreview" autoplay muted playsinline class="w-full h-full object-cover"></video>
                        <canvas data-face-overlay aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full"></canvas>
                        <div x-show="kameraGagal" x-cloak class="pointer-events-none absolute inset-x-0 top-2 flex justify-center px-3">
                            <p class="rounded-lg bg-black/70 px-3 py-1 text-[11px] font-bold text-amber-300">Kamera tidak tersedia — panduan framing tetap tampil</p>
                        </div>
                        <div x-show="countdown !== null" x-cloak class="absolute inset-0 flex items-center justify-center bg-black/40">
                            <div class="text-7xl font-black text-white drop-shadow-lg" x-text="countdown"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mt-3">
                        <template x-for="(foto, i) in photos" :key="i">
                            <div class="rounded-xl border-2 overflow-hidden" :class="foto ? 'border-emerald-400' : 'border-dashed border-gray-300 bg-gray-50'">
                                <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center overflow-hidden">
                                    <img x-show="foto" :src="foto" class="w-full h-full object-cover" alt="Foto wajah">
                                    <span x-show="!foto" class="text-2xl font-black text-gray-300" x-text="i + 1"></span>
                                </div>
                                <div class="p-1.5 flex items-center justify-between bg-white border-t border-gray-100">
                                    <span class="text-[10px] font-bold text-gray-500" x-text="'Foto ' + (i + 1)"></span>
                                    <button type="button" x-show="foto" @click="ulangFoto(i)" class="text-[10px] font-bold text-rose-600 hover:text-rose-800 px-1.5 py-0.5 rounded">Ulangi</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="ambilFoto()"
                            :disabled="!form.setuju || kameraGagal || countdown !== null || photos.every(p => p)"
                            class="mt-3 inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white text-xs font-semibold transition">
                        <span x-text="countdown !== null ? 'Bersiap… ' + countdown : 'Ambil Foto'"></span>
                    </button>
                </section>

                {{-- Langkah 3: Sidik Jari --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center space-x-3 mb-1">
                        <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-black flex items-center justify-center">3</span>
                        <h2 class="text-base font-bold text-gray-900">Sidik Jari</h2>
                    </div>
                    <p class="text-[11px] text-gray-400 ml-10 mb-4">Tekan tombol, lalu letakkan jari Anda pada sensor. Bisa dilewati bila sensor belum tersedia.</p>

                    <div class="flex items-center gap-3 flex-wrap">
                        <button type="button" @click="daftarSidikJari()" :disabled="enrollStatus === 'loading'"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white text-xs font-semibold transition">
                            <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2m-6.5 1.5A6.5 6.5 0 0112 5a6.5 6.5 0 014.5 11M8 14.5A3.5 3.5 0 0012 17a3.5 3.5 0 003.5-3M5.5 12H3m18 0h-2.5"/></svg>
                            <span x-text="enrollStatus === 'loading' ? 'Membaca sidik jari…' : 'Daftar Sidik Jari'"></span>
                        </button>
                        <span x-show="enrollStatus === 'ok'" class="inline-flex items-center px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">✓ Sidik jari terdaftar</span>
                        <span x-show="enrollStatus === 'gagal'" class="text-xs text-rose-600 font-medium" x-text="enrollMessage"></span>
                    </div>
                </section>

                {{-- Langkah 4: Kartu Pelajar --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center space-x-3 mb-1">
                        <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-black flex items-center justify-center">4</span>
                        <h2 class="text-base font-bold text-gray-900">Kartu Pelajar</h2>
                    </div>
                    <p class="text-[11px] text-gray-400 ml-10 mb-4">Ketuk kotak di bawah, lalu tempelkan kartu pelajar Anda ke scanner (atau ketik UID kartu lalu Enter).</p>

                    <input type="text" x-ref="cardInput" @keydown.enter.prevent="bacaKartu($event)" placeholder="Tempelkan kartu di sini…"
                           class="w-full rounded-lg border-gray-300 text-center text-lg font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500" autocomplete="off">
                    <div class="mt-2 flex items-center justify-between gap-3 flex-wrap">
                        <span x-show="!form.card_uid" class="text-xs text-gray-400">Belum ada kartu terbaca.</span>
                        <span x-show="form.card_uid" class="inline-flex items-center px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-mono font-bold">✓ Kartu terbaca: <span class="ms-1" x-text="form.card_uid"></span></span>
                        <button type="button" x-show="form.card_uid" @click="form.card_uid = ''" class="text-xs font-semibold text-gray-500 hover:text-gray-800 underline">Ketuk ulang</button>
                    </div>
                </section>

                <button type="submit" :disabled="!form.siapSimpan() || saving"
                        class="w-full py-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-lg font-extrabold shadow-sm transition">
                    <span x-text="saving ? 'Menyimpan…' : 'Selesaikan Pendaftaran'"></span>
                </button>
            </form>
    </main>

    <script>
        function pendaftaranApp() {
            return {
                form: {
                    name: '',
                    gender: '',
                    school_class_id: '',
                    nisn: '',
                    setuju: false,
                    card_uid: '',
                    fingerprint_id: '',
                    siapSimpan() {
                        return this.name.trim() !== '' && this.gender !== '' && this.school_class_id !== ''
                            && /^\d{6,20}$/.test(this.nisn) && this.setuju
                            && this.card_uid.trim() !== '';
                    },
                },
                photos: [null, null, null],
                kameraGagal: false,
                cameraStream: null,
                countdown: null,
                enrollStatus: 'idle', // idle | loading | ok | gagal
                enrollMessage: '',
                saving: false,
                message: '',
                errorList: [],

                init() {
                    window.addEventListener('beforeunload', () => this.stopCamera());
                },

                onSetujuChange() {
                    if (this.form.setuju) this.startCamera(); else this.stopCamera();
                },

                stopCamera() {
                    if (this.cameraStream) {
                        this.cameraStream.getTracks().forEach(t => t.stop());
                        this.cameraStream = null;
                    }
                },

                async startCamera() {
                    this.kameraGagal = false;
                    try {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            this.kameraGagal = true;
                            return;
                        }
                        this.cameraStream = await navigator.mediaDevices.getUserMedia({
                            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
                            audio: false,
                        });
                        this.$nextTick(() => {
                            if (this.$refs.cameraPreview) this.$refs.cameraPreview.srcObject = this.cameraStream;
                        });
                    } catch (e) {
                        this.cameraStream = null;
                        this.kameraGagal = true;
                    }
                },

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
                        this.tampilError('Kamera belum siap. Coba lagi.');
                        return;
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    const slot = this.photos.findIndex(p => !p);
                    if (slot !== -1) this.photos[slot] = canvas.toDataURL('image/jpeg', 0.7);
                    if (this.photos.every(p => p)) this.stopCamera();
                },

                ulangFoto(i) {
                    this.photos[i] = null;
                    if (this.form.setuju && !this.cameraStream) this.startCamera();
                },

                async daftarSidikJari() {
                    this.enrollStatus = 'loading';
                    this.enrollMessage = '';
                    try {
                        const res = await fetch("{{ route('pendaftaran.fingerprint.enrolar') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        const data = await res.json();
                        if (res.ok && data.ok) {
                            this.form.fingerprint_id = data.huellaId;
                            this.enrollStatus = 'ok';
                        } else {
                            this.enrollStatus = 'gagal';
                            this.enrollMessage = data.message ?? 'Gagal mendaftarkan sidik jari. Coba lagi.';
                        }
                    } catch (e) {
                        this.enrollStatus = 'gagal';
                        this.enrollMessage = 'Sensor tidak tersedia. Periksa koneksi atau coba lagi.';
                    }
                },

                bacaKartu(event) {
                    const uid = event.target.value.trim();
                    if (uid) {
                        this.form.card_uid = uid;
                        event.target.value = '';
                        event.target.focus();
                    }
                },

                tampilError(pesan, daftar = []) {
                    this.message = pesan;
                    this.errorList = daftar;
                },

                async simpan() {
                    this.message = '';
                    this.errorList = [];
                    if (this.photos.some(p => !p)) {
                        this.tampilError('Foto wajah wajib pas 3 foto.');
                        return;
                    }
                    this.saving = true;
                    try {
                        const res = await fetch("{{ route('pendaftaran.simpan') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ ...this.form, photos: this.photos }),
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            window.location.href = data.redirect;
                            return;
                        }
                        const errors = data.errors ? Object.values(data.errors).flat() : [data.message ?? 'Gagal menyimpan.'];
                        this.tampilError('Pendaftaran belum bisa disimpan:', errors);
                    } catch (e) {
                        this.tampilError('Gagal menghubungi server. Periksa koneksi Anda.');
                    } finally {
                        this.saving = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
