<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pendaftaran Berhasil — SIAP KNBI</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-emerald-50 min-h-screen flex items-center justify-center font-sans antialiased">
    <main class="max-w-md w-full mx-4 bg-white rounded-3xl border border-emerald-200 shadow-lg p-8 text-center">
        <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1 class="text-2xl font-extrabold text-emerald-700">Pendaftaran Berhasil</h1>
        <p class="mt-3 text-lg text-gray-800">Kartu aktif. Selamat datang,</p>
        <p class="text-2xl font-black text-gray-900 mt-1">{{ $nama }}</p>
        <p class="text-sm font-semibold text-gray-500 mt-1">Kelas {{ $kelas }}</p>
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-xs text-emerald-800 leading-relaxed">
            Foto wajah dan sidik jari Anda sudah tersimpan sebagai data referensi absensi.
            Kartu pelajar Anda sudah aktif untuk Absen Gerbang.
        </div>
    </main>
</body>
</html>
