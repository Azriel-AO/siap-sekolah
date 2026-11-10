<?php

use App\Http\Controllers\AdminClassController;
use App\Http\Controllers\AdminStudentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaceEnrollmentController;
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

    // Admin: Data Kelas (khusus peran admin)
    Route::get('/admin/data-kelas', [AdminClassController::class, 'index'])->name('admin.kelas.index');
    Route::get('/admin/data-kelas/tambah', [AdminClassController::class, 'create'])->name('admin.kelas.create');
    Route::post('/admin/data-kelas', [AdminClassController::class, 'store'])->name('admin.kelas.store');
    Route::get('/admin/data-kelas/{class}/edit', [AdminClassController::class, 'edit'])->name('admin.kelas.edit');
    Route::put('/admin/data-kelas/{class}', [AdminClassController::class, 'update'])->name('admin.kelas.update');
    Route::delete('/admin/data-kelas/{class}', [AdminClassController::class, 'destroy'])->name('admin.kelas.destroy');

    // Admin: Data Siswa (khusus peran admin)
    Route::get('/admin/data-siswa', [AdminStudentController::class, 'index'])->name('admin.siswa.index');
    Route::get('/admin/data-siswa/tambah', [AdminStudentController::class, 'create'])->name('admin.siswa.create');
    Route::post('/admin/data-siswa', [AdminStudentController::class, 'store'])->name('admin.siswa.store');
    Route::get('/admin/data-siswa/{student}/edit', [AdminStudentController::class, 'edit'])->name('admin.siswa.edit');
    Route::put('/admin/data-siswa/{student}', [AdminStudentController::class, 'update'])->name('admin.siswa.update');

    // Riwayat Absensi Siswa (US-31)
    Route::get('/siswa/riwayat-absensi', [StudentAttendanceController::class, 'index'])->name('siswa.riwayat');

    // Pendaftaran Wajah Mandiri Siswa (US-10 tahap 1, PRD F3) — tanpa pengenalan wajah
    Route::get('/siswa/pendaftaran-wajah', [FaceEnrollmentController::class, 'show'])->name('siswa.wajah');
    Route::get('/siswa/pendaftaran-wajah/status', [FaceEnrollmentController::class, 'status'])->name('siswa.wajah.status');
    Route::post('/siswa/pendaftaran-wajah', [FaceEnrollmentController::class, 'store'])->name('siswa.wajah.simpan');
});

require __DIR__.'/auth.php';
