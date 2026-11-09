<?php

use App\Http\Controllers\DashboardController;
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

require __DIR__.'/auth.php';
