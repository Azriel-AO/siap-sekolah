<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Rekap Kehadiran — {{ $periodeLabel }}<span class="text-gray-400"> · {{ $schoolClass->name }}</span>
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    {{ $tanggalDisplay }} — Jurusan {{ $schoolClass->department }} — Jam Masuk: {{ substr($schoolClass->entry_time, 0, 5) }} — Jam Pulang: {{ substr($schoolClass->exit_time, 0, 5) }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                {{-- Filter: tanggal (anchor minggu/bulan) + kelas + periode --}}
                <select onchange="window.location.href='{{ route('guru.rekap') }}?periode=' + encodeURIComponent(this.value) + '&kelas={{ $schoolClass->slug }}&tanggal={{ $today }}'"
                        class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        aria-label="Pilih periode">
                    <option value="hari" @selected($periode === 'hari')>Hari Ini</option>
                    <option value="minggu" @selected($periode === 'minggu')>Minggu Ini</option>
                    <option value="bulan" @selected($periode === 'bulan')>Bulan Ini</option>
                </select>
                <form method="GET" action="{{ route('guru.rekap') }}" class="flex items-center">
                    <input type="hidden" name="kelas" value="{{ $schoolClass->slug }}">
                    <input type="hidden" name="periode" value="{{ $periode }}">
                    <input type="date" name="tanggal" value="{{ $today }}" onchange="this.form.submit()"
                           class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                           aria-label="Tanggal acuan periode">
                </form>
                {{-- Pemilih kelas: reload halaman dengan ?kelas=slug --}}
                <select onchange="window.location.href='{{ route('guru.rekap') }}?kelas=' + encodeURIComponent(this.value) + '&tanggal={{ $today }}&periode={{ $periode }}'"
                        class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        aria-label="Pilih kelas">
                    @foreach($classes as $kelas)
                        <option value="{{ $kelas->slug }}" @selected($kelas->slug === $schoolClass->slug)>{{ $kelas->name }}</option>
                    @endforeach
                </select>
                @if(Auth::user()->role === 'admin')
                    <a href="{{ route('admin.rekap.excel', ['tanggal' => $today, 'kelas' => $schoolClass->slug, 'periode' => $periode]) }}" class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-sm transition">
                        <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 7a4 4 0 014-4h10a4 4 0 014 4v10a4 4 0 01-4 4H7a4 4 0 01-4-4V7z" />
                        </svg>
                        Export Excel
                    </a>
                    <a href="{{ route('admin.rekap.pdf', ['tanggal' => $today, 'kelas' => $schoolClass->slug, 'periode' => $periode]) }}" class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-medium text-xs shadow-sm transition">
                        <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 7a4 4 0 014-4h10a4 4 0 014 4v10a4 4 0 01-4 4H7a4 4 0 01-4-4V7z" />
                        </svg>
                        Export PDF
                    </a>
                @endif
                <a href="{{ route('kiosk.gerbang') }}" target="_blank" class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs shadow-sm transition">
                    <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    Buka Kiosk Gerbang
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="teacherMonitorApp()" x-init="startPolling()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Summary Cards -->
            @if($periode === 'hari')
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Siswa</div>
                    <div class="text-3xl font-black text-gray-900 mt-2" x-text="summary.total">{{ $summary['total'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">Kelas {{ $schoolClass->name }}</div>
                </div>

                <div class="bg-emerald-50/60 p-5 rounded-xl border border-emerald-200 shadow-sm">
                    <div class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Hadir Tepat Waktu</div>
                    <div class="text-3xl font-black text-emerald-700 mt-2" x-text="summary.hadir">{{ $summary['hadir'] }}</div>
                    <div class="text-xs text-emerald-600 mt-1">Sesuai jam masuk kelas</div>
                </div>

                <div class="bg-amber-50/60 p-5 rounded-xl border border-amber-200 shadow-sm">
                    <div class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Terlambat</div>
                    <div class="text-3xl font-black text-amber-700 mt-2" x-text="summary.terlambat">{{ $summary['terlambat'] }}</div>
                    <div class="text-xs text-amber-600 mt-1">Lewat jam masuk kelas</div>
                </div>

                <div class="bg-rose-50/60 p-5 rounded-xl border border-rose-200 shadow-sm">
                    <div class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Belum Absen</div>
                    <div class="text-3xl font-black text-rose-700 mt-2" x-text="summary.belum_absen">{{ $summary['belum_absen'] }}</div>
                    <div class="text-xs text-rose-600 mt-1">Menunggu tap Gerbang</div>
                </div>
            </div>
            @else
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Siswa</div>
                    <div class="text-3xl font-black text-gray-900 mt-2" x-text="summary.total">{{ $summary['total'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">Kelas {{ $schoolClass->name }}</div>
                </div>

                <div class="bg-emerald-50/60 p-5 rounded-xl border border-emerald-200 shadow-sm">
                    <div class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Hadir (hari)</div>
                    <div class="text-3xl font-black text-emerald-700 mt-2" x-text="summary.hadir">{{ $summary['hadir'] }}</div>
                    <div class="text-xs text-emerald-600 mt-1">Akumulasi Senin–Jumat</div>
                </div>

                <div class="bg-amber-50/60 p-5 rounded-xl border border-amber-200 shadow-sm">
                    <div class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Terlambat (hari)</div>
                    <div class="text-3xl font-black text-amber-700 mt-2" x-text="summary.terlambat">{{ $summary['terlambat'] }}</div>
                    <div class="text-xs text-amber-600 mt-1">Akumulasi Senin–Jumat</div>
                </div>

                <div class="bg-rose-50/60 p-5 rounded-xl border border-rose-200 shadow-sm">
                    <div class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Tidak Hadir (hari)</div>
                    <div class="text-3xl font-black text-rose-700 mt-2" x-text="summary.tidak_hadir">{{ $summary['tidak_hadir'] }}</div>
                    <div class="text-xs text-rose-600 mt-1">Rata-rata kehadiran: <span x-text="summary.rata_persen">{{ $summary['rata_persen'] }}</span>%</div>
                </div>
            </div>
            @endif

            <!-- Table Container -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-5 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Daftar Kehadiran Siswa</h2>
                        <p class="text-xs text-gray-500" x-text="dateFormatted ? dateFormatted + ' - Server: ' + serverTime + ' WIB' : 'Memuat data...'">
                            {{ $tanggalDisplay }}
                        </p>
                    </div>

                    <!-- LIVE badge -->
                    <div class="flex items-center space-x-2 text-xs font-bold text-rose-700 bg-rose-50 px-3 py-1.5 rounded-full border border-rose-200">
                        <span class="relative flex w-2.5 h-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full w-2.5 h-2.5 bg-rose-600"></span>
                        </span>
                        <span>LIVE</span>
                        <span class="text-rose-400 font-medium">· refresh 10 detik</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($periode === 'hari')
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <th class="py-3.5 px-4 text-center w-12">No</th>
                                <th class="py-3.5 px-4">NISN</th>
                                <th class="py-3.5 px-4">Nama Siswa</th>
                                <th class="py-3.5 px-4">UID Kartu</th>
                                <th class="py-3.5 px-4 text-center">Foto Bukti</th>
                                <th class="py-3.5 px-4 text-center">Jam Masuk</th>
                                <th class="py-3.5 px-4 text-center">Jam Pulang</th>
                                <th class="py-3.5 px-4 text-center">Status</th>
                                <th class="py-3.5 px-4 text-center">Metode</th>
                                <th class="py-3.5 px-4 text-center">Wajah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            <template x-for="(s, index) in students" :key="s.id">
                                <tr class="hover:bg-slate-50 transition"
                                    :class="{'bg-emerald-50/20': s.status === 'HADIR', 'bg-amber-50/20': s.status === 'TERLAMBAT'}">
                                    <td class="py-3 px-4 text-center text-xs text-gray-400 font-mono" x-text="index + 1"></td>
                                    <td class="py-3 px-4 font-mono font-medium text-gray-700" x-text="s.nisn"></td>
                                    <td class="py-3 px-4 font-semibold text-gray-900" x-text="s.name"></td>
                                    <td class="py-3 px-4 font-mono text-xs text-gray-500" x-text="s.card_uid"></td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <img x-show="s.photo_in" :src="s.photo_in" @click="lightbox = s.photo_in"
                                                 class="w-14 h-9 object-cover rounded-md border border-emerald-400 cursor-zoom-in hover:scale-110 transition"
                                                 alt="Foto masuk" title="Klik untuk perbesar (foto masuk)" />
                                            <span x-show="!s.photo_in"
                                                  class="inline-flex items-center justify-center w-14 h-9 rounded-md border border-dashed border-gray-300 text-[9px] font-bold text-gray-400">M: -</span>
                                            <img x-show="s.photo_out" :src="s.photo_out" @click="lightbox = s.photo_out"
                                                 class="w-14 h-9 object-cover rounded-md border border-blue-400 cursor-zoom-in hover:scale-110 transition"
                                                 alt="Foto pulang" title="Klik untuk perbesar (foto pulang)" />
                                            <span x-show="!s.photo_out"
                                                  class="inline-flex items-center justify-center w-14 h-9 rounded-md border border-dashed border-gray-300 text-[9px] font-bold text-gray-400">P: -</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-medium"
                                        :class="s.check_in !== '-' ? 'text-gray-900 font-bold' : 'text-gray-300'"
                                        x-text="s.check_in"></td>
                                    <td class="py-3 px-4 text-center font-mono font-medium"
                                        :class="s.check_out !== '-' ? 'text-gray-900 font-bold' : 'text-gray-300'"
                                        x-text="s.check_out"></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                                              :class="{
                                                  'bg-emerald-100 text-emerald-800': s.status === 'HADIR',
                                                  'bg-amber-100 text-amber-800': s.status === 'TERLAMBAT',
                                                  'bg-red-100 text-red-800': s.status === 'ALPA',
                                                  'bg-gray-100 text-gray-600': s.status === 'BELUM_ABSEN'
                                              }"
                                              x-text="s.status_label">
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center text-xs font-semibold text-gray-600" x-text="s.metode"></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                                              :class="s.face === 'TERDAFTAR' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'"
                                              x-text="s.face === 'TERDAFTAR' ? 'Terdaftar' : 'Belum'">
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    @else
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <th class="py-3.5 px-4 text-center w-12">No</th>
                                <th class="py-3.5 px-4">NISN</th>
                                <th class="py-3.5 px-4">Nama Siswa</th>
                                <th class="py-3.5 px-4 text-center">Hadir</th>
                                <th class="py-3.5 px-4 text-center">Terlambat</th>
                                <th class="py-3.5 px-4 text-center">Tidak Hadir</th>
                                <th class="py-3.5 px-4 text-center">Persentase</th>
                                <th class="py-3.5 px-4 text-center">Wajah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            <template x-for="(s, index) in students" :key="s.id">
                                <tr class="hover:bg-slate-50 transition"
                                    :class="{'bg-emerald-50/20': s.tidak_hadir === 0, 'bg-rose-50/20': s.tidak_hadir > 2}">
                                    <td class="py-3 px-4 text-center text-xs text-gray-400 font-mono" x-text="index + 1"></td>
                                    <td class="py-3 px-4 font-mono font-medium text-gray-700" x-text="s.nisn"></td>
                                    <td class="py-3 px-4 font-semibold text-gray-900" x-text="s.name"></td>
                                    <td class="py-3 px-4 text-center font-mono font-bold text-emerald-700" x-text="s.hadir"></td>
                                    <td class="py-3 px-4 text-center font-mono font-bold text-amber-700" x-text="s.terlambat"></td>
                                    <td class="py-3 px-4 text-center font-mono font-bold" :class="s.tidak_hadir > 0 ? 'text-rose-600' : 'text-gray-400'" x-text="s.tidak_hadir"></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="font-mono font-bold" :class="s.persentase >= 80 ? 'text-emerald-700' : (s.persentase >= 50 ? 'text-amber-700' : 'text-rose-600')" x-text="s.persentase + '%'"></span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                                              :class="s.face === 'TERDAFTAR' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'"
                                              x-text="s.face === 'TERDAFTAR' ? 'Terdaftar' : 'Belum'">
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    @endif
                </div>

                <div class="p-4 bg-gray-50 border-t border-gray-200 text-xs text-gray-500 flex flex-wrap justify-between items-center gap-2">
                    <span>Menampilkan {{ $students->count() }} siswa aktif kelas {{ $schoolClass->name }}@if($periode !== 'hari') — ringkasan {{ strtolower($periodeLabel) }} (hari sekolah: Senin–Jumat)@endif</span>
                    <span>SMK Karya Nugraha (KNBI) - SIAP Sekolah</span>
                </div>
            </div>

            <!-- Lightbox: klik thumbnail untuk memperbesar (hanya di halaman guru) -->
            <div x-show="lightbox" x-cloak @click="lightbox = ''"
                 class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-6 cursor-zoom-out">
                <img :src="lightbox" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl border-4 border-white/10" alt="Foto bukti absen diperbesar" />
                <div class="absolute top-5 right-6 px-3 py-1.5 rounded-lg bg-white/10 text-white/90 text-xs font-semibold">
                    Klik di mana saja untuk menutup
                </div>
            </div>

        </div>
    </div>

    <script>
        function teacherMonitorApp() {
            return {
                summary: @json($summary),
                students: @json($students),
                dateFormatted: '',
                serverTime: '',
                lightbox: '',
                pollingTimer: null,

                startPolling() {
                    this.fetchData();
                    this.pollingTimer = setInterval(() => {
                        this.fetchData();
                    }, 10000);
                },

                async fetchData() {
                    try {
                        const res = await fetch("{{ route('guru.rekap.data') }}?kelas={{ $schoolClass->slug }}&tanggal={{ $today }}&periode={{ $periode }}");
                        if (res.ok) {
                            const data = await res.json();
                            this.summary = data.summary;
                            this.students = data.students;
                            this.dateFormatted = data.date_formatted;
                            this.serverTime = data.server_time;
                        }
                    } catch (e) {
                        // Silent retry on temporary connection lag
                    }
                }
            }
        }
    </script>
</x-app-layout>
