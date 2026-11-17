<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\View\View;

class AdminStatusSistemController extends Controller
{
    /**
     * Halaman GUI status kesehatan sistem (khusus Admin, read-only).
     */
    public function index(SystemHealthService $healthService): View
    {
        return view('admin.status-sistem.index', $healthService->collectHealthReport());
    }
}
