<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pendaftaran Siswa Baru - E-PRESKNBI</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-sekolah.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-sekolah.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-gray-900 font-sans antialiased min-h-screen" x-data="pendaftaranApp()" x-init="init()">

    <header class="bg-white border-b border-gray-200">
        <div class="max-w-2xl mx-auto px-4 py-4 flex items-center space-x-3">
            <div class="bg-white rounded-lg p-1 border border-gray-100 shadow-sm flex items-center justify-center">
                <img src="{{ asset('images/logo-sekolah.png') }}" alt="Logo SMK Karya Nugraha" class="h-9 w-auto object-contain">
            </div>
            <div>
                <h1 class="text-base sm:text-lg font-extrabold text-gray-900 leading-tight">Pendaftaran Siswa Baru</h1>
                <p class="text-[11px] sm:text-xs text-gray-500">E-PRESKNBI - SMK KARYA NUGRAHA</p>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6">

        {{-- Pendaftaran SELALU terbuka dalam pengawasan guru piket --}}

        {{-- Daftar error dari server --}}
            <div x-show="message" x-cloak class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold" x-text="message"></p>
                <ul x-show="errorList.length" class="list-disc list-inside mt-1">
                    <template x-for="e in errorList" :key="e"><li x-text="e"></li></template>
                </ul>
            </div>

            <form @submit.prevent="simpan()" class="space-y-5">

                @if(isDebugMode())
                    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-amber-400 text-slate-950 text-[10px] font-black tracking-widest uppercase">DEBUG</span>
                                <span class="text-xs font-semibold text-amber-900">Mode Pengembang Granular: lewati perangkat tertentu tanpa menimpa data asli perangkat lain.</span>
                            </div>
                            <button type="button" @click="isiDummyBiometrik()"
                                    class="inline-flex items-center justify-center px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-extrabold shadow-sm transition shrink-0">
                                Lewati Biometrik (Pakai Data Dummy)
                            </button>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1 border-t border-amber-200/80">
                            <button type="button" @click="isiDataDiriContoh()"
                                    class="px-2.5 py-1.5 rounded-lg bg-white border border-amber-300 hover:bg-amber-100 text-amber-900 text-[11px] font-bold transition text-center">
                                + Isi Data Diri Kosong
                            </button>
                            <button type="button" @click="bypassKameraGranular()"
                                    class="px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition text-center"
                                    :class="debugBypass.camera ? 'bg-amber-500 border-amber-600 text-slate-950' : 'bg-white border-amber-300 hover:bg-amber-100 text-amber-900'">
                                Bypass Kamera (Slot Kosong)
                            </button>
                            <button type="button" @click="bypassSidikJariGranular()"
                                    class="px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition text-center"
                                    :class="debugBypass.fingerprint ? 'bg-amber-500 border-amber-600 text-slate-950' : 'bg-white border-amber-300 hover:bg-amber-100 text-amber-900'">
                                Bypass Sidik Jari
                            </button>
                            <button type="button" @click="bypassRfidGranular()"
                                    class="px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition text-center"
                                    :class="debugBypass.rfid ? 'bg-amber-500 border-amber-600 text-slate-950' : 'bg-white border-amber-300 hover:bg-amber-100 text-amber-900'">
                                Bypass Kartu RFID
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Langkah 1: Data Diri --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-black flex items-center justify-center">1</span>
                        <h2 class="text-base font-bold text-gray-900">Data Diri</h2>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="form.name" required maxlength="100" placeholder="Masukkan nama lengkap sesuai ijazah"
                                   class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Jenis Kelamin <span class="text-rose-500">*</span></label>
                            <div class="flex gap-3">
                                <label class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border cursor-pointer transition"
                                       :class="form.gender === 'L' ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-gray-300 text-gray-600'">
                                    <input type="radio" name="gender" value="L" x-model="form.gender" class="sr-only"> L - Laki-laki
                                </label>
                                <label class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border cursor-pointer transition"
                                       :class="form.gender === 'P' ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-gray-300 text-gray-600'">
                                    <input type="radio" name="gender" value="P" x-model="form.gender" class="sr-only"> P - Perempuan
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Kelas <span class="text-rose-500">*</span></label>
                            <select x-model="form.school_class_id" required class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">- Pilih kelas Anda -</option>
                                @foreach($classes as $kelas)
                                    <option value="{{ $kelas->id }}">{{ $kelas->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">NISN <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="form.nisn" required inputmode="numeric" maxlength="20" placeholder="Masukkan 10 digit angka NISN"
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
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6" data-face-autocapture>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="flex items-center space-x-3">
                            <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-black flex items-center justify-center">2</span>
                            <h2 class="text-base font-bold text-gray-900">Foto Wajah (3 foto)</h2>
                        </div>
                        <span x-show="form.setuju && !kameraGagal" x-cloak
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                              :class="manualMode ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'"
                              x-text="manualMode ? 'Mode Manual (Fallback)' : 'Auto-Capture + Liveness'"></span>
                    </div>
                    <p class="text-[11px] text-gray-400 ml-10 mb-3">Kamera menyala setelah Anda mencentang persetujuan di atas. Ikuti panduan gerakan wajah agar 3 foto diambil otomatis.</p>

                    {{-- Indikator 4 langkah otomatis: Hadap Lurus -> Tengok Kanan -> Tengok Kiri -> Kedip --}}
                    <div x-show="form.setuju && !kameraGagal && !manualMode" x-cloak class="mb-3 rounded-xl border border-indigo-100 bg-indigo-50/70 p-3">
                        <div class="grid grid-cols-4 gap-1.5 text-[11px] font-bold text-center">
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
                        <div class="mt-2 flex flex-col items-center gap-1 text-center">
                            <p class="text-xs font-bold text-indigo-900" x-text="autoHint"></p>
                            <span x-show="stabilityText" x-cloak
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-extrabold transition-colors duration-200"
                                  :class="stabilityReady ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                  x-text="stabilityText"></span>
                        </div>
                    </div>

                    {{-- Banner peringatan bila timeout 10 detik per step tercapai --}}
                    <div x-show="form.setuju && !kameraGagal && !manualMode && stepTimedOut" x-cloak
                         class="mb-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <p class="font-extrabold">Wajah tidak terdeteksi dengan baik</p>
                            <p class="text-[11px] text-amber-800 mt-0.5">Pastikan pencahayaan cukup dan wajah berada di dalam kotak panduan, atau beralih ke mode manual.</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" @click="cobaLagiStep()"
                                    class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm transition">
                                Coba lagi
                            </button>
                            <button type="button" @click="aktifManualFallback()"
                                    class="px-3 py-1.5 rounded-lg bg-white border border-amber-400 hover:bg-amber-100 text-amber-900 text-xs font-bold transition">
                                Mode Manual
                            </button>
                        </div>
                    </div>

                    <div x-show="form.setuju && kameraGagal" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 mb-3">
                        Kamera tidak dapat dipakai (perangkat tidak ada / izin ditolak / browser tidak mendukung). Izinkan kamera lewat ikon gembok di bilah alamat browser, lalu muat ulang halaman ini.
                    </div>

                    <div x-show="form.setuju" class="relative rounded-xl overflow-hidden bg-gray-900 border border-gray-300" style="aspect-ratio: 16/9;">
                        <video x-ref="cameraPreview" autoplay muted playsinline class="w-full h-full object-cover relative z-0"></video>

                        {{-- Overlay panduan posisi wajah (disamakan dengan kiosk gerbang) --}}
                        <div data-face-position-guide
                             aria-hidden="true"
                             class="pointer-events-none absolute inset-0 z-10 overflow-hidden">
                            <div class="face-guide-overlay absolute transition-colors duration-200"
                                 :class="faceGuideState === 'inside'
                                     ? 'border-solid border-emerald-400'
                                     : (faceGuideState === 'outside'
                                         ? 'border-dashed border-amber-400'
                                         : 'border-dashed border-white/70')"
                                 style="top: 50%; left: 50%; transform: translate(-50%, -50%); height: min(64%, 280px); aspect-ratio: 3 / 4; border-width: 3px; border-radius: 50%; box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.45);">
                            </div>
                            <div class="absolute left-1/2 -translate-x-1/2 px-3.5 py-1.5 rounded-2xl text-[11px] sm:text-xs font-semibold tracking-wide max-w-[92%] sm:max-w-md text-center leading-snug backdrop-blur-sm transition-colors duration-200 border"
                                 :class="faceGuideState === 'outside'
                                     ? 'bg-amber-950/85 border-amber-400/60 text-amber-300'
                                     : (faceGuideState === 'inside'
                                         ? 'bg-emerald-950/85 border-emerald-400/60 text-emerald-300'
                                         : 'bg-slate-950/75 border-white/20 text-slate-100')"
                                 style="top: calc(50% + min(32%, 140px) + 8px);">
                                <span x-text="faceGuideState === 'outside' ? 'Geser wajah ke tengah' : (faceGuideState === 'inside' ? 'Wajah sesuai, ikuti instruksi pose' : 'Posisikan wajah di dalam kotak')">Posisikan wajah di dalam kotak</span>
                            </div>
                        </div>

                        <canvas data-face-overlay aria-hidden="true" class="pointer-events-none absolute inset-0 z-20 h-full w-full"></canvas>

                        <div x-show="!kameraGagal && !manualMode" x-cloak class="pointer-events-none absolute inset-x-0 top-2 z-30 flex flex-col items-center gap-1 px-3">
                            <p class="rounded-full bg-slate-900/80 px-3.5 py-1 text-xs font-extrabold text-emerald-300 shadow" x-text="autoHint"></p>
                            <span x-show="stabilityText || faceInGuide" x-cloak
                                  class="rounded-full px-3 py-0.5 text-[11px] font-black text-white shadow transition-colors duration-200"
                                  :class="stabilityReady ? 'bg-emerald-600/95' : 'bg-amber-500/95'"
                                  x-text="stabilityText || (faceInGuide ? 'Bagus, tahan posisi...' : '')"></span>
                        </div>
                        <div x-show="kameraGagal" x-cloak class="pointer-events-none absolute inset-x-0 top-2 z-30 flex justify-center px-3">
                            <p class="rounded-lg bg-black/70 px-3 py-1 text-[11px] font-bold text-amber-300">Kamera tidak tersedia: panduan framing tetap tampil</p>
                        </div>
                        <div x-show="countdown !== null" x-cloak class="absolute inset-0 z-30 flex items-center justify-center bg-black/40">
                            <div class="text-7xl font-black text-white drop-shadow-lg" x-text="countdown"></div>
                        </div>
                    </div>

                    <p class="mt-2 text-center text-xs font-medium text-gray-600">
                        Pastikan cahaya cukup dan wajah menghadap kamera.
                    </p>

                    <div class="grid grid-cols-3 gap-3 mt-3">
                        <template x-for="(foto, i) in photos" :key="i">
                            <div class="rounded-xl border-2 overflow-hidden" :class="foto ? 'border-emerald-400' : 'border-dashed border-gray-300 bg-gray-50'">
                                <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center overflow-hidden">
                                    <img x-show="foto" :src="foto" class="w-full h-full object-cover" alt="Foto wajah">
                                    <span x-show="!foto" class="text-2xl font-black text-gray-300" x-text="i + 1"></span>
                                </div>
                                <div class="p-1.5 flex items-center justify-between bg-white border-t border-gray-100">
                                    <span class="text-[10px] font-bold text-gray-500" x-text="photoLabels[i] || ('Foto ' + (i + 1))"></span>
                                    <button type="button" x-show="foto" @click="ulangFoto(i)" class="text-[10px] font-bold text-rose-600 hover:text-rose-800 px-1.5 py-0.5 rounded">Ulangi</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" x-show="manualMode" @click="ambilFoto()"
                                    :disabled="!form.setuju || kameraGagal || countdown !== null || photos.every(p => p)"
                                    class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white text-xs font-semibold transition">
                                <span x-text="countdown !== null ? 'Bersiap… ' + countdown : 'Ambil Foto'"></span>
                            </button>
                            <button type="button" x-show="form.setuju && !kameraGagal && !manualMode && stepTimedOut" @click="cobaLagiStep()"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition">
                                Coba lagi
                            </button>
                            <button type="button" x-show="!manualMode && !photos.every(p => p)" @click="aktifManualFallback()"
                                    class="text-xs font-bold text-indigo-700 hover:text-indigo-900 underline">
                                Gunakan tombol ambil foto manual
                            </button>
                        </div>
                        <button type="button" x-show="form.setuju && !kameraGagal && (photos.some(p => p) || autoStep > 0 || stepTimedOut)" @click="ulangSemuaWajah()"
                                class="text-[11px] font-semibold text-rose-600 hover:text-rose-800 underline">
                            Ulangi panduan dari awal
                        </button>
                    </div>
                </section>

                    {{-- Langkah 3: Sidik Jari --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="flex items-center space-x-3">
                            <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-black flex items-center justify-center">3</span>
                            <h2 class="text-base font-bold text-gray-900">Sidik Jari</h2>
                        </div>
                        @if(isDebugMode())
                            <button type="button" @click="bypassSidikJariGranular()"
                                    class="inline-flex items-center px-2.5 py-1 rounded-lg border border-amber-400 bg-amber-50 hover:bg-amber-100 text-amber-900 text-[11px] font-bold transition">
                                DEBUG: Skip Sidik Jari
                            </button>
                        @endif
                    </div>
                    <p class="text-[11px] text-gray-400 ml-10 mb-4">Tekan tombol, lalu letakkan jari Anda pada sensor. Bisa dilewati bila sensor belum tersedia.</p>

                    <div class="flex items-center gap-3 flex-wrap">
                        <button type="button" @click="daftarSidikJari()" :disabled="enrollStatus === 'loading'"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 text-white text-xs font-semibold transition">
                            <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2m-6.5 1.5A6.5 6.5 0 0112 5a6.5 6.5 0 014.5 11M8 14.5A3.5 3.5 0 0012 17a3.5 3.5 0 003.5-3M5.5 12H3m18 0h-2.5"/></svg>
                            <span x-text="enrollStatus === 'loading' ? 'Membaca sidik jari…' : 'Daftar Sidik Jari'"></span>
                        </button>
                        <span x-show="enrollStatus === 'ok'" class="inline-flex items-center px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">✓ Sidik jari terdaftar <span x-show="form.fingerprint_id" class="ms-1 font-mono text-[10px]" x-text="'(' + form.fingerprint_id + ')'"></span></span>
                        <span x-show="enrollStatus === 'gagal'" class="text-xs text-rose-600 font-medium" x-text="enrollMessage"></span>
                    </div>
                </section>

                {{-- Langkah 4: Kartu Pelajar --}}
                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="flex items-center space-x-3">
                            <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-black flex items-center justify-center">4</span>
                            <h2 class="text-base font-bold text-gray-900">Kartu Pelajar</h2>
                        </div>
                        @if(isDebugMode())
                            <button type="button" @click="bypassRfidGranular()"
                                    class="inline-flex items-center px-2.5 py-1 rounded-lg border border-amber-400 bg-amber-50 hover:bg-amber-100 text-amber-900 text-[11px] font-bold transition">
                                DEBUG: Generate UID Kartu
                            </button>
                        @endif
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
                photoLabels: ['Foto 1 (Depan)', 'Foto 2 (Kanan)', 'Foto 3 (Kiri)'],
                kameraGagal: false,
                cameraStream: null,
                countdown: null,
                manualMode: false,
                autoStep: 0,
                autoHint: 'Langkah 1/4: Hadap lurus ke tengah kamera',
                faceDetected: false,
                faceInGuide: false,
                faceGuideState: 'idle',
                stabilityText: '',
                stabilityReady: false,
                stepTimedOut: false,
                autoSession: null,
                enrollStatus: 'idle', // idle | loading | ok | gagal
                enrollMessage: '',
                saving: false,
                message: '',
                errorList: [],
                debugBypass: {
                    camera: false,
                    fingerprint: false,
                    rfid: false,
                },

                init() {
                    window.addEventListener('beforeunload', () => this.stopCamera());
                    window.addEventListener('face-autocapture:ready', () => {
                        if (this.form.setuju && this.cameraStream && !this.manualMode && !this.autoSession) {
                            this.startAutoCapture();
                        }
                    });
                    window.addEventListener('face-autocapture:failed', () => {
                        this.aktifManualFallback();
                    });
                },

                onSetujuChange() {
                    if (this.form.setuju) this.startCamera(); else this.stopCamera();
                },

                stopCamera() {
                    if (this.autoSession) {
                        this.autoSession.stop();
                        this.autoSession = null;
                    }
                    if (this.cameraStream) {
                        this.cameraStream.getTracks().forEach(t => t.stop());
                        this.cameraStream = null;
                    }
                    this.faceDetected = false;
                    this.faceInGuide = false;
                    this.faceGuideState = 'idle';
                    this.stabilityReady = false;
                },

                async startCamera() {
                    this.kameraGagal = false;
                    this.stepTimedOut = false;
                    try {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            this.kameraGagal = true;
                            this.manualMode = true;
                            return;
                        }
                        this.cameraStream = await navigator.mediaDevices.getUserMedia({
                            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
                            audio: false,
                        });
                        this.$nextTick(() => {
                            if (this.$refs.cameraPreview) {
                                this.$refs.cameraPreview.srcObject = this.cameraStream;
                                const playPromise = this.$refs.cameraPreview.play?.();
                                if (playPromise && typeof playPromise.catch === 'function') {
                                    playPromise.catch(() => {});
                                }
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
                        onStateChange: ({ stepIndex, hint, faceDetected, inGuideBox, stabilityText, stabilityReady, timedOut }) => {
                            this.autoStep = stepIndex;
                            this.autoHint = hint;
                            this.faceDetected = Boolean(faceDetected);
                            this.faceInGuide = Boolean(inGuideBox);
                            this.faceGuideState = this.faceInGuide ? 'inside' : (this.faceDetected ? 'outside' : 'idle');
                            this.stabilityText = stabilityText || '';
                            this.stabilityReady = Boolean(stabilityReady);
                            this.stepTimedOut = Boolean(timedOut);
                        },
                        onPhotoCaptured: (slotIndex, jpegDataUrl) => {
                            if (slotIndex >= 0 && slotIndex < 3) {
                                this.photos.splice(slotIndex, 1, jpegDataUrl);
                            }
                        },
                        onComplete: () => {
                            this.autoStep = 4;
                            this.autoHint = 'Selesai! 3 foto wajah & kedipan mata terverifikasi.';
                            this.stabilityText = '';
                            this.stabilityReady = false;
                            this.stepTimedOut = false;
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

                cobaLagiStep() {
                    this.stepTimedOut = false;
                    this.stabilityText = '';
                    this.stabilityReady = false;
                    if (!this.cameraStream && this.form.setuju) {
                        this.startCamera();
                    } else if (this.autoSession && !this.manualMode) {
                        this.autoSession.retryCurrentStep();
                    } else if (!this.manualMode) {
                        this.startAutoCapture();
                    }
                },

                aktifManualFallback() {
                    if (this.autoSession) {
                        this.autoSession.stop();
                        this.autoSession = null;
                    }
                    this.stepTimedOut = false;
                    this.stabilityText = '';
                    this.stabilityReady = false;
                    this.faceGuideState = 'idle';
                    this.manualMode = true;
                },

                ulangSemuaWajah() {
                    this.photos = [null, null, null];
                    this.autoStep = 0;
                    this.autoHint = 'Langkah 1/4: Hadap lurus ke tengah kamera';
                    this.stabilityText = '';
                    this.stabilityReady = false;
                    this.stepTimedOut = false;
                    if (!this.cameraStream && this.form.setuju) {
                        this.startCamera();
                    } else if (this.autoSession) {
                        this.autoSession.resetToStep(0);
                    } else if (!this.manualMode) {
                        this.startAutoCapture();
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
                    if (slot !== -1) this.photos.splice(slot, 1, canvas.toDataURL('image/jpeg', 0.7));
                    if (this.photos.every(p => p)) this.stopCamera();
                },

                ulangFoto(i) {
                    this.photos.splice(i, 1, null);
                    this.autoStep = i;
                    this.stepTimedOut = false;
                    this.stabilityText = '';
                    this.stabilityReady = false;
                    this.faceGuideState = 'idle';
                    if (this.form.setuju && !this.cameraStream) {
                        this.startCamera();
                    } else if (this.autoSession && !this.manualMode) {
                        this.autoSession.resetToStep(i);
                    }
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
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                @if(isDebugMode())
                buatTokenDebug() {
                    return Date.now().toString(36).toUpperCase() + Math.random().toString(36).slice(2, 6).toUpperCase();
                },

                buatDummyFotoJpeg(label) {
                    const canvas = document.createElement('canvas');
                    canvas.width = 320;
                    canvas.height = 240;
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#0f172a';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.strokeStyle = '#38bdf8';
                    ctx.lineWidth = 4;
                    ctx.strokeRect(12, 12, canvas.width - 24, canvas.height - 24);
                    ctx.fillStyle = '#f8fafc';
                    ctx.font = 'bold 20px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(label, canvas.width / 2, canvas.height / 2 - 10);
                    ctx.fillStyle = '#94a3b8';
                    ctx.font = '12px monospace';
                    ctx.fillText('DEBUG DUMMY PHOTO', canvas.width / 2, canvas.height / 2 + 20);
                    return canvas.toDataURL('image/jpeg', 0.8);
                },

                isiDataDiriContoh() {
                    if (!this.form.name.trim()) {
                        this.form.name = 'Siswa Uji Debug ' + Math.floor(100 + Math.random() * 900);
                    }
                    if (!this.form.gender) {
                        this.form.gender = 'L';
                    }
                    if (!this.form.school_class_id) {
                        const firstClassId = @js($classes->first()?->id ?? '');
                        if (firstClassId) {
                            this.form.school_class_id = String(firstClassId);
                        }
                    }
                    if (!/^\d{6,20}$/.test(this.form.nisn)) {
                        this.form.nisn = '00' + String(Math.floor(10000000 + Math.random() * 89999999));
                    }
                    if (!this.form.setuju) {
                        this.form.setuju = true;
                        this.onSetujuChange();
                    }
                },

                bypassKameraGranular() {
                    this.debugBypass.camera = true;
                    this.stepTimedOut = false;
                    if (!this.form.setuju) {
                        this.form.setuju = true;
                    }
                    const labels = ['FOTO 1 (DEPAN)', 'FOTO 2 (KANAN)', 'FOTO 3 (KIRI)'];
                    // Hanya isi slot foto yang masih kosong (null), TIDAK menimpa foto asli yang sudah diambil
                    const nextPhotos = this.photos.map((existing, idx) => existing || this.buatDummyFotoJpeg(labels[idx]));
                    this.photos = nextPhotos;
                },

                bypassSidikJariGranular() {
                    this.debugBypass.fingerprint = true;
                    // Hanya isi fingerprint_id jika belum ada data asli dari sensor
                    if (!this.form.fingerprint_id || !this.form.fingerprint_id.trim()) {
                        this.form.fingerprint_id = 'DBG-FP-' + this.buatTokenDebug();
                    }
                    this.enrollStatus = 'ok';
                    this.enrollMessage = '';
                },

                bypassRfidGranular() {
                    this.debugBypass.rfid = true;
                    // Hanya isi card_uid jika belum ada kartu asli yang di-tap
                    if (!this.form.card_uid || !this.form.card_uid.trim()) {
                        this.form.card_uid = 'DBG-CARD-' + this.buatTokenDebug();
                    }
                },

                isiDummyBiometrik() {
                    // Isi hanya perangkat biometrik yang masih kosong tanpa menimpa data asli yang sudah ada
                    this.bypassKameraGranular();
                    this.bypassSidikJariGranular();
                    this.bypassRfidGranular();
                },
                @endif

                async simpan() {
                    this.message = '';
                    this.errorList = [];
                    @if(isDebugMode())
                    if (this.debugBypass.camera && this.photos.some(p => !p)) {
                        this.bypassKameraGranular();
                    }
                    if (this.debugBypass.rfid && !this.form.card_uid.trim()) {
                        this.bypassRfidGranular();
                    }
                    if (this.debugBypass.fingerprint && !this.form.fingerprint_id.trim()) {
                        this.bypassSidikJariGranular();
                    }
                    @endif
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
                            body: JSON.stringify({
                                ...this.form,
                                photos: this.photos,
                                @if(isDebugMode())
                                bypass_camera: this.debugBypass.camera,
                                bypass_fingerprint: this.debugBypass.fingerprint,
                                bypass_rfid: this.debugBypass.rfid,
                                @endif
                            }),
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            window.location.href = data.redirect || "{{ route('dashboard') }}";
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
