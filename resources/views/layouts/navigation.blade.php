<nav x-data="{ open: false }" class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 shadow-sm transition-colors duration-200">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 gap-4">
            <div class="flex items-center space-x-6 min-w-0 flex-1">
                <!-- Logo & Brand -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 text-indigo-700 dark:text-indigo-400 font-bold text-lg tracking-tight hover:text-indigo-800 dark:hover:text-indigo-300 transition">
                        <div class="bg-white dark:bg-slate-100 rounded-lg p-1 shadow-sm border border-gray-100 dark:border-slate-700 flex items-center justify-center">
                            <img src="{{ asset('images/logo-sekolah.png') }}" alt="Logo SMK Karya Nugraha" class="h-9 w-auto object-contain">
                        </div>
                        <span class="hidden 2xl:inline whitespace-nowrap">E-PRESKNBI - SMK KARYA NUGRAHA</span>
                        <span class="2xl:hidden whitespace-nowrap">E-PRESKNBI</span>
                    </a>
                </div>

                @php
                    $routeName = (string) (request()->route()?->getName() ?? '');
                    $aktif = static fn (string $pattern): bool => \Illuminate\Support\Str::is($pattern, $routeName);
                    $aktifNavClass = 'border-indigo-600 dark:border-indigo-400 text-indigo-700 dark:text-indigo-300 font-semibold';
                    $idleNavClass = 'border-transparent text-gray-500 dark:text-slate-300 hover:text-gray-700 dark:hover:text-slate-100 hover:border-gray-300 dark:hover:border-slate-600 font-medium';
                @endphp

                <div class="hidden lg:flex lg:space-x-2 lg:items-center min-w-0 flex-1 overflow-x-auto whitespace-nowrap [&>*]:shrink-0 [scrollbar-width:thin] [scrollbar-color:var(--color-gray-300)_transparent]">
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('dashboard') ? $aktifNavClass : $idleNavClass }}"
                       @if($aktif('dashboard')) aria-current="page" @endif>
                        Beranda
                    </a>

                    @if(Auth::user()->role === 'admin')
                        <a href="{{ route('admin.siswa.index') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.siswa.*') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('admin.siswa.*')) aria-current="page" @endif>
                            Data Siswa
                        </a>
                        <a href="{{ route('admin.kelas.index') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.kelas.*') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('admin.kelas.*')) aria-current="page" @endif>
                            Data Kelas
                        </a>
                        <a href="{{ route('admin.rekapitulasi') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.rekap*') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('admin.rekap*')) aria-current="page" @endif>
                            Rekapitulasi
                        </a>
                        <a href="{{ route('admin.kehadiran.manual') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.kehadiran-manual.*') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('admin.kehadiran-manual.*')) aria-current="page" @endif>
                            Izin/Sakit
                        </a>
                        <a href="{{ route('admin.daftar-hadir') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.daftar-hadir.*') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('admin.daftar-hadir.*')) aria-current="page" @endif>
                            Daftar Hadir
                        </a>
                        <a href="{{ route('admin.status-sistem') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.status-sistem') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('admin.status-sistem')) aria-current="page" @endif>
                            Status Sistem
                        </a>
                    @endif

                    <a href="{{ route('kiosk.gerbang') }}" target="_blank"
                       class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('kiosk.gerbang') ? $aktifNavClass : $idleNavClass }}"
                       @if($aktif('kiosk.gerbang')) aria-current="page" @endif>
                        Kiosk Absen
                    </a>

                    @if(Auth::user()->role === 'guru')
                        <a href="{{ route('guru.rekap') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('guru.rekap') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('guru.rekap')) aria-current="page" @endif>
                            Rekap Harian
                        </a>
                    @elseif(Auth::user()->role === 'siswa')
                        <a href="{{ route('siswa.riwayat') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('siswa.riwayat') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('siswa.riwayat')) aria-current="page" @endif>
                            Riwayat Absensi
                        </a>
                        <a href="{{ route('siswa.wajah') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('siswa.wajah') ? $aktifNavClass : $idleNavClass }}"
                           @if($aktif('siswa.wajah')) aria-current="page" @endif>
                            Pendaftaran Wajah
                        </a>
                    @endif
                </div>
            </div>

            <!-- Settings & User Profile -->
            <div class="hidden lg:flex lg:items-center lg:space-x-4 shrink-0">
                <x-theme-toggle />

                @if(isDebugMode())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 dark:bg-amber-900/50 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-300 text-[11px] font-black tracking-wider uppercase" title="Developer Debug Mode Aktif">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        DEBUG
                    </span>
                @endif
                <!-- User Info & Role Badge -->
                <div class="text-right">
                    <div class="text-sm font-semibold text-gray-800 dark:text-slate-100">{{ Auth::user()->name }}</div>
                    <div>
                        @if(Auth::user()->role === 'admin')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300">
                                Admin
                            </span>
                        @elseif(Auth::user()->role === 'guru')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300">
                                Guru
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300">
                                Siswa
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Logout Button -->
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-slate-700 hover:bg-red-50 dark:hover:bg-red-900/40 text-gray-700 dark:text-slate-200 hover:text-red-700 dark:hover:text-red-300 text-sm font-medium rounded-md border border-gray-300 dark:border-slate-600 hover:border-red-300 dark:hover:border-red-700 transition shadow-sm" title="Keluar dari akun">
                        <svg class="w-4 h-4 me-1.5 text-gray-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>

            <!-- Hamburger Button (Mobile) -->
            <div class="-me-2 flex items-center lg:hidden shrink-0 gap-2">
                <x-theme-toggle :compact="true" />
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-slate-400 hover:text-gray-500 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-slate-700 focus:text-gray-500 dark:focus:text-slate-200 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (Mobile) -->
    <div x-show="open" x-cloak
         x-transition:enter="transition-all ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition-all ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="lg:hidden border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/95">
        <!-- User Info -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="font-semibold text-base text-gray-800 dark:text-slate-100">{{ Auth::user()->name }}</div>
            <div class="font-medium text-xs text-gray-500 dark:text-slate-400 mb-1">{{ Auth::user()->email }}</div>
            <div>
                @if(Auth::user()->role === 'admin')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300">Admin</span>
                @elseif(Auth::user()->role === 'guru')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300">Guru</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300">Siswa</span>
                @endif
            </div>
        </div>

        @php
            $mobileAktifClass = 'border-indigo-600 dark:border-indigo-400 text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50 dark:bg-indigo-950/60';
            $mobileIdleClass = 'border-transparent text-gray-600 dark:text-slate-300 hover:text-gray-800 dark:hover:text-slate-100 hover:bg-gray-100 dark:hover:bg-slate-700/70';
        @endphp

        <div class="pt-2 pb-3 space-y-1">
            <a href="{{ route('dashboard') }}" onclick="open = false"
               class="block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('dashboard') ? $mobileAktifClass : $mobileIdleClass }}"
               @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                Beranda
            </a>
            @if(Auth::user()->role === 'admin')
                <a href="{{ route('admin.siswa.index') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.siswa.*') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('admin.siswa.*')) aria-current="page" @endif>
                    Data Siswa
                </a>
                <a href="{{ route('admin.kelas.index') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.kelas.*') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('admin.kelas.*')) aria-current="page" @endif>
                    Data Kelas
                </a>
                <a href="{{ route('admin.rekapitulasi') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.rekap*') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('admin.rekap*')) aria-current="page" @endif>
                    Rekapitulasi
                </a>
                <a href="{{ route('admin.kehadiran.manual') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.kehadiran-manual.*') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('admin.kehadiran-manual.*')) aria-current="page" @endif>
                    Izin/Sakit
                </a>
                <a href="{{ route('admin.daftar-hadir') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.daftar-hadir.*') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('admin.daftar-hadir.*')) aria-current="page" @endif>
                    Daftar Hadir
                </a>
                <a href="{{ route('admin.status-sistem') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.status-sistem') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('admin.status-sistem')) aria-current="page" @endif>
                    Status Sistem
                </a>
            @endif
            <a href="{{ route('kiosk.gerbang') }}" target="_blank" onclick="open = false"
               class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('kiosk.gerbang') ? $mobileAktifClass : $mobileIdleClass }}"
               @if(request()->routeIs('kiosk.gerbang')) aria-current="page" @endif>
                Kiosk Absen
            </a>
            @if(Auth::user()->role === 'guru')
                <a href="{{ route('guru.rekap') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('guru.rekap') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('guru.rekap')) aria-current="page" @endif>
                    Rekap Harian
                </a>
            @elseif(Auth::user()->role === 'siswa')
                <a href="{{ route('siswa.riwayat') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('siswa.riwayat') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('siswa.riwayat')) aria-current="page" @endif>
                    Riwayat Absensi
                </a>
                <a href="{{ route('siswa.wajah') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('siswa.wajah') ? $mobileAktifClass : $mobileIdleClass }}"
                   @if(request()->routeIs('siswa.wajah')) aria-current="page" @endif>
                    Pendaftaran Wajah
                </a>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-2 pb-3 border-t border-gray-200 dark:border-slate-700">
            <div class="px-4">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-md shadow-sm text-sm">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

