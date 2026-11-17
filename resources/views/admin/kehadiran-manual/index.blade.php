<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">Catat Izin / Sakit</h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-slate-400 mt-0.5">Tandai siswa izin atau sakit per hari (berlaku untuk rekap &amp; laporan).</p>
            </div>
            <div class="flex items-center space-x-3">
                {{-- Filter tanggal --}}
                <form method="GET" action="{{ route('admin.kehadiran.manual') }}" class="flex items-center space-x-2">
                    <input type="hidden" name="kelas" value="{{ $kelasDipilih?->slug }}">
                    <input type="date" name="tanggal" value="{{ $tanggal }}" onchange="this.form.submit()" max="{{ now()->toDateString() }}"
                           class="rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                           aria-label="Pilih tanggal">
                </form>
                {{-- Pemilih kelas --}}
                <select onchange="window.location.href='{{ route('admin.kehadiran.manual') }}?kelas=' + encodeURIComponent(this.value) + '&tanggal={{ $tanggal }}'"
                        class="rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        aria-label="Pilih kelas">
                    @forelse($classes as $kelas)
                        <option value="{{ $kelas->slug }}" @selected($kelasDipilih && $kelas->slug === $kelasDipilih->slug)>{{ $kelas->name }}</option>
                    @empty
                        <option value="">Belum ada kelas</option>
                    @endforelse
                </select>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm font-medium text-emerald-800 dark:text-emerald-300 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if(! $kelasDipilih)
                <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 px-4 py-6 text-center text-sm text-amber-800 dark:text-amber-300">
                    Belum ada kelas. Buat kelas terlebih dahulu di menu <strong>Data Kelas</strong>.
                </div>
            @else
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                    <div class="p-5 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">Izin / Sakit - {{ $kelasDipilih->name }}</h2>
                        <p class="text-xs text-gray-500 dark:text-slate-400">{{ $tanggalDisplay }} - {{ $rows->count() }} siswa</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-slate-900/70 border-b border-gray-200 dark:border-slate-700 text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">
                                    <th class="py-3.5 px-4 text-center w-12">No</th>
                                    <th class="py-3.5 px-4">NIS Induk</th>
                                    <th class="py-3.5 px-4">NISN</th>
                                    <th class="py-3.5 px-4">Nama Siswa</th>
                                    <th class="py-3.5 px-4 text-center">Status Hari Ini</th>
                                    <th class="py-3.5 px-4">Keterangan</th>
                                    <th class="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                                @forelse($rows as $index => $row)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                        <td class="py-3 px-4 text-center text-xs text-gray-400 dark:text-slate-500 font-mono">{{ $index + 1 }}</td>
                                        <td class="py-3 px-4 font-mono text-xs text-gray-500 dark:text-slate-400">{{ $row['induk'] ?? '-' }}</td>
                                        <td class="py-3 px-4 font-mono text-xs text-gray-500 dark:text-slate-400">{{ $row['nisn'] ?? '-' }}</td>
                                        <td class="py-3 px-4 font-semibold text-gray-900 dark:text-slate-100">{{ $row['name'] }}</td>
                                        <td class="py-3 px-4 text-center">
                                            @php
                                                $badge = match ($row['statusJenis']) {
                                                    'hadir' => 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300',
                                                    'terlambat' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300',
                                                    'izin' => 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300',
                                                    'sakit' => 'bg-sky-100 dark:bg-sky-950/60 text-sky-800 dark:text-sky-300',
                                                    default => 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-300',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $badge }}">{{ $row['status'] }}</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <form method="POST" action="{{ route('admin.kehadiran.manual.simpan') }}" class="flex items-center space-x-2">
                                                @csrf
                                                <input type="hidden" name="student_id" value="{{ $row['id'] }}">
                                                <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                                                <input type="hidden" name="kelas" value="{{ $kelasDipilih->slug }}">
                                                <input type="text" name="keterangan" value="{{ $row['keterangan'] }}" maxlength="255" placeholder="Keterangan (opsional)"
                                                       class="w-44 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-400 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                                                <button type="submit" name="aksi" value="izin" title="Tandai Izin"
                                                        class="w-9 h-9 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-black transition">I</button>
                                                <button type="submit" name="aksi" value="sakit" title="Tandai Sakit"
                                                        class="w-9 h-9 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-sm font-black transition">S</button>
                                                @if($row['hasIzinSakit'])
                                                    <button type="submit" name="aksi" value="hapus" title="Hapus catatan izin/sakit"
                                                            class="px-3 h-9 rounded-md bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 hover:border-rose-400 dark:hover:border-rose-500 hover:text-rose-700 dark:hover:text-rose-300 text-xs font-semibold text-gray-600 dark:text-slate-200 transition">Hapus</button>
                                                @endif
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-sm text-gray-400 dark:text-slate-500">Belum ada siswa di kelas ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
