<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-600 text-white font-extrabold text-2xl shadow-sm mb-3">
            S
        </div>
        <h2 class="text-xl font-bold text-gray-900 tracking-tight">
            SIAP — SMK Karya Nugraha
        </h2>
        <p class="text-xs text-gray-500 mt-1">
            Sistem Informasi Absensi Pelajar (KNBI)
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4" novalidate>
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block font-medium text-sm text-gray-700">
                Email
            </label>
            <input id="email"
                   class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   type="email"
                   name="email"
                   value="{{ old('email') }}"
                   required
                   autofocus
                   autocomplete="username"
                   placeholder="nama@knbi.sch.id" />
            @error('email')
                <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block font-medium text-sm text-gray-700">
                Kata Sandi
            </label>
            <input id="password"
                   class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                   type="password"
                   name="password"
                   required
                   autocomplete="current-password"
                   placeholder="••••••••" />
            @error('password')
                <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between text-sm">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-xs text-gray-600">Ingat saya di perangkat ini</span>
            </label>
        </div>

        <!-- Tombol Masuk -->
        <div class="pt-2">
            <button type="submit" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                Masuk
            </button>
        </div>

        <p class="text-center text-xs text-gray-500 mt-3">
            Halaman khusus admin sekolah.
        </p>
    </form>

    <!-- Kredensial Akun Contoh (Seed) untuk Pengujian -->
    <div class="mt-6 pt-5 border-t border-gray-200">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 text-center">
            Akun Contoh Pengujian (Kata Sandi: <code class="font-mono bg-gray-100 px-1 py-0.5 rounded text-gray-800">password</code>)
        </p>
        <div class="space-y-1.5 text-xs text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-200">
            <div class="flex justify-between items-center">
                <span>🛡️ <strong>Admin:</strong> admin@knbi.sch.id</span>
                <button type="button" onclick="document.getElementById('email').value='admin@knbi.sch.id'; document.getElementById('password').value='password';" class="text-indigo-600 hover:text-indigo-800 font-medium underline text-[11px]">Gunakan</button>
            </div>
            <div class="flex justify-between items-center">
                <span>👨‍🏫 <strong>Guru:</strong> guru@knbi.sch.id</span>
                <button type="button" onclick="document.getElementById('email').value='guru@knbi.sch.id'; document.getElementById('password').value='password';" class="text-indigo-600 hover:text-indigo-800 font-medium underline text-[11px]">Gunakan</button>
            </div>
            <div class="flex justify-between items-center">
                <span>🎓 <strong>Siswa:</strong> siswa@knbi.sch.id</span>
                <button type="button" onclick="document.getElementById('email').value='siswa@knbi.sch.id'; document.getElementById('password').value='password';" class="text-indigo-600 hover:text-indigo-800 font-medium underline text-[11px]">Gunakan</button>
            </div>
        </div>
    </div>
</x-guest-layout>
