<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Riwayat Kehadiran Siswa
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    Data rekaman absensi mandiri pelajar SMK Karya Nugraha (KNBI)
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                    🎓 Akun Siswa Aktif
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Student Profile Badge -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <div class="w-14 h-14 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center font-black text-xl border-2 border-amber-300">
                        {{ strtoupper(substr($student->name, 0, 2)) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ $student->name }}</h2>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 mt-1">
                            <span>NIS: <strong class="text-gray-700 font-mono">{{ $student->nis }}</strong></span>
                            <span>•</span>
                            <span>Kelas: <strong class="text-indigo-600 font-semibold">{{ $student->schoolClass->name }}</strong></span>
                            <span>•</span>
                            <span>Kartu: <strong class="font-mono text-gray-700" title="UID: {{ $student->rfidCard?->card_uid ?? '-' }}">{{ $student->card_label ?? $student->rfidCard?->card_uid ?? '-' }}</strong></span>
                        </div>
                    </div>
                </div>

                <div class="text-left sm:text-right text-xs text-gray-500 bg-gray-50 sm:bg-transparent p-3 sm:p-0 rounded-lg">
                    <div>Total Riwayat: <strong>{{ $attendances->count() }} Hari Tercatat</strong></div>
                    <div class="text-emerald-600 font-medium mt-0.5">Kartu RFID Terdaftar Aktif</div>
                </div>
            </div>

            <!-- Attendance Records Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-5 border-b border-gray-200">
                    <h3 class="text-base font-bold text-gray-900">Catatan Absensi Tanggal</h3>
                    <p class="text-xs text-gray-500">Pencatatan waktu resmi dari server absensi sekolah</p>
                </div>

                @if($attendances->isEmpty())
                    <div class="p-10 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm font-medium">Belum ada riwayat absensi yang tercatat untuk Anda.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <th class="py-3.5 px-5">Tanggal</th>
                                    <th class="py-3.5 px-4 text-center">Jam Masuk</th>
                                    <th class="py-3.5 px-4 text-center">Jam Pulang</th>
                                    <th class="py-3.5 px-4 text-center">Status</th>
                                    <th class="py-3.5 px-4 text-right">Perangkat Kiosk</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                @foreach($attendances as $att)
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3.5 px-5 font-medium text-gray-900">
                                            {{ \Carbon\Carbon::parse($att->date)->translatedFormat('l, d F Y') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-bold text-gray-800">
                                            {{ $att->check_in_at ? substr($att->check_in_at, 0, 5) . ' WIB' : '-' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-bold text-gray-800">
                                            {{ $att->check_out_at ? substr($att->check_out_at, 0, 5) . ' WIB' : '-' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            @if($att->status === 'HADIR')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                                    Hadir
                                                </span>
                                            @elseif($att->status === 'TERLAMBAT')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                                    Terlambat
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                                    {{ $att->status }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-xs font-mono text-gray-500">
                                            {{ $att->kiosk_device ?? 'kiosk-utama' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="p-4 bg-gray-50 border-t border-gray-200 text-xs text-gray-500 text-center">
                    Jika ada ketidaksesuaian jam absensi, silakan hubungi Wali Kelas X TKJ 1.
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
