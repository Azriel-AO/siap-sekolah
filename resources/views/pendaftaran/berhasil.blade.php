<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pendaftaran Berhasil - E-PRESKNBI</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-sekolah.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-sekolah.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <script>
        (function () {
            const t = localStorage.getItem('epresknbi-theme');
            if (t === 'dark' || (t === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-emerald-50 dark:bg-slate-900 min-h-screen flex items-center justify-center font-sans antialiased transition-colors duration-200">
    <div class="fixed top-4 right-4 z-10">
        <x-theme-toggle :compact="true" />
    </div>
    <main class="max-w-md w-full mx-4 bg-white dark:bg-slate-800 rounded-3xl border border-emerald-200 dark:border-emerald-800/60 shadow-lg p-8 text-center">
        <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1 class="text-2xl font-extrabold text-emerald-700 dark:text-emerald-400">Pendaftaran Berhasil</h1>
        <p class="mt-3 text-lg text-gray-800 dark:text-slate-200">Kartu aktif. Selamat datang,</p>
        <p class="text-2xl font-black text-gray-900 dark:text-slate-100 mt-1">{{ $nama }}</p>
        <p class="text-sm font-semibold text-gray-500 dark:text-slate-400 mt-1">Kelas {{ $kelas }}</p>
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-800/60 text-xs text-emerald-800 dark:text-emerald-200 leading-relaxed">
            Foto wajah dan sidik jari Anda sudah tersimpan sebagai data referensi absensi.
            Kartu pelajar Anda sudah aktif untuk Absen Gerbang.
        </div>
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ Auth::check() && Auth::user()->role === 'admin' ? route('admin.siswa.index') : route('dashboard') }}"
               class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm transition">
                {{ Auth::check() && Auth::user()->role === 'admin' ? 'Ke Daftar Siswa' : 'Ke Dashboard' }}
            </a>
            <a href="{{ route('pendaftaran.index') }}"
               class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 text-sm font-semibold transition">
                Daftar Siswa Lain
            </a>
        </div>
    </main>
</body>
</html>
