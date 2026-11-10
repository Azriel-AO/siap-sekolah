<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRekapitulasiController extends Controller
{
    /**
     * Halaman admin "Rekapitulasi": bar chart per kelas.
     */
    public function index(Request $request): View
    {
        return view('admin.rekapitulasi', [
            'tanggal' => Carbon::now('Asia/Jakarta')->toDateString(),
            'tanggalDisplay' => Carbon::now('Asia/Jakarta')->translatedFormat('l, d F Y'),
        ]);
    }

    /**
     * Endpoint JSON grafik: per kelas, hitung Hadir / Terlambat / Tidak Hadir
     * dari catatan kehadiran HARI INI.
     *
     * Definisi (mengikuti logika yang sudah ada):
     * - Hadir/Terlambat = status yang sudah tercatat di attendances hari ini
     *   (dibangkitkan otomatis dari jam masuk kelas saat siswa tap).
     * - Tidak Hadir = total siswa kelas − Hadir − Terlambat (belum tap / ALPA / dst.).
     */
    public function grafik(Request $request): JsonResponse
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $classes = SchoolClass::withCount('students')->orderBy('name')->get();

        $counts = Attendance::where('date', $today)
            ->selectRaw('school_class_id, status, count(*) as jumlah')
            ->groupBy('school_class_id', 'status')
            ->get()
            ->groupBy('school_class_id')
            ->map(fn ($rows) => $rows->pluck('jumlah', 'status'));

        $labels = [];
        $hadir = [];
        $terlambat = [];
        $tidakHadir = [];

        foreach ($classes as $kelas) {
            $byStatus = $counts[$kelas->id] ?? collect();
            $h = (int) ($byStatus['HADIR'] ?? 0);
            $t = (int) ($byStatus['TERLAMBAT'] ?? 0);

            $labels[] = $kelas->name;
            $hadir[] = $h;
            $terlambat[] = $t;
            $tidakHadir[] = max(0, $kelas->students_count - $h - $t);
        }

        return response()->json([
            'tanggal' => $today,
            'labels' => $labels,
            'hadir' => $hadir,
            'terlambat' => $terlambat,
            'tidak_hadir' => $tidakHadir,
        ]);
    }
}
