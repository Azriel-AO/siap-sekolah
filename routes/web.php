<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KioskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — SIAP SMK Karya Nugraha (KNBI)
|--------------------------------------------------------------------------
*/

// Halaman utama dialihkan langsung ke dashboard (akan diteruskan ke login jika belum auth)
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

require __DIR__.'/auth.php';
