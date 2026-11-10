<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard view with authenticated user info.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Label peran ramah pengguna
        $roleLabels = [
            'admin' => 'Admin',
            'guru' => 'Guru',
            'siswa' => 'Siswa',
        ];

        $roleLabel = $roleLabels[$user->role] ?? ucfirst($user->role);

        // Banner pengingat: siswa yang belum punya baris face_enrollments
        // ditampilkan di dashboard (relasi & fallback demo sama seperti controllers siswa lain)
        $siswaPerluDaftarWajah = false;
        if ($user->role === 'siswa') {
            $student = Student::where('user_id', $user->id)->first() ?? Student::first();
            $siswaPerluDaftarWajah = ! $student || ! $student->faceEnrollment;
        }

        return view('dashboard', [
            'user' => $user,
            'roleLabel' => $roleLabel,
            'siswaPerluDaftarWajah' => $siswaPerluDaftarWajah,
        ]);
    }
}
