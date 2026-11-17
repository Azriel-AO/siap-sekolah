@props(['compact' => false])

<button type="button"
        x-data
        role="switch"
        :aria-checked="$store.theme?.dark ?? false"
        aria-label="Ubah tema terang atau gelap"
        @click="$store.theme ? $store.theme.toggle() : window.toggleTheme()"
        {{ $attributes->merge([
            'class' => 'group relative inline-flex items-center gap-2 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 px-2 py-1 text-xs font-semibold text-slate-600 dark:text-slate-300 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200'
        ]) }}
        title="Ganti Mode Gelap / Terang">
    <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full bg-slate-300 dark:bg-indigo-600 transition-colors duration-200">
        <span class="inline-flex h-4 w-4 transform items-center justify-center rounded-full bg-white shadow transition-transform duration-200"
              :class="($store.theme && $store.theme.dark) ? 'translate-x-4' : 'translate-x-0.5'">
            <svg x-show="!($store.theme && $store.theme.dark)" class="h-2.5 w-2.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
            </svg>
            <svg x-show="$store.theme && $store.theme.dark" x-cloak class="h-2.5 w-2.5 text-indigo-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
            </svg>
        </span>
    </span>
    @unless($compact)
        <span class="hidden xl:inline pr-1 text-[11px] font-semibold text-slate-600 dark:text-slate-300"
              x-text="($store.theme && $store.theme.dark) ? 'Gelap' : 'Terang'">Terang</span>
    @endunless
</button>
