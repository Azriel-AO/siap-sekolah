<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">Tambah Siswa</h1>
        <p class="text-xs sm:text-sm text-gray-500 dark:text-slate-400 mt-0.5">Isi data siswa dan kartu RFID (bila sudah ada).</p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.siswa.store') }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-5">
                @csrf

                @if($errors->any())
                    <div class="rounded-lg border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/60 px-4 py-3 text-sm text-rose-800 dark:text-rose-200">
                        <p class="font-semibold mb-1">Data belum bisa disimpan:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Nama Siswa <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                           class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-500 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: Raka Pratama">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">NIS <span class="text-rose-500">*</span></label>
                        <input type="text" name="nis" value="{{ old('nis') }}" required maxlength="20"
                               class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-500 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: 10240111">
                        <p class="text-[11px] text-gray-400 dark:text-slate-400 mt-1">NIS harus unik untuk setiap siswa.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Jenis Kelamin <span class="text-rose-500">*</span></label>
                        <select name="gender" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="L" @selected(old('gender', 'L') === 'L')>L - Laki-laki</option>
                            <option value="P" @selected(old('gender') === 'P')>P - Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">NIS Induk</label>
                        <input type="text" name="induk" value="{{ old('induk') }}" maxlength="20"
                               class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-500 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: 0075384110">
                        <p class="text-[11px] text-gray-400 dark:text-slate-400 mt-1">NIS Induk sekolah (opsional), jangan tertukar dengan NISN dari pendaftaran.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Kelas <span class="text-rose-500">*</span></label>
                    <select name="school_class_id" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">- Pilih kelas -</option>
                        @foreach($classes as $kelas)
                            <option value="{{ $kelas->id }}" @selected(old('school_class_id') == $kelas->id)>{{ $kelas->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">UID Kartu RFID</label>
                    <input type="text" name="card_uid" value="{{ old('card_uid') }}" maxlength="50"
                           class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-500 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: CARD-TKJ-011 (kosongkan bila belum ada)">
                    <p class="text-[11px] text-gray-400 dark:text-slate-400 mt-1">Kartu dibuat baru bila siswa belum punya, atau UID-nya diperbarui bila sudah ada.</p>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-2">
                    <a href="{{ route('admin.siswa.index') }}" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 font-semibold text-sm transition">Batal</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">Simpan Siswa</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
