<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">
                    Data Siswa
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-slate-400 mt-0.5">
                    Kelola nama, NIS, jenis kelamin, dan kartu RFID siswa per kelas.
                </p>
            </div>
            <div class="flex items-center space-x-3">
                {{-- Pemilih kelas: reload dengan ?kelas=slug --}}
                <select onchange="window.location.href='{{ route('admin.siswa.index') }}?kelas=' + encodeURIComponent(this.value)"
                        class="rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
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

    <div class="py-8" x-data="{ modalHapus: false, targetSiswa: null, actionUrl: '' }">
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
                    <div class="p-5 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">Siswa Kelas {{ $kelasDipilih->name }}</h2>
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $kelasDipilih->department }} - {{ $students->count() }} siswa</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-slate-900/70 border-b border-gray-200 dark:border-slate-700 text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wider">
                                    <th class="py-3.5 px-4 text-center w-12">No</th>
                                    <th class="py-3.5 px-4">NIS</th>
                                    <th class="py-3.5 px-4">Nama Siswa</th>
                                    <th class="py-3.5 px-4 text-center">L/P</th>
                                    <th class="py-3.5 px-4">Kartu</th>
                                    <th class="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                                @forelse($students as $index => $siswa)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                        <td class="py-3 px-4 text-center text-xs text-gray-400 dark:text-slate-500 font-mono">{{ $index + 1 }}</td>
                                        <td class="py-3 px-4 font-mono font-medium text-gray-700 dark:text-slate-300">{{ $siswa->nis }}</td>
                                        <td class="py-3 px-4 font-semibold text-gray-900 dark:text-slate-100">{{ $siswa->name }}</td>
                                        <td class="py-3 px-4 text-center text-gray-600 dark:text-slate-300">{{ $siswa->gender }}</td>
                                        <td class="py-3 px-4 font-mono text-xs text-gray-600 dark:text-slate-300">
                                            @if($siswa->rfidCard)
                                                <span title="UID: {{ $siswa->rfidCard->card_uid }}" class="cursor-help font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-800">
                                                    {{ $siswa->card_label }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 dark:text-slate-500">-</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex items-center justify-center space-x-2">
                                                <a href="{{ route('admin.siswa.edit', $siswa) }}" class="inline-flex items-center px-3 py-1.5 rounded-md bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 hover:border-indigo-400 dark:hover:border-indigo-500 hover:text-indigo-700 dark:hover:text-indigo-300 text-xs font-semibold text-gray-700 dark:text-slate-200 shadow-sm transition">
                                                    Ubah
                                                </a>
                                                <button type="button"
                                                        @click="targetSiswa = {{ json_encode([
                                                            'id' => $siswa->id,
                                                            'name' => $siswa->name,
                                                            'nis' => $siswa->nis,
                                                            'attendances_count' => $siswa->attendances_count ?? 0,
                                                            'has_card' => (bool)$siswa->rfidCard,
                                                            'card_uid' => $siswa->rfidCard?->card_uid,
                                                            'card_label' => $siswa->card_label,
                                                            'face_photos_count' => $siswa->faceEnrollment ? collect([$siswa->faceEnrollment->photo_1_path, $siswa->faceEnrollment->photo_2_path, $siswa->faceEnrollment->photo_3_path])->filter()->count() : 0,
                                                        ]) }}; actionUrl = '{{ route('admin.siswa.destroy', $siswa) }}'; modalHapus = true;"
                                                        class="inline-flex items-center px-3 py-1.5 rounded-md bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 hover:border-rose-400 dark:hover:border-rose-500 hover:text-rose-700 dark:hover:text-rose-300 text-xs font-semibold text-gray-700 dark:text-slate-200 shadow-sm transition">
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-sm text-gray-400 dark:text-slate-500">Belum ada siswa di kelas ini. Klik "Tambah Siswa".</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Modal Konfirmasi Hapus Siswa -->
        <div x-show="modalHapus" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm transition-opacity duration-200" @click="modalHapus = false"></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div x-show="modalHapus"
                     x-transition:enter="transition-all ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="transition-all ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all duration-200 sm:my-8 sm:w-full sm:max-w-lg border border-gray-100 dark:border-slate-700 p-6 space-y-5"
                     @click.away="modalHapus = false">

                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-full bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100" id="modal-title">
                                Hapus Data Siswa: <span x-text="targetSiswa?.name"></span>
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                                NIS: <span class="font-mono font-medium text-gray-700 dark:text-slate-300" x-text="targetSiswa?.nis"></span>
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/70 p-4 space-y-2 text-sm text-rose-900 dark:text-rose-200">
                        <p class="font-semibold text-rose-800 dark:text-rose-300">
                            Data berikut akan ikut terhapus permanen (hard-delete):
                        </p>
                        <ul class="list-disc list-inside space-y-1 text-xs text-rose-700 dark:text-rose-300">
                            <li>
                                <strong x-text="targetSiswa?.attendances_count"></strong> catatan absensi
                            </li>
                            <li x-show="targetSiswa?.has_card" x-cloak>
                                <strong>1 kartu</strong> RFID (<span class="font-mono" x-text="targetSiswa?.card_label || targetSiswa?.card_uid" :title="'UID: ' + (targetSiswa?.card_uid || '')"></span>)
                            </li>
                            <li x-show="!targetSiswa?.has_card" x-cloak class="opacity-75">
                                Tidak ada kartu RFID
                            </li>
                            <li>
                                <strong x-text="targetSiswa?.face_photos_count"></strong> foto wajah
                            </li>
                        </ul>
                        <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-2 italic">
                            * Tindakan ini tidak dapat dibatalkan. NISN dan kartu yang dihapus dapat didaftarkan kembali.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button"
                                @click="modalHapus = false"
                                class="px-4 py-2 rounded-lg border border-gray-300 dark:border-slate-600 text-sm font-semibold text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                            Batal
                        </button>
                        <form :action="actionUrl" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm transition">
                                Ya, Hapus Permanen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
