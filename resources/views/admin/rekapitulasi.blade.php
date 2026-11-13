<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Rekapitulasi Kehadiran</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Per kelas — {{ $periodeLabel }}: {{ $tanggalDisplay }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <select id="filterKelasRekap"
                        onchange="window.dispatchEvent(new CustomEvent('rekap:kelas-change', { detail: this.value }))"
                        class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        aria-label="Pilih kelas">
                    <option value="semua" @selected($kelas === 'semua')>Semua Kelas</option>
                    @foreach($classes as $itemKelas)
                        <option value="{{ $itemKelas->slug }}" @selected($kelas === $itemKelas->slug)>{{ $itemKelas->name }}</option>
                    @endforeach
                </select>
                <select id="filterPeriodeRekap"
                        onchange="window.location.href='{{ route('admin.rekapitulasi') }}?periode=' + encodeURIComponent(this.value) + '&tanggal={{ $tanggal }}&kelas=' + encodeURIComponent(document.getElementById('filterKelasRekap')?.value || '{{ $kelas }}')"
                        class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        aria-label="Pilih periode">
                    <option value="hari" @selected($periode === 'hari')>Hari Ini</option>
                    <option value="minggu" @selected($periode === 'minggu')>Minggu Ini</option>
                    <option value="bulan" @selected($periode === 'bulan')>Bulan Ini</option>
                </select>
                <form method="GET" action="{{ route('admin.rekapitulasi') }}" class="flex items-center">
                    <input type="hidden" name="periode" value="{{ $periode }}">
                    <input type="hidden" name="kelas" id="inputHiddenKelasRekap" value="{{ $kelas }}">
                    <input type="date" name="tanggal" value="{{ $tanggal }}" onchange="this.form.submit()"
                           class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
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
                <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-1">
                        Kehadiran <span x-text="kelasLabel">{{ $kelasLabel }}</span> — {{ $periodeLabel }}
                    </h2>
                    <p class="text-xs text-gray-400 mb-4">Batang menunjukkan jumlah siswa per status.</p>
                    <div class="relative" style="height: 340px;">
                        <canvas id="grafikKehadiran"></canvas>
                    </div>
                    <p x-show="error" x-cloak class="mt-3 text-xs font-medium text-rose-600" x-text="error"></p>
                </div>

                <div class="space-y-4">
                    <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-5">
                        <div class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Hadir</div>
                        <div class="text-4xl font-black text-emerald-600 mt-1" x-text="total.hadir">0</div>
                    </div>
                    <div class="bg-white rounded-xl border border-amber-200 shadow-sm p-5">
                        <div class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Terlambat</div>
                        <div class="text-4xl font-black text-amber-600 mt-1" x-text="total.terlambat">0</div>
                    </div>
                    <div class="bg-white rounded-xl border border-rose-200 shadow-sm p-5">
                        <div class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Alpa</div>
                        <div class="text-4xl font-black text-rose-600 mt-1" x-text="total.alpa">0</div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-400 leading-relaxed border-t border-gray-200 pt-4">
                <strong>Catatan:</strong> Hadir / Terlambat / Izin / Sakit dihitung dari catatan pada rentang periode terpilih — Hadir/Terlambat ditentukan otomatis oleh jam server dibandingkan jam masuk kelas saat siswa tap (sama dengan aturan rekap harian); Izin/Sakit dari halaman "Catat Izin / Sakit". Alpa = hari sekolah (Senin–Jumat) yang sudah lewat dalam rentang − Hadir − Terlambat − Izin − Sakit. Data diambil langsung dari database.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        function rekapGrafik() {
            let chartInstance = null;

            return {
                kelas: @js($kelas),
                kelasLabel: @js($kelasLabel),
                total: { hadir: 0, terlambat: 0, alpa: 0 },
                error: '',

                initGrafik() {
                    window.addEventListener('rekap:kelas-change', (e) => {
                        this.gantiKelas(e.detail || 'semua');
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
                                    y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: 'Jumlah Siswa' } },
                                },
                                plugins: {
                                    legend: { position: 'bottom' },
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
