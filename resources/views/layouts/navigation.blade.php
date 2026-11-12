<nav x-data="{ open: false, placeholderNotice: '' }" class="bg-white border-b border-gray-200 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 gap-4">
            <div class="flex items-center space-x-6 min-w-0 flex-1">
                <!-- Logo & Brand -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 text-indigo-700 font-bold text-lg tracking-tight hover:text-indigo-800 transition">
                        <div class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-black text-xl shadow-sm">
                            E
                        </div>
                        <span class="hidden 2xl:inline whitespace-nowrap">E-PRESKNBI — SMK KARYA NUGRAHA</span>
                        <span class="2xl:hidden whitespace-nowrap">E-PRESKNBI</span>
                    </a>
                </div>

                <div class="hidden lg:flex lg:space-x-2 lg:items-center min-w-0 flex-1 overflow-x-auto whitespace-nowrap [&>*]:shrink-0 [scrollbar-width:thin] [scrollbar-color:var(--color-gray-300)_transparent]">
                    @php
                        $routeName = (string) (request()->route()?->getName() ?? '');
                        $aktif = static fn (string $pattern): bool => \Illuminate\Support\Str::is($pattern, $routeName);
                    @endphp
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('dashboard') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                       @if($aktif('dashboard')) aria-current="page" @endif>
                        Beranda
                    </a>

                    @if(Auth::user()->role === 'admin')
                        <a href="{{ route('admin.siswa.index') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.siswa.*') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                           @if($aktif('admin.siswa.*')) aria-current="page" @endif>
                            Data Siswa
                        </a>
                        <a href="{{ route('admin.kelas.index') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.kelas.*') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                           @if($aktif('admin.kelas.*')) aria-current="page" @endif>
                            Data Kelas
                        </a>
                        <a href="{{ route('admin.rekapitulasi') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.rekap*') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                           @if($aktif('admin.rekap*')) aria-current="page" @endif>
                            Rekapitulasi
                        </a>
                        <a href="{{ route('admin.kehadiran.manual') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.kehadiran-manual.*') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                           @if($aktif('admin.kehadiran-manual.*')) aria-current="page" @endif>
                            Izin/Sakit
                        </a>
                        <a href="{{ route('admin.daftar-hadir') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('admin.daftar-hadir.*') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                           @if($aktif('admin.daftar-hadir.*')) aria-current="page" @endif>
                            Daftar Hadir
                        </a>
                    @else
                        <button type="button" @click="placeholderNotice = 'Fitur Data Siswa segera hadir pada tahap berikutnya.'" class="inline-flex items-center px-3 py-1.5 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 transition">
                            Data Siswa
                        </button>
                    @endif

                    <button type="button" @click="placeholderNotice = 'Fitur Jadwal Kelas segera hadir pada tahap berikutnya.'" class="inline-flex items-center px-3 py-1.5 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 transition">
                        Jadwal Kelas
                    </button>

                    <a href="{{ route('kiosk.gerbang') }}" target="_blank"
                       class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('kiosk.gerbang') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                       @if($aktif('kiosk.gerbang')) aria-current="page" @endif>
                        Kiosk Absen
                    </a>

                    @if(Auth::user()->role === 'guru')
                        <a href="{{ route('guru.rekap') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('guru.rekap') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                           @if($aktif('guru.rekap')) aria-current="page" @endif>
                            Rekap Harian
                        </a>
                    @elseif(Auth::user()->role === 'siswa')
                        <a href="{{ route('siswa.riwayat') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('siswa.riwayat') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                           @if($aktif('siswa.riwayat')) aria-current="page" @endif>
                            Riwayat Absensi
                        </a>
                        <a href="{{ route('siswa.wajah') }}"
                           class="inline-flex items-center px-3 py-1.5 border-b-2 text-sm transition {{ $aktif('siswa.wajah') ? 'border-indigo-600 text-indigo-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium' }}"
                           @if($aktif('siswa.wajah')) aria-current="page" @endif>
                            Pendaftaran Wajah
                        </a>
                    @else
                        <button type="button" @click="placeholderNotice = 'Fitur Laporan segera hadir pada tahap berikutnya.'" class="inline-flex items-center px-3 py-1.5 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 transition">
                            Laporan
                        </button>
                    @endif
                </div>
            </div>

            <!-- Settings & User Profile -->
            <div class="hidden lg:flex lg:items-center lg:space-x-4 shrink-0">
                <!-- User Info & Role Badge -->
                <div class="text-right">
                    <div class="text-sm font-semibold text-gray-800">{{ Auth::user()->name }}</div>
                    <div>
                        @if(Auth::user()->role === 'admin')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800">
                                Admin
                            </span>
                        @elseif(Auth::user()->role === 'guru')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">
                                Guru
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">
                                Siswa
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Logout Button -->
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-red-50 text-gray-700 hover:text-red-700 text-sm font-medium rounded-md border border-gray-300 hover:border-red-300 transition shadow-sm" title="Keluar dari akun">
                        <svg class="w-4 h-4 me-1.5 text-gray-500 hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>

            <!-- Hamburger Button (Mobile) -->
            <div class="-me-2 flex items-center lg:hidden shrink-0">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification for Placeholder Features -->
    <div x-show="placeholderNotice" x-transition x-cloak class="bg-indigo-50 border-t border-b border-indigo-200 px-4 py-2 text-sm text-indigo-800 flex justify-between items-center max-w-7xl mx-auto">
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <span x-text="placeholderNotice"></span>
        </div>
        <button @click="placeholderNotice = ''" class="text-indigo-600 hover:text-indigo-900 font-bold px-2 py-0.5 rounded text-xs hover:bg-indigo-100">Tutup</button>
    </div>

    <!-- Responsive Navigation Menu (Mobile) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden border-t border-gray-200 bg-gray-50">
        <!-- User Info -->
        <div class="px-4 py-3 border-b border-gray-200 bg-white">
            <div class="font-semibold text-base text-gray-800">{{ Auth::user()->name }}</div>
            <div class="font-medium text-xs text-gray-500 mb-1">{{ Auth::user()->email }}</div>
            <div>
                @if(Auth::user()->role === 'admin')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800">Admin</span>
                @elseif(Auth::user()->role === 'guru')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">Guru</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">Siswa</span>
                @endif
            </div>
        </div>

        <div class="pt-2 pb-3 space-y-1">
            <a href="{{ route('dashboard') }}" onclick="open = false"
               class="block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('dashboard') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
               @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                Beranda
            </a>
            @if(Auth::user()->role === 'admin')
                <a href="{{ route('admin.siswa.index') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.siswa.*') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
                   @if(request()->routeIs('admin.siswa.*')) aria-current="page" @endif>
                    Data Siswa
                </a>
                <a href="{{ route('admin.kelas.index') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.kelas.*') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
                   @if(request()->routeIs('admin.kelas.*')) aria-current="page" @endif>
                    Data Kelas
                </a>
                <a href="{{ route('admin.rekapitulasi') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.rekap*') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
                   @if(request()->routeIs('admin.rekap*')) aria-current="page" @endif>
                    Rekapitulasi
                </a>
                <a href="{{ route('admin.kehadiran.manual') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.kehadiran-manual.*') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
                   @if(request()->routeIs('admin.kehadiran-manual.*')) aria-current="page" @endif>
                    Izin/Sakit
                </a>
                <a href="{{ route('admin.daftar-hadir') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('admin.daftar-hadir.*') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
                   @if(request()->routeIs('admin.daftar-hadir.*')) aria-current="page" @endif>
                    Daftar Hadir
                </a>
            @else
                <button type="button" @click="placeholderNotice = 'Fitur Data Siswa segera hadir pada tahap berikutnya.'; open = false" class="w-full text-left block ps-3 pe-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100">
                    Data Siswa
                </button>
            @endif
            <button type="button" @click="placeholderNotice = 'Fitur Jadwal Kelas segera hadir pada tahap berikutnya.'; open = false" class="w-full text-left block ps-3 pe-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100">
                Jadwal Kelas
            </button>
            <a href="{{ route('kiosk.gerbang') }}" target="_blank" onclick="open = false"
               class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('kiosk.gerbang') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
               @if(request()->routeIs('kiosk.gerbang')) aria-current="page" @endif>
                Kiosk Absen
            </a>
            @if(Auth::user()->role === 'guru')
                <a href="{{ route('guru.rekap') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('guru.rekap') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
                   @if(request()->routeIs('guru.rekap')) aria-current="page" @endif>
                    Rekap Harian
                </a>
            @elseif(Auth::user()->role === 'siswa')
                <a href="{{ route('siswa.riwayat') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('siswa.riwayat') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
                   @if(request()->routeIs('siswa.riwayat')) aria-current="page" @endif>
                    Riwayat Absensi
                </a>
                <a href="{{ route('siswa.wajah') }}" onclick="open = false"
                   class="w-full text-left block ps-3 pe-4 py-2 border-l-4 text-base font-medium {{ request()->routeIs('siswa.wajah') ? 'border-indigo-600 text-indigo-700 font-semibold bg-indigo-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}"
                   @if(request()->routeIs('siswa.wajah')) aria-current="page" @endif>
                    Pendaftaran Wajah
                </a>
            @else
                <button type="button" @click="placeholderNotice = 'Fitur Laporan segera hadir pada tahap berikutnya.'; open = false" class="w-full text-left block ps-3 pe-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100">
                    Laporan
                </button>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-2 pb-3 border-t border-gray-200">
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

