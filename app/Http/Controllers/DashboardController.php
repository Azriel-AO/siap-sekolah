<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metricsService) {}

    /**
     * Display the dashboard view with authenticated user info.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $roleLabels = [
            'admin' => 'Admin',
            'guru' => 'Guru',
            'siswa' => 'Siswa',
        ];

        $siswaPerluDaftarWajah = false;
        if ($user->role === 'siswa') {
            $student = Student::where('user_id', $user->id)
                ->when($user->student_id, fn ($query) => $query->orWhere('id', $user->student_id))
                ->first();
            $siswaPerluDaftarWajah = $student !== null && ! $student->faceEnrollment;
        }

        $metrics = $this->metricsService->buildMetrics();

        return view('dashboard', [
            'user' => $user,
            'roleLabel' => $roleLabels[$user->role] ?? ucfirst($user->role),
            'siswaPerluDaftarWajah' => $siswaPerluDaftarWajah,
            'chart7Hari' => $metrics['chart7Hari'],
            'weeklyTotals' => $metrics['weeklyTotals'],
        ]);
    }
}
