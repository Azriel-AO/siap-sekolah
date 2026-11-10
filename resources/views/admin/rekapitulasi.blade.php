<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Rekapitulasi Kehadiran</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Per kelas — {{ $tanggalDisplay }}</p>
            </div>
            <a href="{{ route('guru.rekap') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-sm transition">
                Buka Rekap Harian
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="rekapGrafik()" x-init="muatGrafik()">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-1">Kehadiran per Kelas — Hari Ini</h2>
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
                        <div class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Tidak Hadir</div>
                        <div class="text-4xl font-black text-rose-600 mt-1" x-text="total.tidakHadir">0</div>
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-gray-400 leading-relaxed border-t border-gray-200 pt-4">
                <strong>Catatan:</strong> Hadir / Terlambat dihitung dari status yang tercatat di absensi
                hari ini — status ditentukan otomatis oleh jam server dibandingkan jam masuk
                masing-masing kelas saat siswa tap (sama dengan aturan rekap harian).
                Tidak Hadir = total siswa kelas − Hadir − Terlambat (termasuk yang belum tap hari ini).
                Data diambil langsung dari catatan absensi hari ini.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        function rekapGrafik() {
            return {
                total: { hadir: 0, terlambat: 0, tidakHadir: 0 },
                error: '',

                async muatGrafik() {
                    try {
                        const res = await fetch("{{ route('admin.rekap.grafik') }}", {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (!res.ok) {
                            this.error = 'Gagal memuat data grafik. Coba muat ulang halaman.';
                            return;
                        }
                        const data = await res.json();

                        this.total.hadir = data.hadir.reduce((a, b) => a + b, 0);
                        this.total.terlambat = data.terlambat.reduce((a, b) => a + b, 0);
                        this.total.tidakHadir = data.tidak_hadir.reduce((a, b) => a + b, 0);

                        const ctx = document.getElementById('grafikKehadiran');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: data.labels,
                                datasets: [
                                    { label: 'Hadir', data: data.hadir, backgroundColor: '#10b981' },
                                    { label: 'Terlambat', data: data.terlambat, backgroundColor: '#f59e0b' },
                                    { label: 'Tidak Hadir', data: data.tidak_hadir, backgroundColor: '#f43f5e' },
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
