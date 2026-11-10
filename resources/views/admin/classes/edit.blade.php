<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Ubah Kelas</h1>
        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Perbarui data kelas {{ $class->name }}. Kosongkan slug untuk membuatnya ulang dari nama.</p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.kelas.update', $class) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
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
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nama Kelas <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $class->name) }}" required maxlength="50"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $class->slug) }}" maxlength="50"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-[11px] text-gray-400 mt-1">Slug dipakai di URL kiosk &amp; rekap (contoh: /kiosk/X-TKJ-1). Harus unik; kosongkan untuk dibuat ulang dari nama.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Jurusan <span class="text-rose-500">*</span></label>
                    <input type="text" name="department" value="{{ old('department', $class->department) }}" required maxlength="50"
                           class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Jam Masuk <span class="text-rose-500">*</span></label>
                        <input type="time" name="entry_time" value="{{ old('entry_time', substr($class->entry_time, 0, 5)) }}" required
                               class="w-full rounded-lg border-gray-300 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Jam Pulang <span class="text-rose-500">*</span></label>
                        <input type="time" name="exit_time" value="{{ old('exit_time', substr($class->exit_time, 0, 5)) }}" required
                               class="w-full rounded-lg border-gray-300 text-sm shadow-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-2">
                    <a href="{{ route('admin.kelas.index') }}" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-sm transition">Batal</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
