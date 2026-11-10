<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya akun dengan peran admin yang boleh lewat.
 * Guru/siswa/tamu → 403 "Halaman ini hanya untuk admin."
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'Halaman ini hanya untuk admin.');
        }

        return $next($request);
    }
}
