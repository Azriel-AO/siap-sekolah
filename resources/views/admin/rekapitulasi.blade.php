<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">Rekapitulasi Kehadiran</h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-slate-400 mt-0.5">Per kelas - {{ $periodeLabel }}: {{ $tanggalDisplay }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <select id="filterKelasRekap"
                        onchange="window.dispatchEvent(new CustomEvent('rekap:kelas-change', { detail: this.value }))"
                        class="rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        aria-label="Pilih kelas">
                    <option value="semua" @selected($kelas === 'semua')>Semua Kelas</option>
                    @foreach($classes as $itemKelas)
                        <option value="{{ $itemKelas->slug }}" @selected($kelas === $itemKelas->slug)>{{ $itemKelas->name }}</option>
                    @endforeach
                </select>
                <select id="filterPeriodeRekap"
                        onchange="window.location.href='{{ route('admin.rekapitulasi') }}?periode=' + encodeURIComponent(this.value) + '&tanggal=' + encodeURIComponent(document.getElementById('filterTanggalRekap')?.value || '{{ $tanggal }}') + '&kelas=' + encodeURIComponent(document.getElementById('filterKelasRekap')?.value || '{{ $kelas }}')"
                        class="rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        aria-label="Pilih periode">
                    <option value="hari" @selected($periode === 'hari')>Hari Ini</option>
                    <option value="minggu" @selected($periode === 'minggu')>Minggu Ini</option>
                    <option value="bulan" @selected($periode === 'bulan')>Bulan Ini</option>
                </select>
                <form method="GET" action="{{ route('admin.rekapitulasi') }}" class="flex items-center">
                    <input type="hidden" name="periode" value="{{ $periode }}">
                    <input type="hidden" name="kelas" id="inputHiddenKelasRekap" value="{{ $kelas }}">
                    <input type="date" id="filterTanggalRekap" name="tanggal" value="{{ $tanggal }}"
                           onkeydown="if (event.key === 'Enter') { if (/^(19\d\d|20\d\d|2100)-\d{2}-\d{2}$/.test(this.value)) this.form.submit(); } else { this.dataset.typing = '1'; }"
                           onchange="if (this.dataset.typing !== '1' && /^(19\d\d|20\d\d|2100)-\d{2}-\d{2}$/.test(this.value)) this.form.submit()"
                           onblur="if (this.dataset.typing === '1') { delete this.dataset.typing; if (/^(19\d\d|20\d\d|2100)-\d{2}-\d{2}$/.test(this.value) && this.value !== this.defaultValue) this.form.submit(); }"
                           class="rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                           aria-label="Tanggal acuan periode">
                </form>
                <a id="linkRekapHarian"
                   href="{{ route('guru.rekap', array_filter(['periode' => $periode, 'tanggal' => $tanggal, 'kelas' => $kelas !== 'semua' ? $kelas : null])) }}"
                   class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">
                    Buka Rekap Harian
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="rekapGrafik()" x-init="initGrafik()">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100 mb-1">
                        Kehadiran <span x-text="kelasLabel">{{ $kelasLabel }}</span> - {{ $periodeLabel }}
                    </h2>
                    <p class="text-xs text-gray-400 dark:text-slate-400 mb-4">Batang menunjukkan jumlah siswa per status.</p>
                    <div class="relative" style="height: 340px;">
                        <canvas id="grafikKehadiran"></canvas>
                    </div>
                    <p x-show="error" x-cloak class="mt-3 text-xs font-medium text-rose-600 dark:text-rose-400" x-text="error"></p>
                </div>

                <div class="space-y-4">
                    <div class="bg-white dark:bg-slate-800 rounded-xl border border-emerald-200 dark:border-emerald-800/60 shadow-sm p-5">
                        <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Hadir</div>
                        <div class="text-4xl font-black text-emerald-600 dark:text-emerald-400 mt-1" x-text="total.hadir">0</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl border border-amber-200 dark:border-amber-800/60 shadow-sm p-5">
                        <div class="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Terlambat</div>
                        <div class="text-4xl font-black text-amber-600 dark:text-amber-400 mt-1" x-text="total.terlambat">0</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl border border-rose-200 dark:border-rose-800/60 shadow-sm p-5">
                        <div class="text-xs font-semibold text-rose-700 dark:text-rose-400 uppercase tracking-wider">Alpa</div>
                        <div class="text-4xl font-black text-rose-600 dark:text-rose-400 mt-1" x-text="total.alpa">0</div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-400 dark:text-slate-400 leading-relaxed border-t border-gray-200 dark:border-slate-700 pt-4">
                <strong>Catatan:</strong> Hadir / Terlambat / Izin / Sakit dihitung dari catatan pada rentang periode terpilih. Hadir/Terlambat ditentukan otomatis oleh jam server dibandingkan jam masuk kelas saat siswa tap (sama dengan aturan rekap harian); Izin/Sakit dari halaman "Catat Izin / Sakit". Alpa = hari sekolah (Senin–Jumat) yang sudah lewat dalam rentang − Hadir − Terlambat − Izin − Sakit. Data diambil langsung dari database.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        function rekapGrafik() {
            let chartInstance = null;

            const themeColors = () => {
                const isDark = document.documentElement.classList.contains('dark');
                return {
                    tick: isDark ? '#cbd5e1' : '#475569',
                    grid: isDark ? '#334155' : '#e2e8f0',
                };
            };

            return {
                kelas: @js($kelas),
                kelasLabel: @js($kelasLabel),
                total: { hadir: 0, terlambat: 0, alpa: 0 },
                error: '',

                initGrafik() {
                    window.addEventListener('rekap:kelas-change', (e) => {
                        this.gantiKelas(e.detail || 'semua');
                    });
                    window.addEventListener('theme:changed', () => {
                        if (!chartInstance) return;
                        const c = themeColors();
                        chartInstance.options.scales.x.ticks.color = c.tick;
                        chartInstance.options.scales.x.grid.color = c.grid;
                        chartInstance.options.scales.y.ticks.color = c.tick;
                        chartInstance.options.scales.y.grid.color = c.grid;
                        chartInstance.options.scales.y.title.color = c.tick;
                        chartInstance.options.plugins.legend.labels.color = c.tick;
                        chartInstance.update();
                    });
                    this.muatGrafik();
                },

                gantiKelas(slugBaru) {
                    this.kelas = slugBaru || 'semua';

                    const hiddenInput = document.getElementById('inputHiddenKelasRekap');
                    if (hiddenInput) {
                        hiddenInput.value = this.kelas;
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.set('kelas', this.kelas);
                    window.history.replaceState({}, '', url.toString());

                    const linkHarian = document.getElementById('linkRekapHarian');
                    if (linkHarian) {
                        const urlHarian = new URL(linkHarian.href, window.location.origin);
                        if (this.kelas && this.kelas !== 'semua') {
                            urlHarian.searchParams.set('kelas', this.kelas);
                        } else {
                            urlHarian.searchParams.delete('kelas');
                        }
                        linkHarian.href = urlHarian.toString();
                    }

                    this.muatGrafik();
                },

                async muatGrafik() {
                    this.error = '';
                    try {
                        const params = new URLSearchParams({
                            periode: @js($periode),
                            tanggal: @js($tanggal),
                            kelas: this.kelas || 'semua',
                        });
                        const res = await fetch("{{ route('admin.rekap.grafik') }}?" + params.toString(), {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (!res.ok) {
                            this.error = 'Gagal memuat data grafik. Coba muat ulang halaman.';
                            return;
                        }
                        const data = await res.json();

                        this.kelasLabel = data.kelas_label || (this.kelas === 'semua' ? 'Semua Kelas' : this.kelas);
                        this.total.hadir = data.hadir.reduce((a, b) => a + b, 0);
                        this.total.terlambat = data.terlambat.reduce((a, b) => a + b, 0);
                        this.total.alpa = data.alpa.reduce((a, b) => a + b, 0);

                        const ctx = document.getElementById('grafikKehadiran');
                        if (chartInstance) {
                            chartInstance.destroy();
                            chartInstance = null;
                        }
                        const c = themeColors();
                        chartInstance = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: data.labels,
                                datasets: [
                                    { label: 'Hadir', data: data.hadir, backgroundColor: '#10b981' },
                                    { label: 'Terlambat', data: data.terlambat, backgroundColor: '#f59e0b' },
                                    { label: 'Izin', data: data.izin, backgroundColor: '#818cf8' },
                                    { label: 'Sakit', data: data.sakit, backgroundColor: '#3b82f6' },
                                    { label: 'Alpa', data: data.alpa, backgroundColor: '#f43f5e' },
                                ],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    x: { ticks: { color: c.tick }, grid: { color: c.grid } },
                                    y: { beginAtZero: true, ticks: { stepSize: 1, color: c.tick }, grid: { color: c.grid }, title: { display: true, text: 'Jumlah Siswa', color: c.tick } },
                                },
                                plugins: {
                                    legend: { position: 'bottom', labels: { color: c.tick } },
                                },
                            },
                        });
                    } catch (e) {
                        this.error = 'Gagal memuat data grafik. Periksa koneksi Anda.';
                    }
                }
            };
        }
    </script>
</x-app-layout>
