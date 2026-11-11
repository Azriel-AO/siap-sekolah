<?php

namespace App\Http\Controllers;

use App\Models\IzinSakit;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KehadiranManualController extends Controller
{
    /**
     * Halaman "Catat Izin / Sakit" (admin-only lewat middleware 'admin').
     * Tampilkan status hari tertentu per siswa kelas terpilih:
     * Hadir (tap masuk), Terlambat (tap lewat jam kelas), Izin/Sakit (dari tabel izin_sakit), atau — (belum apa-apa).
     */
    public function index(Request $request): View
    {
        $tanggal = $this->tanggalDipilih($request);
        $classes = SchoolClass::orderBy('name')->get();

        $slug = (string) $request->query('kelas', '');
        $kelasDipilih = $slug !== ''
            ? $classes->firstWhere('slug', $slug)
            : $classes->first();

        $rows = collect();
        if ($kelasDipilih) {
            $students = $kelasDipilih->students()
                ->with([
                    'attendances' => fn ($q) => $q->where('date', $tanggal),
                    'izinSakits' => fn ($q) => $q->where('tanggal', $tanggal),
                ])
                ->orderBy('name')
                ->get();

            $rows = $students->map(function ($student) use ($tanggal) {
                $att = $student->attendances->first();
                $izinSakit = $student->izinSakits->first();

                if ($izinSakit) {
                    $status = ucfirst($izinSakit->status);
                    $statusJenis = $izinSakit->status; // izin | sakit
                } elseif ($att) {
                    $status = $att->status === 'HADIR' ? 'Hadir' : 'Terlambat';
                    $statusJenis = $att->status === 'HADIR' ? 'hadir' : 'terlambat';
                } else {
                    $status = '—';
                    $statusJenis = 'kosong';
                }

                return [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'induk' => $student->induk,
                    'nisn' => $student->nisn,
                    'name' => $student->name,
                    'status' => $status,
                    'statusJenis' => $statusJenis,
                    'keterangan' => $izinSakit?->keterangan,
                    'hasIzinSakit' => (bool) $izinSakit,
                ];
            });
        }

        return view('admin.kehadiran-manual.index', [
            'tanggal' => $tanggal,
            'tanggalDisplay' => Carbon::parse($tanggal)->translatedFormat('l, d F Y'),
            'classes' => $classes,
            'kelasDipilih' => $kelasDipilih,
            'rows' => $rows,
        ]);
    }

    /**
     * Tandai Izin / Sakit / hapus catatan izin-sakit siswa pada tanggal tertentu.
     * UNIQUE(student_id, tanggal) — input ulang menimpa.
     */
    public function simpan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'aksi' => ['required', Rule::in(['izin', 'sakit', 'hapus'])],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'kelas' => ['nullable', 'string', 'max:50'],
        ], [
            'student_id.required' => 'Siswa wajib dipilih.',
            'student_id.exists' => 'Siswa tidak ditemukan.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'aksi.required' => 'Aksi wajib dipilih (I / S / Hapus).',
            'aksi.in' => 'Aksi tidak valid.',
        ]);

        if ($validated['aksi'] === 'hapus') {
            IzinSakit::where('student_id', $validated['student_id'])
                ->where('tanggal', $validated['tanggal'])
                ->delete();

            return $this->kembali($request, 'Catatan izin/sakit dihapus.');
        }

        IzinSakit::updateOrCreate(
            ['student_id' => $validated['student_id'], 'tanggal' => $validated['tanggal']],
            [
                'status' => $validated['aksi'],
                'keterangan' => $validated['keterangan'] ?? null,
                'recorded_by' => $request->user()->id,
            ]
        );

        return $this->kembali($request, 'Catatan ' . strtoupper($validated['aksi']) . ' tersimpan.');
    }

    private function kembali(Request $request, string $pesan): RedirectResponse
    {
        $params = ['tanggal' => $request->input('tanggal')];
        if ($request->input('kelas')) {
            $params['kelas'] = $request->input('kelas');
        }

        return redirect()
            ->route('admin.kehadiran.manual', $params)
            ->with('status', $pesan);
    }

    private function tanggalDipilih(Request $request): string
    {
        $t = (string) $request->query('tanggal', '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && strtotime($t) !== false) {
            return $t;
        }

        return Carbon::now('Asia/Jakarta')->toDateString();
    }
}
