<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Catat Izin / Sakit</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Tandai siswa izin atau sakit per hari (berlaku untuk rekap &amp; laporan).</p>
            </div>
            <div class="flex items-center space-x-3">
                {{-- Filter tanggal --}}
                <form method="GET" action="{{ route('admin.kehadiran.manual') }}" class="flex items-center space-x-2">
                    <input type="hidden" name="kelas" value="{{ $kelasDipilih?->slug }}">
                    <input type="date" name="tanggal" value="{{ $tanggal }}" onchange="this.form.submit()" max="{{ now()->toDateString() }}"
                           class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                           aria-label="Pilih tanggal">
                </form>
                {{-- Pemilih kelas --}}
                <select onchange="window.location.href='{{ route('admin.kehadiran.manual') }}?kelas=' + encodeURIComponent(this.value) + '&tanggal={{ $tanggal }}'"
                        class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
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
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if(! $kelasDipilih)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-800">
                    Belum ada kelas. Buat kelas terlebih dahulu di menu <strong>Data Kelas</strong>.
                </div>
            @else
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-5 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-900">Izin / Sakit — {{ $kelasDipilih->name }}</h2>
                        <p class="text-xs text-gray-500">{{ $tanggalDisplay }} — {{ $rows->count() }} siswa</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <th class="py-3.5 px-4 text-center w-12">No</th>
                                    <th class="py-3.5 px-4">NIS Induk</th>
                                    <th class="py-3.5 px-4">NISN</th>
                                    <th class="py-3.5 px-4">Nama Siswa</th>
                                    <th class="py-3.5 px-4 text-center">Status Hari Ini</th>
                                    <th class="py-3.5 px-4">Keterangan</th>
                                    <th class="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                @forelse($rows as $index => $row)
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3 px-4 text-center text-xs text-gray-400 font-mono">{{ $index + 1 }}</td>
                                        <td class="py-3 px-4 font-mono text-xs text-gray-500">{{ $row['induk'] ?? '-' }}</td>
                                        <td class="py-3 px-4 font-mono text-xs text-gray-500">{{ $row['nisn'] ?? '-' }}</td>
                                        <td class="py-3 px-4 font-semibold text-gray-900">{{ $row['name'] }}</td>
                                        <td class="py-3 px-4 text-center">
                                            @php
                                                $badge = match ($row['statusJenis']) {
                                                    'hadir' => 'bg-emerald-100 text-emerald-800',
                                                    'terlambat' => 'bg-amber-100 text-amber-800',
                                                    'izin' => 'bg-indigo-100 text-indigo-800',
                                                    'sakit' => 'bg-sky-100 text-sky-800',
                                                    default => 'bg-gray-100 text-gray-500',
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
                                                       class="w-44 rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                                                <button type="submit" name="aksi" value="izin" title="Tandai Izin"
                                                        class="w-9 h-9 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-black transition">I</button>
                                                <button type="submit" name="aksi" value="sakit" title="Tandai Sakit"
                                                        class="w-9 h-9 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-sm font-black transition">S</button>
                                                @if($row['hasIzinSakit'])
                                                    <button type="submit" name="aksi" value="hapus" title="Hapus catatan izin/sakit"
                                                            class="px-3 h-9 rounded-md bg-white border border-gray-300 hover:border-rose-400 hover:text-rose-700 text-xs font-semibold text-gray-600 transition">Hapus</button>
                                                @endif
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-sm text-gray-400">Belum ada siswa di kelas ini.</td>
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
