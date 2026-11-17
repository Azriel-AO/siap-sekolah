<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">
                    E-PRESKNBI - SMK KARYA NUGRAHA
                </h1>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
                    Sistem Informasi Absensi Pelajar - SMK Karya Nugraha (KNBI)
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-sm text-gray-600 dark:text-slate-300">Peran aktif:</span>
                @if($user->role === 'admin')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                        🛡️ Administrator
                    </span>
                @elseif($user->role === 'guru')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        👨‍🏫 Guru / Wali Kelas
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        🎓 Siswa
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('status') || session('success'))
                <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm font-semibold text-emerald-800 dark:text-emerald-300 shadow-sm">
                    {{ session('status') ?? session('success') }}
                </div>
            @endif

            {{-- Banner pengingat pendaftaran wajah (khusus siswa yang belum terdaftar) --}}
            @if($siswaPerluDaftarWajah ?? false)
                <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
                    <div class="flex items-start sm:items-center space-x-3">
                        <svg class="w-6 h-6 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5 sm:mt-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <p class="text-sm sm:text-base font-semibold text-amber-800 dark:text-amber-300">
                            Wajah Anda belum terdaftar untuk absensi, daftar sekarang
                        </p>
                    </div>
                    <a href="{{ route('siswa.wajah') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold shadow-sm transition shrink-0">
                        Daftar Sekarang
                    </a>
                </div>
            @endif

            <!-- Kartu Sambutan & Ringkasan Peran -->
            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-slate-700 p-6 sm:p-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center space-x-2 text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-1">
                            <span>Status Sesi: Terhubung</span>
                        </div>
                        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-slate-100">
                            Selamat Datang, {{ $user->name }}!
                        </h2>
                        <p class="text-gray-600 dark:text-slate-300 mt-2 max-w-2xl leading-relaxed text-sm sm:text-base">
                            @if($user->role === 'admin')
                                Anda memiliki hak akses <strong>Administrator</strong>. Anda berwenang mengelola data sekolah, akun pengguna, jadwal kelas per jurusan, konfigurasi perangkat kiosk absensi, dan laporan kehadiran.
                            @elseif($user->role === 'guru')
                                Anda masuk sebagai <strong>Guru / Wali Kelas</strong>. Anda dapat memantau kehadiran siswa di kelas Anda, mengesahkan izin dan sakit siswa, serta meninjau rekap absensi harian.
                            @else
                                Anda masuk sebagai <strong>Siswa</strong>. Anda dapat melihat catatan kehadiran dan riwayat jam masuk serta jam pulang Anda sendiri.
                            @endif
                        </p>
                    </div>
                    <div class="shrink-0 bg-gray-50 dark:bg-slate-900 rounded-lg p-4 border border-gray-200 dark:border-slate-700 text-xs text-gray-600 dark:text-slate-300 space-y-1">
                        <div><strong>Akun:</strong> {{ $user->email }}</div>
                        <div><strong>Peran:</strong> {{ $roleLabel }}</div>
                        <div><strong>Server:</strong> Aktif (SQLite)</div>
                    </div>
                </div>
            </div>

            <!-- Panel Pendaftaran Siswa Baru (khusus admin) -->
            @if($user->role === 'admin')
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-slate-700 p-6 sm:p-8">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-extrabold text-gray-900 dark:text-slate-100">Pendaftaran Siswa Baru</h2>
                                <p class="text-gray-600 dark:text-slate-300 mt-1 max-w-2xl leading-relaxed text-sm">
                                    Buka terminal pendaftaran mandiri: isi data, foto wajah, sidik jari, dan tap kartu RFID.
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('pendaftaran.index') }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-sm transition shrink-0">
                            Buka Terminal Pendaftaran
                        </a>
                    </div>
                </div>
            @endif

            <!-- Ringkasan 7 Hari Terakhir (Mini Bar Chart + 4 Metrik Mingguan) -->
            <section class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">Ringkasan 7 Hari Terakhir</h3>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Total siswa hadir tepat waktu per hari dalam 7 hari terakhir dan rekap minggu ini.</p>
                    </div>
                    <a href="{{ route('admin.rekapitulasi') }}"
                       class="inline-flex items-center text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition shrink-0">
                        Lihat Detail →
                    </a>
                </div>

                {{-- Mini Bar Chart 7 Kolom (Warna Hijau) --}}
                <div class="rounded-xl border border-gray-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/70 p-4 sm:p-5">
                    <div class="grid grid-cols-7 gap-2 sm:gap-4 items-end h-36">
                        @foreach($chart7Hari as $bar)
                            <div class="flex flex-col items-center justify-end h-full gap-1.5" title="{{ $bar['day_label'] }}, {{ $bar['date_label'] }}: {{ $bar['hadir'] }} hadir">
                                <span class="text-[11px] font-bold {{ $bar['hadir'] > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-400 dark:text-slate-500' }}">
                                    {{ $bar['hadir'] }}
                                </span>
                                <div class="w-full max-w-[44px] flex-1 flex items-end justify-center">
                                    @if($bar['hadir'] > 0)
                                        <div class="w-full rounded-t-lg bg-emerald-500 hover:bg-emerald-600 transition-all shadow-sm"
                                             style="height: {{ $bar['height_percent'] }}%;"></div>
                                    @else
                                        <div class="w-full h-1.5 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                                    @endif
                                </div>
                                <div class="text-center leading-tight">
                                    <div class="text-[11px] font-bold text-gray-700 dark:text-slate-200">{{ $bar['day_label'] }}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-slate-500">{{ $bar['date_label'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- 4 Angka Ringkas Minggu Ini --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/70 bg-emerald-50/50 dark:bg-emerald-950/30 p-4">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Total Hadir Minggu Ini</div>
                        <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $weeklyTotals['hadir'] }}</div>
                    </div>
                    <div class="rounded-xl border border-amber-200 dark:border-amber-800/70 bg-amber-50/50 dark:bg-amber-950/30 p-4">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Total Terlambat</div>
                        <div class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ $weeklyTotals['terlambat'] }}</div>
                    </div>
                    <div class="rounded-xl border border-sky-200 dark:border-sky-800/70 bg-sky-50/50 dark:bg-sky-950/30 p-4">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-400">Total Izin/Sakit</div>
                        <div class="text-2xl sm:text-3xl font-black text-sky-600 dark:text-sky-400 mt-1">{{ $weeklyTotals['izin_sakit'] }}</div>
                    </div>
                    <div class="rounded-xl border border-rose-200 dark:border-rose-800/70 bg-rose-50/50 dark:bg-rose-950/30 p-4">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Total Alpa</div>
                        <div class="text-2xl sm:text-3xl font-black text-rose-600 dark:text-rose-400 mt-1">{{ $weeklyTotals['alpa'] }}</div>
                    </div>
                </div>
            </section>

            <!-- Menu Layanan Sekolah -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">
                        Menu Layanan Sekolah
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <!-- Kartu 1: Data Siswa -->
                    <a href="{{ route('admin.siswa.index') }}" class="text-left bg-white dark:bg-slate-800 p-6 rounded-xl border border-gray-200 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition group">
                        <div class="w-12 h-12 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-4 group-hover:scale-105 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">Data Siswa</h4>
                            <span class="text-xs bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium px-2 py-0.5 rounded">Aktif</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 leading-relaxed">
                            Master data siswa seluruh kelas, pencarian cepat, dan pengelolaan kartu RFID serta biometrik.
                        </p>
                    </a>

                    <!-- Kartu 2: Data Kelas -->
                    <a href="{{ route('admin.kelas.index') }}" class="text-left bg-white dark:bg-slate-800 p-6 rounded-xl border border-gray-200 dark:border-slate-700 hover:border-emerald-400 dark:hover:border-emerald-500 hover:shadow-md transition group">
                        <div class="w-12 h-12 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4 group-hover:scale-105 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition">Data Kelas</h4>
                            <span class="text-xs bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium px-2 py-0.5 rounded">Aktif</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 leading-relaxed">
                            Pengelolaan daftar kelas, wali kelas, serta pengaturan jam masuk dan pulang.
                        </p>
                    </a>

                    <!-- Kartu 3: Absen Gerbang -->
                    <a href="{{ route('kiosk.gerbang') }}" target="_blank" class="text-left bg-white dark:bg-slate-800 p-6 rounded-xl border border-gray-200 dark:border-slate-700 hover:border-amber-400 dark:hover:border-amber-500 hover:shadow-md transition group">
                        <div class="w-12 h-12 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-4 group-hover:scale-105 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition">Absen Gerbang</h4>
                            <span class="text-xs bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-medium px-2 py-0.5 rounded">Buka di Tab Baru</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 leading-relaxed">
                            Titik absen gerbang tunggal: semua kelas, lewat kartu RFID, wajah (Face ID), atau sidik jari.
                        </p>
                    </a>

                    <!-- Kartu 4: Rekapitulasi / Riwayat -->
                    @if($user->role === 'guru')
                        <a href="{{ route('guru.rekap') }}" class="text-left bg-white dark:bg-slate-800 p-6 rounded-xl border border-gray-200 dark:border-slate-700 hover:border-purple-400 dark:hover:border-purple-500 hover:shadow-md transition group">
                            <div class="w-12 h-12 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-4 group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-purple-600 dark:group-hover:text-purple-400 transition">Rekap Harian</h4>
                                <span class="text-xs bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium px-2 py-0.5 rounded">Aktif</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 leading-relaxed">
                                Pemantauan kehadiran siswa kelas binaan hari ini, diperbarui otomatis tiap 10 detik.
                            </p>
                        </a>
                    @elseif($user->role === 'siswa')
                        <a href="{{ route('siswa.riwayat') }}" class="text-left bg-white dark:bg-slate-800 p-6 rounded-xl border border-gray-200 dark:border-slate-700 hover:border-purple-400 dark:hover:border-purple-500 hover:shadow-md transition group">
                            <div class="w-12 h-12 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-4 group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-purple-600 dark:group-hover:text-purple-400 transition">Riwayat Absensi</h4>
                                <span class="text-xs bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium px-2 py-0.5 rounded">Aktif</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 leading-relaxed">
                                Catatan kehadiran pribadi Anda: jam masuk, jam pulang, dan status per hari.
                            </p>
                        </a>
                    @else
                        <a href="{{ route('admin.daftar-hadir') }}" class="text-left bg-white dark:bg-slate-800 p-6 rounded-xl border border-gray-200 dark:border-slate-700 hover:border-purple-400 dark:hover:border-purple-500 hover:shadow-md transition group">
                            <div class="w-12 h-12 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-4 group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-purple-600 dark:group-hover:text-purple-400 transition">Daftar Hadir</h4>
                                <span class="text-xs bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium px-2 py-0.5 rounded">Aktif</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 leading-relaxed">
                                Matriks daftar hadir bulanan per kelas dan unduh file Excel resmi wali kelas.
                            </p>
                        </a>
                    @endif

                    <!-- Kartu 5: Pendaftaran Wajah (khusus siswa) -->
                    @if($user->role === 'siswa')
                        <a href="{{ route('siswa.wajah') }}" class="text-left bg-white dark:bg-slate-800 p-6 rounded-xl border border-gray-200 dark:border-slate-700 hover:border-cyan-400 dark:hover:border-cyan-500 hover:shadow-md transition group">
                            <div class="w-12 h-12 rounded-lg bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 flex items-center justify-center mb-4 group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0M3 9.75V7.5A1.5 1.5 0 014.5 6h2M21 9.75V7.5A1.5 1.5 0 0019.5 6h-2M3 14.25V16.5A1.5 1.5 0 004.5 18h2M21 14.25V16.5a1.5 1.5 0 01-1.5 1.5h-2" />
                                </svg>
                            </div>
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-gray-900 dark:text-slate-100 group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition">Pendaftaran Wajah</h4>
                                <span class="text-xs bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium px-2 py-0.5 rounded">Aktif</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 leading-relaxed">
                                Daftarkan 3 foto wajah Anda sebagai data referensi absensi. Hanya untuk siswa yang login.
                            </p>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Info Bar Status Sistem -->
            <div class="bg-gray-50 dark:bg-slate-800/80 rounded-xl p-5 border border-gray-200 dark:border-slate-700 text-xs text-gray-500 dark:text-slate-400 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span><strong>Sistem Aktif:</strong> Layanan E-PRESKNBI &amp; Database SQLite Siap.</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span>SMK Karya Nugraha (KNBI)</span>
                    <span>•</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-600 dark:text-rose-400 hover:text-red-800 dark:hover:text-rose-300 font-semibold underline">
                            Keluar dari Sesi
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
