<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="inline-flex items-center justify-center bg-white rounded-lg p-1 shadow-sm border border-gray-100 mb-3">
            <img src="{{ asset('images/logo-sekolah.png') }}" alt="Logo SMK Karya Nugraha" class="h-10 w-auto object-contain">
        </div>
        <h2 class="text-xl font-bold text-gray-900 tracking-tight">
            E-PRESKNBI — SMK KARYA NUGRAHA
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
</x-guest-layout>
