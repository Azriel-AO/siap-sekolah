<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Daftar Hadir</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Laporan kehadiran per bulan sesuai format sekolah (satu sheet per kelas).</p>
            </div>
            <a href="{{ route('admin.pengaturan.laporan') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-gray-300 hover:border-indigo-400 hover:text-indigo-700 text-sm font-semibold text-gray-700 shadow-sm transition">
                <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Pengaturan Laporan
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filter --}}
            <form method="GET" action="{{ route('admin.daftar-hadir') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col sm:flex-row sm:items-end gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Bulan</label>
                    <select name="bulan" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5 w-40">
                        @foreach(range(1, 12) as $b)
                            <option value="{{ $b }}" @selected($b === $bulan)>{{ \Carbon\Carbon::create($tahun, $b, 1)->translatedFormat('F') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Tahun</label>
                    <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2100" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5 w-28">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Kelas</label>
                    <select name="kelas" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5 w-40">
                        <option value="semua" @selected($slug === 'semua')>Semua Kelas</option>
                        @foreach($classes as $kelas)
                            <option value="{{ $kelas->slug }}" @selected($slug === $kelas->slug)>{{ $kelas->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-white border border-gray-300 hover:border-indigo-400 hover:text-indigo-700 text-sm font-semibold text-gray-700 shadow-sm transition">Tampilkan</button>
                    <a href="{{ route('admin.daftar-hadir.excel', ['bulan' => $bulan, 'tahun' => $tahun, 'kelas' => $slug]) }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm transition">
                        <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 7a4 4 0 014-4h10a4 4 0 014 4v10a4 4 0 01-4 4H7a4 4 0 01-4-4V7z" />
                        </svg>
                        Unduh Excel
                    </a>
                </div>
            </form>

            {{-- Keterangan simbol --}}
            <div class="text-xs text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                <span>Simbol:</span>
                <span><b class="text-emerald-700">√</b> = Hadir (termasuk terlambat)</span>
                <span><b class="text-amber-600">I</b> = Izin</span>
                <span><b class="text-blue-600">S</b> = Sakit</span>
                <span><b class="text-red-600">A</b> = Alpa (hari sekolah lampau, tidak tap &amp; tidak izin)</span>
                <span>Kosong = bukan hari sekolah / belum lewat / hari ini belum tap</span>
            </div>

            {{-- Pratinjau --}}
            @if($preview)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-5 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Pratinjau — {{ $preview['kelas']->name }}</h2>
                            <p class="text-xs text-gray-400">{{ $preview['namaBulan'] }} {{ $preview['tahun'] }} — pratinjau blok ({{ count($preview['rows']) - 9 - 5 }} siswa)</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-xs">
                            @foreach(array_slice($preview['rows'], 0, 18) as $i => $row)
                                <tr>
                                    @foreach($row as $cell)
                                        @php
                                            $warnaSimbol = match ((string) $cell) {
                                                '√' => 'text-[#008000] font-bold',
                                                'I' => 'text-[#FFA500] font-bold',
                                                'A' => 'text-[#FF0000] font-bold',
                                                'S' => 'text-[#2563EB] font-bold',
                                                default => '',
                                            };
                                        @endphp
                                        <td @if(in_array($i, [0,1,2])) class="font-bold text-gray-900 px-2 py-0.5 text-center" @elseif(in_array($i, [3,4,5,6])) class="font-bold text-gray-900 px-2 py-0.5" @else class="border border-gray-200 px-2 py-0.5 text-center {{ $i === 7 || $i === 8 ? 'bg-gray-50 font-bold' : $warnaSimbol }}" @endif>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            @elseif($slug === 'semua')
                <p class="text-xs text-gray-400">Pratinjau ditampilkan bila satu kelas dipilih. Unduh Excel untuk semua kelas.</p>
            @endif
        </div>
    </div>
</x-app-layout>
