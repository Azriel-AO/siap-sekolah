<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TeacherAttendanceController extends Controller
{
    /**
     * Tampilkan halaman rekap absensi harian kelas binaan.
     */
    public function index(Request $request): View
    {
        $this->pastikanGuruAtauAdmin($request);

        $schoolClass = $this->kelasDipilih($request);
        $classes = SchoolClass::orderBy('name')->get();
        $today = $this->tanggalDipilih($request);

        $studentsData = $this->getAttendanceData($schoolClass, $today);

        return view('teacher.attendance-today', [
            'schoolClass' => $schoolClass,
            'classes' => $classes,
            'today' => $today,
            'tanggalDisplay' => \Carbon\Carbon::parse($today)->translatedFormat('l, d F Y'),
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
        $today = $this->tanggalDipilih($request);

        $data = $this->getAttendanceData($schoolClass, $today);

        return response()->json([
            'success' => true,
            'class_name' => $schoolClass->name,
            'date_formatted' => \Carbon\Carbon::parse($today)->translatedFormat('l, d F Y'),
            'server_time' => Carbon::now('Asia/Jakarta')->format('H:i:s'),
            'summary' => $data['summary'],
            'students' => $data['students'],
        ]);
    }

    /**
     * Helper untuk mengambil dan memformat kehadiran seluruh siswa kelas.
     */
    protected function getAttendanceData(SchoolClass $schoolClass, string $today): array
    {
        $students = $schoolClass->students()
            ->with(['attendances' => function ($query) use ($today) {
                $query->where('date', $today);
            }, 'rfidCard', 'faceEnrollment'])
            ->orderBy('name')
            ->get();

        $hadirCount = 0;
        $terlambatCount = 0;
        $belumAbsenCount = 0;

        $formatted = $students->map(function ($student) {
            $att = $student->attendances->first();

            $status = $att ? $att->status : 'BELUM_ABSEN';
            $checkIn = $att && $att->check_in_at ? substr($att->check_in_at, 0, 5) : '-';
            $checkOut = $att && $att->check_out_at ? substr($att->check_out_at, 0, 5) : '-';

            return [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'gender' => $student->gender,
                'card_uid' => $student->rfidCard?->card_uid ?? '-',
                'status' => $status,
                'status_label' => match ($status) {
                    'HADIR' => 'Hadir',
                    'TERLAMBAT' => 'Terlambat',
                    'ALPA' => 'Alpa',
                    default => 'Belum Absen',
                },
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'photo_in' => $this->fotoDataUrl($att?->photo_in_path),
                'photo_out' => $this->fotoDataUrl($att?->photo_out_path),
                'face' => $this->wajahStatus($student->faceEnrollment),
            ];
        });

        foreach ($formatted as $row) {
            if ($row['status'] === 'HADIR') $hadirCount++;
            elseif ($row['status'] === 'TERLAMBAT') $terlambatCount++;
            else $belumAbsenCount++;
        }

        return [
            'summary' => [
                'total' => $students->count(),
                'hadir' => $hadirCount,
                'terlambat' => $terlambatCount,
                'belum_absen' => $belumAbsenCount,
            ],
            'students' => $formatted,
        ];
    }

    /**
     * Tanggal rekap dipilih lewat query param "?tanggal=YYYY-MM-DD".
     * Format salah/tidak masuk akal → hari ini.
     */
    private function tanggalDipilih(Request $request): string
    {
        $t = (string) $request->query('tanggal', '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && strtotime($t) !== false) {
            return $t;
        }

        return Carbon::now('Asia/Jakarta')->toDateString();
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
     * guru dan admin — bukan siswa, bukan pengunjung publik.
     */
    private function pastikanGuruAtauAdmin(Request $request): void
    {
        if (! in_array($request->user()->role, ['guru', 'admin'])) {
            abort(403, 'Halaman ini hanya untuk guru dan admin.');
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

    /**
     * Foto bukti absen (PRD F1) disimpan di disk 'local' (storage privat,
     * tidak bisa diakses publik). Foto dikirim sebagai data URL base64 dan
     * hanya menempel pada halaman rekap yang wajib login. Gagal membaca
     * file atau foto tidak ada -> null (kolom foto tampil kosong).
     */
    private function fotoDataUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            if (! Storage::disk('local')->exists($path)) {
                return null;
            }

            $binary = Storage::disk('local')->get($path);

            return $binary ? 'data:image/jpeg;base64,' . base64_encode($binary) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}