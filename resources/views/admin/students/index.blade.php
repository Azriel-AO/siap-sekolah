<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Data Siswa
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    Kelola nama, NIS, jenis kelamin, kartu RFID, dan akun login siswa per kelas.
                </p>
            </div>
            <div class="flex items-center space-x-3">
                {{-- Pemilih kelas: reload dengan ?kelas=slug --}}
                <select onchange="window.location.href='{{ route('admin.siswa.index') }}?kelas=' + encodeURIComponent(this.value)"
                        class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        aria-label="Pilih kelas">
                    @forelse($classes as $kelas)
                        <option value="{{ $kelas->slug }}" @selected($kelasDipilih && $kelas->slug === $kelasDipilih->slug)>{{ $kelas->name }}</option>
                    @empty
                        <option value="">Belum ada kelas</option>
                    @endforelse
                </select>
                <a href="{{ route('admin.siswa.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">
                    <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Siswa
                </a>
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
                    <div class="p-5 border-b border-gray-200 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Siswa Kelas {{ $kelasDipilih->name }}</h2>
                            <p class="text-xs text-gray-500">{{ $kelasDipilih->department }} — {{ $students->count() }} siswa</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <th class="py-3.5 px-4 text-center w-12">No</th>
                                    <th class="py-3.5 px-4">NIS</th>
                                    <th class="py-3.5 px-4">Nama Siswa</th>
                                    <th class="py-3.5 px-4 text-center">L/P</th>
                                    <th class="py-3.5 px-4">UID Kartu</th>
                                    <th class="py-3.5 px-4">Akun Login</th>
                                    <th class="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                @forelse($students as $index => $siswa)
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3 px-4 text-center text-xs text-gray-400 font-mono">{{ $index + 1 }}</td>
                                        <td class="py-3 px-4 font-mono font-medium text-gray-700">{{ $siswa->nis }}</td>
                                        <td class="py-3 px-4 font-semibold text-gray-900">{{ $siswa->name }}</td>
                                        <td class="py-3 px-4 text-center text-gray-600">{{ $siswa->gender }}</td>
                                        <td class="py-3 px-4 font-mono text-xs text-gray-500">{{ $siswa->rfidCard?->card_uid ?? '-' }}</td>
                                        <td class="py-3 px-4 text-xs">
                                            @if($siswa->user)
                                                <span class="font-mono text-gray-700">{{ $siswa->user->email }}</span>
                                            @else
                                                <span class="text-gray-400">Belum ada</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <a href="{{ route('admin.siswa.edit', $siswa) }}" class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:border-indigo-400 hover:text-indigo-700 text-xs font-semibold text-gray-700 shadow-sm transition">
                                                Ubah
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-sm text-gray-400">Belum ada siswa di kelas ini. Klik "Tambah Siswa".</td>
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
