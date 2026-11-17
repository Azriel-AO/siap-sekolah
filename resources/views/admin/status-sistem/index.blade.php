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
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-bold {{ $statusSensor['conectado'] ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800' }}">
                        <span class="w-2.5 h-2.5 rounded-full {{ $statusSensor['conectado'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        <span>{{ $statusSensor['conectado'] ? 'Semua Layanan Operasional' : 'Sebagian Perangkat Perlu Perhatian' }}</span>
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

                {{-- Device 4: RFID Reader (USB HID Keyboard Wedge) --}}
                <div class="bg-white dark:bg-slate-800 rounded-xl border shadow-sm p-5 space-y-2.5 transition-colors"
                     :class="{
                         'border-emerald-200 dark:border-emerald-800/60': rfidState === 'verified',
                         'border-amber-300 dark:border-amber-700/70': rfidState === 'listening' || rfidState === 'no_tap',
                         'border-sky-200 dark:border-sky-800/60': rfidState === 'standby'
                     }">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                  :class="{
                                      'bg-emerald-500': rfidState === 'verified',
                                      'bg-amber-500 animate-pulse': rfidState === 'listening',
                                      'bg-amber-400': rfidState === 'no_tap',
                                      'bg-sky-500': rfidState === 'standby'
                                  }"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-slate-300 truncate">RFID Reader</span>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                                  :class="{
                                      'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300': rfidState === 'verified',
                                      'bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300': rfidState === 'listening' || rfidState === 'no_tap',
                                      'bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-300': rfidState === 'standby'
                                  }"
                                  x-text="rfidState === 'verified' ? 'Aktif' : (rfidState === 'listening' ? ('Listening (' + rfidCountdown + 's)') : 'Standby')">
                                Standby
                            </span>
                            <button type="button"
                                    @click="mulaiTesRfid()"
                                    :disabled="rfidState === 'listening'"
                                    class="px-2 py-0.5 rounded-md border text-[11px] font-bold transition disabled:opacity-60 disabled:cursor-wait"
                                    :class="rfidState === 'listening'
                                        ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300'
                                        : 'border-indigo-200 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300'">
                                <span x-text="rfidState === 'listening' ? ('Listening (' + rfidCountdown + 's)…') : 'Tes RFID'">Tes RFID</span>
                            </button>
                        </div>
                    </div>

                    <input type="text"
                           x-ref="rfidTestInput"
                           x-model="rfidTestBuffer"
                           @input="jadwalkanTangkapRfid()"
                           @keydown.enter.prevent="tangkapInputRfid()"
                           autocomplete="off"
                           aria-label="Input pengujian RFID"
                           class="sr-only"
                           tabindex="-1">

                    <h2 class="text-base font-extrabold"
                        :class="{
                            'text-emerald-700 dark:text-emerald-400': rfidState === 'verified',
                            'text-amber-700 dark:text-amber-300': rfidState === 'listening' || rfidState === 'no_tap',
                            'text-sky-700 dark:text-sky-400': rfidState === 'standby'
                        }"
                        x-text="rfidState === 'verified'
                            ? 'Terhubung / Berfungsi'
                            : (rfidState === 'listening'
                                ? ('Listening… Tempelkan Kartu (' + rfidCountdown + ' detik)')
                                : (rfidState === 'no_tap'
                                    ? 'Standby (Belum ada tap yang terdeteksi)'
                                    : 'Siap / Standby (Menunggu Tap)'))">
                        Siap / Standby (Menunggu Tap)
                    </h2>

                    <p class="text-[11px]"
                       :class="rfidState === 'no_tap' || rfidState === 'listening' ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-slate-400'">
                        <template x-if="rfidState === 'verified'">
                            <span>Input terbaca (<code class="font-mono font-bold text-emerald-700 dark:text-emerald-300" x-text="rfidLastUid"></code>). Klik <strong>Tes RFID</strong> untuk uji ulang.</span>
                        </template>
                        <template x-if="rfidState === 'listening'">
                            <span>Silakan tempelkan kartu ke RFID reader dalam 5 detik.</span>
                        </template>
                        <template x-if="rfidState === 'no_tap'">
                            <span>Tidak ada input kartu selama tes 5 detik. Alat tetap dalam mode standby (USB HID).</span>
                        </template>
                        <template x-if="rfidState === 'standby'">
                            <span>Mode {{ $rfidMode ?? 'USB HID (Keyboard Wedge)' }}. Klik <strong>Tes RFID</strong> lalu tap kartu untuk menguji pembacaan.</span>
                        </template>
                    </p>
                </div>
            </div>

            {{-- Panduan Singkat Petugas / Guru Piket --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 dark:text-slate-100 mb-2">Panduan Cepat Status &amp; Pengujian Perangkat</h3>
                <ol class="list-decimal list-inside text-xs text-gray-600 dark:text-slate-300 space-y-1.5">
                    <li>Periksa kabel USB sensor sidik jari ZK9500, webcam, dan RFID reader pada Box PC (cabut lalu colok kembali ke port USB).</li>
                    <li>Untuk memastikan <strong>RFID Reader (USB HID)</strong> bekerja, klik tombol <strong>Tes RFID</strong> lalu segera tempelkan kartu ke reader dalam 5 detik.</li>
                    <li>Bila indikator Fingerprint merah, tunggu sekitar 20 detik (watchdog otomatis menyalakan ulang bridge), klik <strong>Muat ulang</strong>, atau jalankan <code class="font-mono">box\mulai-sistem.bat</code>.</li>
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
                rfidState: 'standby',
                rfidCountdown: 5,
                rfidTestBuffer: '',
                rfidLastUid: '',
                _rfidTimer: null,
                _rfidFlushTimer: null,
                _rfidKeyHandler: null,

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

                bersihkanTesRfid() {
                    if (this._rfidTimer) {
                        clearInterval(this._rfidTimer);
                        this._rfidTimer = null;
                    }
                    if (this._rfidFlushTimer) {
                        clearTimeout(this._rfidFlushTimer);
                        this._rfidFlushTimer = null;
                    }
                    if (this._rfidKeyHandler) {
                        window.removeEventListener('keydown', this._rfidKeyHandler);
                        this._rfidKeyHandler = null;
                    }
                },

                mulaiTesRfid() {
                    this.bersihkanTesRfid();
                    this.rfidTestBuffer = '';
                    this.rfidCountdown = 5;
                    this.rfidState = 'listening';

                    this.$nextTick(() => {
                        if (this.$refs.rfidTestInput) {
                            this.$refs.rfidTestInput.focus({ preventScroll: true });
                        }
                    });

                    this._rfidKeyHandler = (event) => {
                        if (this.rfidState !== 'listening') {
                            return;
                        }
                        if (event.ctrlKey || event.altKey || event.metaKey) {
                            return;
                        }
                        if (event.target === this.$refs.rfidTestInput) {
                            return;
                        }
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            this.tangkapInputRfid();
                            return;
                        }
                        if (event.key && event.key.length === 1) {
                            this.rfidTestBuffer += event.key;
                            this.jadwalkanTangkapRfid();
                        }
                    };
                    window.addEventListener('keydown', this._rfidKeyHandler);

                    this._rfidTimer = setInterval(() => {
                        if (this.rfidState !== 'listening') {
                            this.bersihkanTesRfid();
                            return;
                        }
                        this.rfidCountdown -= 1;
                        if (this.rfidCountdown <= 0) {
                            this.tangkapInputRfid(true);
                        }
                    }, 1000);
                },

                jadwalkanTangkapRfid() {
                    if (this.rfidState !== 'listening') {
                        return;
                    }
                    if (this._rfidFlushTimer) {
                        clearTimeout(this._rfidFlushTimer);
                    }
                    const nilai = (this.rfidTestBuffer || '').trim();
                    if (nilai.length >= 6) {
                        this._rfidFlushTimer = setTimeout(() => {
                            this.tangkapInputRfid();
                        }, 250);
                    }
                },

                tangkapInputRfid(waktuHabis = false) {
                    if (this.rfidState !== 'listening') {
                        return;
                    }
                    const uid = (this.rfidTestBuffer || '').trim();
                    if (uid.length >= 4) {
                        this.bersihkanTesRfid();
                        this.rfidLastUid = uid;
                        this.rfidTestBuffer = '';
                        this.rfidState = 'verified';
                        return;
                    }
                    if (waktuHabis) {
                        this.bersihkanTesRfid();
                        this.rfidTestBuffer = '';
                        this.rfidState = 'no_tap';
                    }
                },
            };
        }
    </script>
</x-app-layout>
