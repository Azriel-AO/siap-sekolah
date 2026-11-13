<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FingerprintService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStatusSistemController extends Controller
{
    /**
     * Halaman GUI status kesehatan sistem (khusus Admin, read-only).
     */
    public function index(Request $request): View
    {
        $driver = (string) config('fingerprint.driver', 'mock');
        $baseUrl = (string) config('fingerprint.base_url', 'http://127.0.0.1:8787');
        $statusSensor = FingerprintService::dariConfig()->status();

        return view('admin.status-sistem.index', [
            'driver' => $driver,
            'baseUrl' => $baseUrl,
            'statusSensor' => $statusSensor,
            'jumlahKelas' => SchoolClass::count(),
            'jumlahSiswa' => Student::count(),
        ]);
    }
}
