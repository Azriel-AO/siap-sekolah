<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">Ubah Siswa</h1>
        <p class="text-xs sm:text-sm text-gray-500 dark:text-slate-400 mt-0.5">Perbarui NISN, nama, jenis kelamin, kelas, dan kartu RFID milik {{ $student->name }}.</p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.siswa.update', $student) }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-5">
                @csrf
                @method('PUT')

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
                    <input type="text" name="name" value="{{ old('name', $student->name) }}" required maxlength="100"
                           class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">No. Induk</label>
                    <input type="text" name="induk" value="{{ old('induk', $student->induk) }}" maxlength="20"
                           class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-500 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: 240001">
                    <p class="text-[11px] text-gray-400 dark:text-slate-400 mt-1">Diperlukan untuk laporan "Daftar Hadir" (opsional).</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">NISN <span class="text-rose-500">*</span></label>
                        <input type="text" name="nisn" value="{{ old('nisn', $student->nisn) }}" required inputmode="numeric" maxlength="10"
                               class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: 0010240111">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Jenis Kelamin <span class="text-rose-500">*</span></label>
                        <select name="gender" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="L" @selected(old('gender', $student->gender) === 'L')>L - Laki-laki</option>
                            <option value="P" @selected(old('gender', $student->gender) === 'P')>P - Perempuan</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">Kelas <span class="text-rose-500">*</span></label>
                    <select name="school_class_id" required class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($classes as $kelas)
                            <option value="{{ $kelas->id }}" @selected(old('school_class_id', $student->school_class_id) == $kelas->id)>{{ $kelas->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">UID Kartu RFID</label>
                    <input type="text" name="card_uid" value="{{ old('card_uid', $student->rfidCard?->card_uid) }}" maxlength="50"
                           class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-500 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="Kosongkan bila tidak ingin mengubah kartu">
                    <p class="text-[11px] text-gray-400 dark:text-slate-400 mt-1">Isi untuk membuat/memperbarui kartu milik siswa ini. Kartu tidak dihapus dari sini.</p>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-2">
                    <a href="{{ route('admin.siswa.index') }}" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 font-semibold text-sm transition">Batal</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
