<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\PhotoUploadService;
use App\Services\RekapPeriodeHelper;
use App\Services\RekapRingkasanPeriode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TeacherAttendanceController extends Controller
{
    public function __construct(private readonly PhotoUploadService $photoService) {}

    /**
     * Tampilkan halaman rekap absensi harian kelas binaan.
     */
    public function index(Request $request): View
    {
        $this->pastikanGuruAtauAdmin($request);

        $schoolClass = $this->kelasDipilih($request);
        $classes = SchoolClass::orderBy('name')->get();
        $periode = RekapPeriodeHelper::ambilPeriodeDariRequest($request);
        $rentang = RekapPeriodeHelper::hitungRentang($periode, $this->tanggalDipilih($request));

        if ($periode === 'hari') {
            $studentsData = $this->getAttendanceData($schoolClass, $rentang['tanggal']);
        } else {
            $studentsData = $this->getRingkasanData($schoolClass, $rentang);
        }

        return view('teacher.attendance-today', [
            'schoolClass' => $schoolClass,
            'classes' => $classes,
            'periode' => $periode,
            'periodeLabel' => $rentang['label_periode'],
            'rentangLabel' => $rentang['label_rentang'],
            'today' => $rentang['tanggal'],
            'date' => $rentang['tanggal'],
            'tanggal' => $rentang['tanggal'],
            'tanggalDisplay' => $periode === 'hari'
                ? Carbon::parse($rentang['tanggal'])->translatedFormat('l, d F Y')
                : $rentang['label_rentang'],
            'summary' => $studentsData['summary'],
            'students' => $studentsData['students'],
        ]);
    }

    /**
     * Endpoint data JSON untuk live polling auto-refresh di browser guru.
     */
    public function data(Request $request): JsonResponse
    {
        $this->pastikanGuruAtauAdmin($request);

        $schoolClass = $this->kelasDipilih($request);
        $periode = RekapPeriodeHelper::ambilPeriodeDariRequest($request);
        $rentang = RekapPeriodeHelper::hitungRentang($periode, $this->tanggalDipilih($request));

        if ($periode === 'hari') {
            $data = $this->getAttendanceData($schoolClass, $rentang['tanggal']);
        } else {
            $data = $this->getRingkasanData($schoolClass, $rentang);
        }

        return response()->json([
            'success' => true,
            'class_name' => $schoolClass->name,
            'periode' => $periode,
            'periode_label' => $rentang['label_periode'],
            'rentang_label' => $rentang['label_rentang'],
            'tanggal' => $rentang['tanggal'],
            'date' => $rentang['tanggal'],
            'date_formatted' => $periode === 'hari'
                ? Carbon::parse($rentang['tanggal'])->translatedFormat('l, d F Y')
                : $rentang['label_rentang'],
            'server_time' => Carbon::now('Asia/Jakarta')->format('H:i:s'),
            'summary' => $data['summary'],
            'students' => $data['students'],
        ]);
    }

    /**
     * Helper untuk mengambil dan memformat kehadiran seluruh siswa kelas.
     *
     * @return array<string, mixed>
     */
    protected function getAttendanceData(SchoolClass $schoolClass, string $today): array
    {
        $students = $schoolClass->students()
            ->with(['attendances' => function ($query) use ($today): void {
                $query->where('date', $today);
            }, 'rfidCard', 'faceEnrollment', 'schoolClass'])
            ->orderBy('name')
            ->get();

        Student::preloadCardLabels($students);

        $formatted = $students->map(fn (Student $student): array => $this->formatDailyStudentRow($student));

        return [
            'summary' => $this->summarizeDailyCounts($formatted, $students->count()),
            'students' => $formatted,
        ];
    }

    /**
     * Ringkasan per siswa untuk periode Minggu/Bulan: Hadir / Terlambat / Tidak Hadir + Persentase.
     *
     * @param  array<string, mixed>  $rentang
     * @return array<string, mixed>
     */
    protected function getRingkasanData(SchoolClass $schoolClass, array $rentang): array
    {
        $students = $schoolClass->students()
            ->with('faceEnrollment')
            ->orderBy('name')
            ->get();

        $perSiswa = RekapRingkasanPeriode::hitungPerSiswa(
            $students,
            $rentang['start_date'],
            $rentang['end_date'],
            $rentang['hari_sekolah']
        );

        $formatted = $students->map(function (Student $student) use ($perSiswa): array {
            $ringkasan = $perSiswa[$student->id];

            return [
                'id' => $student->id,
                'nis' => $student->nis,
                'nisn' => $student->nisn ?? '-',
                'name' => $student->name,
                'gender' => $student->gender,
                'hadir' => $ringkasan['hadir'],
                'terlambat' => $ringkasan['terlambat'],
                'izin' => $ringkasan['izin'],
                'sakit' => $ringkasan['sakit'],
                'alpa' => $ringkasan['alpa'],
                'persentase' => $ringkasan['persentase'],
                'face' => $this->wajahStatus($student->faceEnrollment),
            ];
        });

        $persenValues = $formatted->pluck('persentase')->filter(fn ($p): bool => $p !== null);

        return [
            'summary' => [
                'total' => $formatted->count(),
                'hadir' => (int) $formatted->sum('hadir'),
                'terlambat' => (int) $formatted->sum('terlambat'),
                'alpa' => (int) $formatted->sum('alpa'),
                'rata_persen' => $persenValues->count() > 0 ? (int) round($persenValues->avg()) : null,
            ],
            'students' => $formatted,
        ];
    }

    /**
     * Format satu baris data absensi harian siswa beserta foto bukti.
     *
     * @return array<string, mixed>
     */
    private function formatDailyStudentRow(Student $student): array
    {
        $att = $student->attendances->first();
        $status = $att ? $att->status : 'BELUM_ABSEN';

        return [
            'id' => $student->id,
            'nis' => $student->nis,
            'nisn' => $student->nisn ?? '-',
            'name' => $student->name,
            'gender' => $student->gender,
            'card_uid' => $student->rfidCard?->card_uid ?? '-',
            'card_label' => $student->card_label ?? '-',
            'status' => $status,
            'status_label' => match ($status) {
                'HADIR' => 'Hadir',
                'TERLAMBAT' => 'Terlambat',
                'ALPA' => 'Alpa',
                default => 'Belum Absen',
            },
            'check_in' => $att && $att->check_in_at ? substr($att->check_in_at, 0, 5) : '-',
            'check_out' => $att && $att->check_out_at ? substr($att->check_out_at, 0, 5) : '-',
            'metode' => $this->metodeLabel($att?->kiosk_device),
            'photo_in' => $this->photoService->readLocalPhotoDataUrl($att?->photo_in_path),
            'photo_out' => $this->photoService->readLocalPhotoDataUrl($att?->photo_out_path),
            'face' => $this->wajahStatus($student->faceEnrollment),
        ];
    }

    /**
     * Hitung jumlah siswa hadir, terlambat, dan belum absen pada hari terpilih.
     *
     * @param  Collection<int, array<string, mixed>>  $formatted
     * @return array<string, int>
     */
    private function summarizeDailyCounts(Collection $formatted, int $totalStudents): array
    {
        $hadirCount = 0;
        $terlambatCount = 0;
        $belumAbsenCount = 0;

        foreach ($formatted as $row) {
            if ($row['status'] === 'HADIR') {
                $hadirCount++;
            } elseif ($row['status'] === 'TERLAMBAT') {
                $terlambatCount++;
            } else {
                $belumAbsenCount++;
            }
        }

        return [
            'total' => $totalStudents,
            'hadir' => $hadirCount,
            'terlambat' => $terlambatCount,
            'belum_absen' => $belumAbsenCount,
        ];
    }

    /**
     * Metode absen dari jejak perangkat (kiosk_device).
     */
    private function metodeLabel(?string $device): string
    {
        $device = strtolower((string) ($device ?? ''));

        if ($device === '') {
            return '-';
        }

        if (str_contains($device, 'face') || str_contains($device, 'wajah')) {
            return 'Wajah';
        }

        if (str_contains($device, 'fingerprint')) {
            return 'Sidik Jari';
        }

        if (str_contains($device, 'kartu') || str_starts_with($device, 'kiosk')) {
            return 'Kartu';
        }

        return 'Lainnya';
    }

    /**
     * Tanggal rekap dipilih lewat query param ("date" atau "tanggal").
     * Format salah/tidak masuk akal → hari ini.
     */
    private function tanggalDipilih(Request $request): string
    {
        return RekapPeriodeHelper::ambilTanggalDariRequest($request);
    }

    /**
     * Kelas yang ditampilkan di rekap dipilih lewat query param "?kelas=slug".
     * Tanpa param → kelas pertama (urut nama). Slug tidak dikenal → 404.
     */
    private function kelasDipilih(Request $request): SchoolClass
    {
        $slug = $request->query('kelas');

        if ($slug !== null && $slug !== '') {
            return SchoolClass::where('slug', $slug)->firstOrFail();
        }

        return SchoolClass::orderBy('name')->firstOrFail();
    }

    /**
     * Rekap harian (termasuk foto bukti absen, PRD F1) hanya boleh dilihat
     * oleh admin — bukan guru, bukan siswa, bukan pengunjung publik.
     */
    private function pastikanGuruAtauAdmin(Request $request): void
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Halaman ini hanya untuk admin.');
        }
    }

    /**
     * Status badge wajah (R3 research.md): "TERDAFTAR" bila baris pendaftaran
     * ada DAN ketiga path terisi DAN ketiga file ada di disk privat; selain itu
     * "BELUM". Status dihitung, bukan kolom — data wajar bisa hilang/korup.
     */
    private function wajahStatus($enrollment): string
    {
        if (! $enrollment || ! $enrollment->photo_1_path || ! $enrollment->photo_2_path || ! $enrollment->photo_3_path) {
            return 'BELUM';
        }

        try {
            $lengkap = Storage::disk('local')->exists($enrollment->photo_1_path)
                && Storage::disk('local')->exists($enrollment->photo_2_path)
                && Storage::disk('local')->exists($enrollment->photo_3_path);
        } catch (\Throwable) {
            return 'BELUM';
        }

        return $lengkap ? 'TERDAFTAR' : 'BELUM';
    }
}
