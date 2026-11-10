<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Kelas</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Kelola nama, jurusan, dan jam masuk/pulang per kelas. Slug dibuat otomatis dari nama.</p>
            </div>
            <a href="{{ route('admin.kelas.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">
                <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Kelas
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('error'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <th class="py-3.5 px-4 text-center w-12">No</th>
                                <th class="py-3.5 px-4">Nama</th>
                                <th class="py-3.5 px-4">Slug</th>
                                <th class="py-3.5 px-4">Jurusan</th>
                                <th class="py-3.5 px-4 text-center">Jam Masuk</th>
                                <th class="py-3.5 px-4 text-center">Jam Pulang</th>
                                <th class="py-3.5 px-4 text-center">Siswa</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            @forelse($classes as $index => $kelas)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-4 text-center text-xs text-gray-400 font-mono">{{ $index + 1 }}</td>
                                    <td class="py-3 px-4 font-semibold text-gray-900">{{ $kelas->name }}</td>
                                    <td class="py-3 px-4 font-mono text-xs text-gray-500">{{ $kelas->slug }}</td>
                                    <td class="py-3 px-4 text-gray-600">{{ $kelas->department }}</td>
                                    <td class="py-3 px-4 text-center font-mono text-gray-700">{{ substr($kelas->entry_time, 0, 5) }}</td>
                                    <td class="py-3 px-4 text-center font-mono text-gray-700">{{ substr($kelas->exit_time, 0, 5) }}</td>
                                    <td class="py-3 px-4 text-center font-mono text-gray-700">{{ $kelas->students_count }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center justify-center space-x-2">
                                            <a href="{{ route('admin.kelas.edit', $kelas) }}" class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:border-indigo-400 hover:text-indigo-700 text-xs font-semibold text-gray-700 shadow-sm transition">
                                                Ubah
                                            </a>
                                            <form method="POST" action="{{ route('admin.kelas.destroy', $kelas) }}" onsubmit="return confirm('Hapus kelas {{ $kelas->name }}? Hanya bisa dihapus bila belum ada siswa dan absensi.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:border-rose-400 hover:text-rose-700 text-xs font-semibold text-gray-700 shadow-sm transition">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-sm text-gray-400">Belum ada kelas. Klik "Tambah Kelas".</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
