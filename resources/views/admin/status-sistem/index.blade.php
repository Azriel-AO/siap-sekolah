<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">Status Sistem</h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-slate-400 mt-0.5">Pantau kondisi Box PC, server database, dan sensor sidik jari tanpa membuka terminal.</p>
            </div>
            <a href="{{ route('admin.status-sistem') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Muat ulang
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="statusSistemApp()" x-init="cekWebcam()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- GitHub Status Style Header Metrics: Uptime (3d) & Availability (3d) + 72-Hour Grid --}}
            <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 dark:border-slate-700 pb-5">
                    <div class="grid grid-cols-2 gap-6 sm:gap-10">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Uptime (3d)</div>
                            <div class="text-3xl sm:text-4xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
                                {{ number_format($uptime3d, 1) }}%
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">Availability (3d)</div>
                            <div class="text-3xl sm:text-4xl font-black {{ $availability3d >= 95 ? 'text-emerald-600 dark:text-emerald-400' : ($availability3d >= 70 ? 'text-amber-500 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }} mt-1">
                                {{ number_format($availability3d, 1) }}%
                            </div>
                        </div>
                    </div>
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-bold {{ $statusSensor['conectado'] && $rfidAktif ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800' }}">
                        <span class="w-2.5 h-2.5 rounded-full {{ $statusSensor['conectado'] && $rfidAktif ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        <span>{{ $statusSensor['conectado'] && $rfidAktif ? 'Semua Layanan Operasional' : 'Sebagian Perangkat Perlu Perhatian' }}</span>
                    </div>
                </div>

                {{-- Grid 72 Kotak Kecil (3 Hari x 24 Jam) --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-slate-400">
                        <span class="font-bold text-gray-800 dark:text-slate-200">Riwayat Kesehatan 72 Jam (3 Hari × 24 Jam)</span>
                        <span>Per jam (WIB)</span>
                    </div>

                    <div class="space-y-2">
                        @foreach($daysGrid as $dayRow)
                            <div class="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-3">
                                <span class="w-28 shrink-0 text-[11px] font-semibold text-gray-500 dark:text-slate-400">
                                    {{ $dayRow['label'] }}
                                </span>
                                <div class="flex-1 grid grid-cols-12 gap-1" style="grid-template-columns: repeat(24, minmax(0, 1fr));">
                                    @foreach($dayRow['slots'] as $slot)
                                        @php
                                            $boxColor = match ($slot['status']) {
                                                'up' => 'bg-emerald-500 hover:bg-emerald-600',
                                                'degraded' => 'bg-amber-400 hover:bg-amber-500',
                                                default => 'bg-rose-500 hover:bg-rose-600',
                                            };
                                        @endphp
                                        <div class="h-6 rounded-sm {{ $boxColor }} transition-colors cursor-default"
                                             title="{{ $slot['day_label'] }} {{ $slot['hour_label'] }} - {{ $slot['availability'] }}% ({{ $slot['note'] }})"></div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-2 text-[11px] text-gray-500 dark:text-slate-400">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-emerald-500 inline-block"></span>
                            Semua device normal
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-amber-400 inline-block"></span>
                            Degraded (1+ device bermasalah)
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-rose-500 inline-block"></span>
                            Server down / critical
                        </span>
                    </div>
                </div>

                {{-- Line Chart: Availability over the last 24 hours --}}
                <div class="pt-4 border-t border-gray-100 dark:border-slate-700 space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-slate-100">Availability over the last 24 hours</h2>
                        <span class="text-xs font-mono text-gray-500 dark:text-slate-400" x-text="hoveredPoint ? (hoveredPoint.hour_label + ' - ' + hoveredPoint.availability + '%') : '24 jam terakhir'">24 jam terakhir</span>
                    </div>

                    <div class="rounded-lg border border-gray-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/60 p-3">
                        <svg viewBox="0 0 760 140" class="w-full h-auto max-h-40 overflow-visible" role="img" aria-label="Grafik availability 24 jam terakhir">
                            <defs>
                                <linearGradient id="availabilityAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0.02" />
                                </linearGradient>
                            </defs>

                            {{-- Horizontal Gridlines --}}
                            <line x1="40" y1="16" x2="746" y2="16" class="stroke-slate-200 dark:stroke-slate-700" stroke-dasharray="3 3" />
                            <line x1="40" y1="70" x2="746" y2="70" class="stroke-slate-200 dark:stroke-slate-700" stroke-dasharray="3 3" />
                            <line x1="40" y1="124" x2="746" y2="124" class="stroke-slate-200 dark:stroke-slate-700" />

                            <text x="32" y="20" text-anchor="end" fill="#94a3b8" font-size="10" font-family="monospace">100%</text>
                            <text x="32" y="73" text-anchor="end" fill="#94a3b8" font-size="10" font-family="monospace">50%</text>
                            <text x="32" y="127" text-anchor="end" fill="#94a3b8" font-size="10" font-family="monospace">0%</text>

                            @if($areaPoints !== '')
                                <polygon points="{{ $areaPoints }}" fill="url(#availabilityAreaGrad)" />
                            @endif
                            @if($polylinePoints !== '')
                                <polyline fill="none" stroke="#10b981" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" points="{{ $polylinePoints }}" />
                            @endif

                            @foreach($chartPoints as $pt)
                                @php
                                    $dotFill = match ($pt['status']) {
                                        'up' => '#10b981',
                                        'degraded' => '#f59e0b',
                                        default => '#ef4444',
                                    };
                                @endphp
                                <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="3.5" fill="{{ $dotFill }}" stroke-width="1.5"
                                        class="cursor-pointer stroke-white dark:stroke-slate-900"
                                        @mouseenter="hoveredPoint = @js($pt)"
                                        @mouseleave="hoveredPoint = null">
                                    <title>{{ $pt['hour_label'] }} - {{ $pt['availability'] }}% ({{ $pt['note'] }})</title>
                                </circle>
                            @endforeach
                        </svg>
                        <div class="flex justify-between text-[10px] font-mono text-gray-400 dark:text-slate-400 px-3 mt-1">
                            <span>{{ $slots24[0]['hour_label'] ?? '' }}</span>
                            <span>{{ $slots24[6]['hour_label'] ?? '' }}</span>
                            <span>{{ $slots24[12]['hour_label'] ?? '' }}</span>
                            <span>{{ $slots24[18]['hour_label'] ?? '' }}</span>
                            <span>Sekarang</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Status Real-Time Perangkat (Card Kecil dengan Dot Indicator Hijau/Merah) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Device 1: Server & SQLite --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-emerald-200 dark:border-emerald-800/60 shadow-sm p-5 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-slate-300">Server &amp; Database</span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300">
                            Aktif
                        </span>
                    </div>
                    <h2 class="text-base font-extrabold text-gray-900 dark:text-slate-100">Server Web &amp; SQLite Siap</h2>
                    <div class="pt-2 border-t border-gray-100 dark:border-slate-700 text-[11px] text-gray-500 dark:text-slate-400 flex justify-between">
                        <span>Kelas: <strong class="text-gray-800 dark:text-slate-200">{{ $jumlahKelas }}</strong></span>
                        <span>Siswa: <strong class="text-gray-800 dark:text-slate-200">{{ $jumlahSiswa }}</strong></span>
                    </div>
                </div>

                {{-- Device 2: Fingerprint (Bridge / Mock) --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border {{ $statusSensor['conectado'] ? 'border-emerald-200 dark:border-emerald-800/60' : 'border-rose-300 dark:border-rose-800/60' }} shadow-sm p-5 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $statusSensor['conectado'] ? 'bg-emerald-500' : 'bg-rose-500' }} shrink-0"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-slate-300">Fingerprint</span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $driver === 'bridge' ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-800 dark:text-indigo-300' : 'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300' }}">
                            {{ strtoupper($driver) }}
                        </span>
                    </div>

                    @if($statusSensor['conectado'])
                        <h2 class="text-base font-extrabold text-emerald-700 dark:text-emerald-400">Terhubung - {{ $statusSensor['modelo'] ?? 'ZK9500' }}</h2>
                        <p class="text-[11px] text-gray-500 dark:text-slate-400">
                            {{ $driver === 'bridge' ? 'Bridge lokal ' . $baseUrl . ' aktif.' : 'Mode simulasi (mock) aktif.' }}
                        </p>
                    @else
                        <h2 class="text-base font-extrabold text-rose-700 dark:text-rose-400">Terputus - Sensor/bridge belum aktif</h2>
                        <p class="text-[11px] text-rose-700 dark:text-rose-300">
                            Periksa USB ZK9500 atau jalankan <code class="font-mono">box\mulai-sistem.bat</code>.
                        </p>
                    @endif
                </div>

                {{-- Device 3: Kamera / Webcam --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border shadow-sm p-5 space-y-2.5 transition-colors"
                     :class="webcamState === 'detected' ? 'border-emerald-200 dark:border-emerald-800/60' : 'border-rose-300 dark:border-rose-800/60'">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                  :class="webcamState === 'detected' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-slate-300">Kamera / Webcam</span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                              :class="webcamState === 'detected' ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-900/50 text-rose-800 dark:text-rose-300'"
                              x-text="webcamState === 'detected' ? 'Aktif' : (webcamState === 'checking' ? 'Cek…' : 'Mati')">
                            Cek…
                        </span>
                    </div>
                    <h2 class="text-base font-extrabold"
                        :class="webcamState === 'detected' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'"
                        x-text="webcamState === 'detected' ? ('Webcam: Terdeteksi (' + webcamCount + ' device)') : (webcamState === 'checking' ? 'Memeriksa webcam…' : 'Webcam: Tidak terdeteksi')">
                        Memeriksa webcam…
                    </h2>
                    <p class="text-[11px]"
                       :class="webcamState === 'detected' ? 'text-gray-500 dark:text-slate-400' : 'text-rose-700 dark:text-rose-300'">
                        <span x-show="webcamState === 'detected'">Siap untuk Face ID &amp; foto bukti.</span>
                        <span x-show="webcamState !== 'detected'">Pastikan webcam USB terpasang &amp; diizinkan.</span>
                    </p>
                </div>

                {{-- Device 4: RFID Reader --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border {{ $rfidAktif ? 'border-emerald-200 dark:border-emerald-800/60' : 'border-rose-300 dark:border-rose-800/60' }} shadow-sm p-5 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $rfidAktif ? 'bg-emerald-500' : 'bg-rose-500' }} shrink-0"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-slate-300">RFID Reader</span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $rfidAktif ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-900/50 text-rose-800 dark:text-rose-300' }}">
                            {{ $rfidAktif ? 'Aktif' : 'Mati' }}
                        </span>
                    </div>

                    @if($rfidAktif)
                        <h2 class="text-base font-extrabold text-emerald-700 dark:text-emerald-400">RFID Reader: Aktif</h2>
                        <p class="text-[11px] text-gray-500 dark:text-slate-400">
                            Tap terakhir: {{ $lastCardTapAt?->format('d M H:i') }} WIB.
                        </p>
                    @else
                        <h2 class="text-base font-extrabold text-rose-700 dark:text-rose-400">RFID Reader: Belum terdeteksi</h2>
                        <p class="text-[11px] text-rose-700 dark:text-rose-300">
                            Belum ada tap kartu dalam 24 jam terakhir.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Panduan Singkat Petugas / Guru Piket --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 dark:text-slate-100 mb-2">Panduan Cepat Jika Ada Indikator Merah</h3>
                <ol class="list-decimal list-inside text-xs text-gray-600 dark:text-slate-300 space-y-1.5">
                    <li>Periksa kabel USB sensor sidik jari ZK9500, webcam, dan RFID reader pada Box PC (cabut lalu colok kembali ke port USB).</li>
                    <li>Tunggu sekitar 20 detik (watchdog otomatis menyalakan ulang bridge), lalu klik tombol <strong>Muat ulang</strong> di kanan atas.</li>
                    <li>Bila masih merah, restart Box PC atau klik dua kali file <code class="font-mono">box\mulai-sistem.bat</code> di folder aplikasi.</li>
                </ol>
            </div>

        </div>
    </div>

    <script>
        function statusSistemApp() {
            return {
                webcamState: 'checking',
                webcamCount: 0,
                hoveredPoint: null,

                async cekWebcam() {
                    try {
                        if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') {
                            this.webcamState = 'none';
                            this.webcamCount = 0;
                            return;
                        }

                        const devices = await navigator.mediaDevices.enumerateDevices();
                        const videoDevices = (devices || []).filter((device) => device.kind === 'videoinput');
                        this.webcamCount = videoDevices.length;
                        this.webcamState = videoDevices.length > 0 ? 'detected' : 'none';
                    } catch {
                        this.webcamState = 'none';
                        this.webcamCount = 0;
                    }
                },
            };
        }
    </script>
</x-app-layout>
