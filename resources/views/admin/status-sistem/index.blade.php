<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Status Sistem</h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Pantau kondisi Box PC, server database, dan sensor sidik jari tanpa membuka terminal.</p>
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {{-- Kartu 1: Server Aplikasi & Database --}}
                <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Server &amp; Database</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                            Aktif
                        </span>
                    </div>
                    <h2 class="text-lg font-extrabold text-gray-900">Server Web &amp; SQLite Siap</h2>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Halaman ini terbuka menandakan layanan web Laravel (port 8000) dan database lokal berjalan normal.
                    </p>
                    <div class="pt-2 border-t border-gray-100 text-xs text-gray-500 flex justify-between">
                        <span>Data Kelas: <strong class="text-gray-800">{{ $jumlahKelas }}</strong></span>
                        <span>Data Siswa: <strong class="text-gray-800">{{ $jumlahSiswa }}</strong></span>
                    </div>
                </div>

                {{-- Kartu 2: Driver Sidik Jari --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Driver Sidik Jari</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $driver === 'bridge' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ strtoupper($driver) }}
                        </span>
                    </div>
                    <h2 class="text-lg font-extrabold text-gray-900">
                        {{ $driver === 'bridge' ? 'Mode Produksi (Bridge ZK9500)' : 'Mode Simulasi (Mock)' }}
                    </h2>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        @if($driver === 'bridge')
                            Terhubung ke layanan lokal <code class="font-mono text-gray-800">{{ $baseUrl }}</code>. Tombol simulasi di layar kiosk disembunyikan.
                        @else
                            Menggunakan sensor virtual untuk pengujian otomatis tanpa perangkat fisik.
                        @endif
                    </p>
                </div>

                {{-- Kartu 3: Status Bridge & Sensor ZK9500 --}}
                <div class="bg-white rounded-xl border {{ $statusSensor['conectado'] ? 'border-emerald-200' : 'border-rose-300' }} shadow-sm p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Sensor / Bridge</span>
                        @if($statusSensor['conectado'])
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                Terhubung
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                Terputus
                            </span>
                        @endif
                    </div>

                    @if($statusSensor['conectado'])
                        <h2 class="text-lg font-extrabold text-emerald-700">Sensor Siap Digunakan</h2>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            Perangkat terdeteksi: <strong class="text-gray-900">{{ $statusSensor['modelo'] ?? 'ZK9500' }}</strong>.
                        </p>
                    @else
                        <h2 class="text-lg font-extrabold text-rose-700">Sensor/bridge belum aktif</h2>
                        <p class="text-xs text-rose-700 leading-relaxed">
                            Pastikan kabel USB sensor ZK9500 tertancap rapat, lalu klik dua kali <code class="font-mono">box\mulai-sistem.bat</code> atau restart Box PC.
                        </p>
                    @endif
                </div>

                {{-- Kartu 4: Kamera / Webcam (navigator.mediaDevices.enumerateDevices) --}}
                <div class="bg-white rounded-xl border shadow-sm p-6 space-y-3 transition-colors"
                     :class="webcamState === 'detected' ? 'border-emerald-200' : 'border-rose-300'">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Kamera / Webcam</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                              :class="webcamState === 'detected' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                              x-text="webcamState === 'detected' ? 'Aktif' : (webcamState === 'checking' ? 'Memeriksa…' : 'Mati')">
                            Memeriksa…
                        </span>
                    </div>
                    <h2 class="text-lg font-extrabold"
                        :class="webcamState === 'detected' ? 'text-emerald-700' : 'text-rose-700'"
                        x-text="webcamState === 'detected' ? ('Webcam: Terdeteksi (' + webcamCount + ' device)') : (webcamState === 'checking' ? 'Memeriksa webcam…' : 'Webcam: Tidak terdeteksi')">
                        Memeriksa webcam…
                    </h2>
                    <p class="text-xs leading-relaxed"
                       :class="webcamState === 'detected' ? 'text-gray-600' : 'text-rose-700'">
                        <span x-show="webcamState === 'detected'">Perangkat video input siap dipakai untuk Face ID dan foto bukti di kiosk maupun pendaftaran.</span>
                        <span x-show="webcamState !== 'detected'">Pastikan webcam USB terpasang dan izin kamera browser tidak diblokir.</span>
                    </p>
                </div>

                {{-- Kartu 5: RFID Reader (deteksi aktivitas tap kartu 24 jam terakhir) --}}
                <div class="bg-white rounded-xl border {{ $rfidAktif ? 'border-emerald-200' : 'border-rose-300' }} shadow-sm p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">RFID Reader</span>
                        @if($rfidAktif)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                Mati
                            </span>
                        @endif
                    </div>

                    @if($rfidAktif)
                        <h2 class="text-lg font-extrabold text-emerald-700">RFID Reader: Aktif</h2>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            Tap kartu terakhir tercatat pada <strong class="text-gray-900">{{ $lastCardTapAt?->format('d M Y H:i:s') }} WIB</strong> (dalam 24 jam terakhir).
                        </p>
                    @else
                        <h2 class="text-lg font-extrabold text-rose-700">RFID Reader: Belum terdeteksi</h2>
                        <p class="text-xs text-rose-700 leading-relaxed">
                            Belum ada aktivitas tap kartu dalam 24 jam terakhir. Pastikan scanner RFID USB terpasang (mode keyboard wedge).
                        </p>
                    @endif
                </div>
            </div>

            {{-- Panduan Singkat Petugas / Guru Piket --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-2">Panduan Cepat Jika Ada Indikator Merah</h3>
                <ol class="list-decimal list-inside text-xs text-gray-600 space-y-1.5">
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
