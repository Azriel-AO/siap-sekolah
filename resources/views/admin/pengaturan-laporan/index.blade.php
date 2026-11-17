<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">Pengaturan Laporan</h1>
        <p class="text-xs sm:text-sm text-gray-500 dark:text-slate-400 mt-0.5">Kop &amp; tanda tangan untuk laporan "Daftar Hadir", tersimpan di tabel settings dan tidak menyentuh data siswa.</p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm font-medium text-emerald-800 dark:text-emerald-300 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.pengaturan.laporan.simpan') }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-5">
                @csrf

                @if($errors->any())
                    <div class="rounded-lg border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-sm text-rose-800 dark:text-rose-300">
                        <p class="font-semibold mb-1">Data belum bisa disimpan:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Nama Sekolah <span class="text-rose-500">*</span></label>
                        <input type="text" name="laporan_nama_sekolah" value="{{ old('laporan_nama_sekolah', $p['laporan_nama_sekolah']) }}" required maxlength="100"
                               class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Tahun Pelajaran <span class="text-rose-500">*</span></label>
                        <input type="text" name="laporan_tahun_pelajaran" value="{{ old('laporan_tahun_pelajaran', $p['laporan_tahun_pelajaran']) }}" required maxlength="20"
                               class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="2026/2027">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Semester <span class="text-rose-500">*</span></label>
                        <select name="laporan_semester" required class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="Ganjil" @selected(old('laporan_semester', $p['laporan_semester']) === 'Ganjil')>Ganjil</option>
                            <option value="Genap" @selected(old('laporan_semester', $p['laporan_semester']) === 'Genap')>Genap</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Kota (untuk ttd) <span class="text-rose-500">*</span></label>
                        <input type="text" name="laporan_kota" value="{{ old('laporan_kota', $p['laporan_kota']) }}" required maxlength="50"
                               class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Boyolali">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Jabatan Penandatangan <span class="text-rose-500">*</span></label>
                        <input type="text" name="laporan_jabatan_ttd" value="{{ old('laporan_jabatan_ttd', $p['laporan_jabatan_ttd']) }}" required maxlength="50"
                               class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Guru mapel">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Nama Penandatangan <span class="text-rose-500">*</span></label>
                        <input type="text" name="laporan_nama_ttd" value="{{ old('laporan_nama_ttd', $p['laporan_nama_ttd']) }}" required maxlength="100"
                               class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Indriyanto, S.Kom">
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-2">
                    <a href="{{ route('admin.daftar-hadir') }}" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 font-semibold text-sm transition">Kembali</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
