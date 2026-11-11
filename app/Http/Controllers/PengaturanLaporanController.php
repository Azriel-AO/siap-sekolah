<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\DaftarHadirBulanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PengaturanLaporanController extends Controller
{
    /**
     * Form kecil "Pengaturan Laporan" (admin) — disimpan di tabel settings.
     */
    public function index(): View
    {
        return view('admin.pengaturan-laporan.index', [
            'p' => DaftarHadirBulanan::pengaturan(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'laporan_nama_sekolah' => ['required', 'string', 'max:100'],
            'laporan_tahun_pelajaran' => ['required', 'string', 'max:20'],
            'laporan_semester' => ['required', Rule::in(['Ganjil', 'Genap'])],
            'laporan_kota' => ['required', 'string', 'max:50'],
            'laporan_jabatan_ttd' => ['required', 'string', 'max:50'],
            'laporan_nama_ttd' => ['required', 'string', 'max:100'],
        ], [
            'laporan_nama_sekolah.required' => 'Nama sekolah wajib diisi.',
            'laporan_tahun_pelajaran.required' => 'Tahun pelajaran wajib diisi.',
            'laporan_semester.required' => 'Semester wajib dipilih.',
            'laporan_kota.required' => 'Kota wajib diisi.',
            'laporan_jabatan_ttd.required' => 'Jabatan penandatangan wajib diisi.',
            'laporan_nama_ttd.required' => 'Nama penandatangan wajib diisi.',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()
            ->route('admin.pengaturan.laporan')
            ->with('status', 'Pengaturan laporan tersimpan.');
    }
}
