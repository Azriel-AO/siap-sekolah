<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\StudentAttendanceController;
use App\Http\Controllers\TeacherAttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — SIAP SMK Karya Nugraha (KNBI)
|--------------------------------------------------------------------------
*/

// Halaman utama dialihkan langsung ke dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Halaman dashboard dilindungi middleware 'auth' dan pencegah cache histori
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'prevent-back-history'])
    ->name('dashboard');

// Rute Kiosk Absensi Terikat Kelas (US-14, R2, R7)
Route::get('/kiosk/{slug}', [KioskController::class, 'show'])->name('kiosk.show');
Route::post('/kiosk/{slug}/tap', [KioskController::class, 'tap'])->name('kiosk.tap');

// Rute Guru & Siswa terproteksi sesi autentikasi
Route::middleware(['auth', 'prevent-back-history'])->group(function () {
    // Rekap Absensi Guru (US-21, US-22)
    Route::get('/guru/rekap-harian', [TeacherAttendanceController::class, 'index'])->name('guru.rekap');
    Route::get('/guru/rekap-harian/data', [TeacherAttendanceController::class, 'data'])->name('guru.rekap.data');

    // Riwayat Absensi Siswa (US-31)
    Route::get('/siswa/riwayat-absensi', [StudentAttendanceController::class, 'index'])->name('siswa.riwayat');
});

require __DIR__.'/auth.php';
