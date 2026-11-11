<?php

use App\Http\Controllers\AdminClassController;
use App\Http\Controllers\AdminRekapExportController;
use App\Http\Controllers\AdminRekapitulasiController;
use App\Http\Controllers\AdminStudentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaceEnrollmentController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\PendaftaranController;
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

// Halaman dashboard dilindungi middleware 'auth', 'admin' dan pencegah cache histori
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'admin', 'prevent-back-history'])
    ->name('dashboard');

// Kiosk Gerbang Tunggal (kartu + sidik jari; tanpa scoping kelas)
Route::get('/kiosk', [KioskController::class, 'gerbang'])->name('kiosk.gerbang');
Route::post('/kiosk/tap', [KioskController::class, 'tap'])->name('kiosk.tap');
Route::post('/kiosk/fingerprint/identificar', [KioskController::class, 'identificarSidikJari'])->name('kiosk.fingerprint.identificar');

// Terminal Pendaftaran Mandiri (guest; SELALU terbuka — pengawasan guru piket)
Route::get('/pendaftaran', [PendaftaranController::class, 'show'])->name('pendaftaran.index');
Route::post('/pendaftaran', [PendaftaranController::class, 'store'])->name('pendaftaran.simpan');
Route::post('/pendaftaran/fingerprint/enrolar', [PendaftaranController::class, 'fingerprintEnrolar'])->name('pendaftaran.fingerprint.enrolar');
Route::get('/pendaftaran/berhasil', [PendaftaranController::class, 'berhasil'])->name('pendaftaran.berhasil');

// Route lama /kiosk/{slug} tetap aman: redirect 301 ke /kiosk
Route::redirect('/kiosk/{slug}', '/kiosk', 301)->where('slug', '[A-Za-z0-9\-]+');
Route::redirect('/kiosk/{slug}/tap', '/kiosk/tap', 301)->where('slug', '[A-Za-z0-9\-]+');

// Rute terproteksi sesi autentikasi
Route::middleware(['auth', 'prevent-back-history'])->group(function () {
    // Admin: semua route /admin/* serta rekap & riwayat khusus peran admin (middleware 'admin'). Guru & siswa → 403.
    Route::middleware(['admin'])->group(function () {
        // Rekap Absensi (US-21, US-22) — sekarang dibuka oleh admin
        Route::get('/guru/rekap-harian', [TeacherAttendanceController::class, 'index'])->name('guru.rekap');
        Route::get('/guru/rekap-harian/data', [TeacherAttendanceController::class, 'data'])->name('guru.rekap.data');

        // Riwayat Absensi Siswa (US-31) — dilindungi middleware admin
        Route::get('/siswa/riwayat-absensi', [StudentAttendanceController::class, 'index'])->name('siswa.riwayat');

        // Admin: Rekapitulasi grafis per kelas
        Route::get('/admin/rekapitulasi', [AdminRekapitulasiController::class, 'index'])->name('admin.rekapitulasi');
        Route::get('/admin/rekap-grafik', [AdminRekapitulasiController::class, 'grafik'])->name('admin.rekap.grafik');

        // Admin: export rekap (Excel & PDF)
        Route::get('/admin/rekap-export/excel', [AdminRekapExportController::class, 'excel'])->name('admin.rekap.excel');
        Route::get('/admin/rekap-export/pdf', [AdminRekapExportController::class, 'pdf'])->name('admin.rekap.pdf');

        // Admin: Data Kelas
        Route::get('/admin/data-kelas', [AdminClassController::class, 'index'])->name('admin.kelas.index');
        Route::get('/admin/data-kelas/tambah', [AdminClassController::class, 'create'])->name('admin.kelas.create');
        Route::post('/admin/data-kelas', [AdminClassController::class, 'store'])->name('admin.kelas.store');
        Route::get('/admin/data-kelas/{class}/edit', [AdminClassController::class, 'edit'])->name('admin.kelas.edit');
        Route::put('/admin/data-kelas/{class}', [AdminClassController::class, 'update'])->name('admin.kelas.update');
        Route::delete('/admin/data-kelas/{class}', [AdminClassController::class, 'destroy'])->name('admin.kelas.destroy');

        // Admin: Data Siswa
        Route::get('/admin/data-siswa', [AdminStudentController::class, 'index'])->name('admin.siswa.index');
        Route::get('/admin/data-siswa/tambah', [AdminStudentController::class, 'create'])->name('admin.siswa.create');
        Route::post('/admin/data-siswa', [AdminStudentController::class, 'store'])->name('admin.siswa.store');
        Route::get('/admin/data-siswa/{student}/edit', [AdminStudentController::class, 'edit'])->name('admin.siswa.edit');
        Route::put('/admin/data-siswa/{student}', [AdminStudentController::class, 'update'])->name('admin.siswa.update');
        Route::delete('/admin/data-siswa/{student}', [AdminStudentController::class, 'destroy'])->name('admin.siswa.destroy');
    });

    // Pendaftaran Wajah Mandiri Siswa (US-10 tahap 1, PRD F3) — tanpa pengenalan wajah
    Route::get('/siswa/pendaftaran-wajah', [FaceEnrollmentController::class, 'show'])->name('siswa.wajah');
    Route::get('/siswa/pendaftaran-wajah/status', [FaceEnrollmentController::class, 'status'])->name('siswa.wajah.status');
    Route::post('/siswa/pendaftaran-wajah', [FaceEnrollmentController::class, 'store'])->name('siswa.wajah.simpan');
});

require __DIR__.'/auth.php';
