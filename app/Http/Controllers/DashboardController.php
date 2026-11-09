<?php

namespace App\Http\Controllers;

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

        return view('dashboard', [
            'user' => $user,
            'roleLabel' => $roleLabel,
        ]);
    }
}
