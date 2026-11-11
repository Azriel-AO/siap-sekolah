<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Ubah Siswa</h1>
        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">NIS, nama, jenis kelamin, kelas, kartu RFID, dan akun login milik {{ $student->name }}.</p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.siswa.update', $student) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
                @csrf
                @method('PUT')

                @if($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p class="font-semibold mb-1">Data belum bisa disimpan:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nama Siswa <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $student->name) }}" required maxlength="100"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">NIS Induk</label>
                    <input type="text" name="induk" value="{{ old('induk', $student->induk) }}" maxlength="20"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: 0075384110">
                    <p class="text-[11px] text-gray-400 mt-1">Diperlukan untuk laporan "Daftar Hadir" (opsional).</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">NIS <span class="text-rose-500">*</span></label>
                        <input type="text" name="nis" value="{{ old('nis', $student->nis) }}" required maxlength="20"
                               class="w-full rounded-lg border-gray-300 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Jenis Kelamin <span class="text-rose-500">*</span></label>
                        <select name="gender" required class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="L" @selected(old('gender', $student->gender) === 'L')>L — Laki-laki</option>
                            <option value="P" @selected(old('gender', $student->gender) === 'P')>P — Perempuan</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Kelas <span class="text-rose-500">*</span></label>
                    <select name="school_class_id" required class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($classes as $kelas)
                            <option value="{{ $kelas->id }}" @selected(old('school_class_id', $student->school_class_id) == $kelas->id)>{{ $kelas->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">UID Kartu RFID</label>
                    <input type="text" name="card_uid" value="{{ old('card_uid', $student->rfidCard?->card_uid) }}" maxlength="50"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="Kosongkan bila tidak ingin mengubah kartu">
                    <p class="text-[11px] text-gray-400 mt-1">Isi untuk membuat/memperbarui kartu milik siswa ini. Kartu tidak dihapus dari sini.</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    @if($student->user)
                        <p class="text-sm text-gray-700"><span class="font-semibold">Akun login:</span> <code class="font-mono">{{ $student->user->email }}</code> — sudah terhubung.</p>
                    @else
                        <label class="flex items-start space-x-3 cursor-pointer">
                            <input type="checkbox" name="buat_akun" value="1" @checked(old('buat_akun')) class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm">
                                <span class="font-semibold text-gray-800">Buatkan akun login siswa</span><br>
                                <span class="text-xs text-gray-500">Email: <code>&lt;NIS&gt;@knbi.sch.id</code>, kata sandi awal: <code>password</code>, peran: siswa.</span>
                            </span>
                        </label>
                    @endif
                </div>

                <div class="flex items-center justify-end space-x-3 pt-2">
                    <a href="{{ route('admin.siswa.index') }}" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-sm transition">Batal</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
