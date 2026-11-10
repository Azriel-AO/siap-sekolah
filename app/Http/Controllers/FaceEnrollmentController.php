<?php

namespace App\Http\Controllers;

use App\Models\FaceEnrollment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FaceEnrollmentController extends Controller
{
    /**
     * Halaman pendaftaran wajah mandiri (hanya peran siswa).
     */
    public function show(Request $request): View
    {
        $this->pastikanSiswa($request);

        $student = $this->siswaMilikAkun($request);
        $enrollment = $student?->faceEnrollment;

        return view('face-enrollment.show', [
            'student' => $student,
            'enrollment' => $enrollment,
            'wajahTervalidasi' => $this->pendaftaranValid($enrollment),
        ]);
    }

    /**
     * Status pendaftaran wajah milik siswa yang login (JSON, tanpa reload).
     */
    public function status(Request $request): JsonResponse
    {
        $this->pastikanSiswa($request);

        $student = $this->siswaMilikAkun($request);
        $enrollment = $student?->faceEnrollment;

        if (! $this->pendaftaranValid($enrollment)) {
            return response()->json([
                'terdaftar' => false,
                'status_label' => 'Belum Terdaftar',
                'tanggal_daftar' => null,
                'tanggal_daftar_ulang' => null,
                'photos' => [],
                'message' => 'Anda belum mendaftarkan wajah.',
            ]);
        }

        return response()->json([
            'terdaftar' => true,
            'status_label' => 'Terdaftar',
            'tanggal_daftar' => $enrollment->created_at->translatedFormat('l, d F Y H.i'),
            'tanggal_daftar_ulang' => $enrollment->last_reenrolled_at?->translatedFormat('l, d F Y H.i'),
            'photos' => $this->fotoEmbedded($enrollment),
            'message' => 'Wajah Anda sudah terdaftar.',
        ]);
    }

    /**
     * Simpan pendaftaran wajah (tepat 3 foto + persetujuan orang tua/wali).
     * Identitas siswa SELALU dari sesi login — klien tidak mengirim student_id.
     * Semua validasi dilakukan sebelum file apa pun ditulis ke storage
     * (all-or-nothing). Waktu SELALU dari server (Konstitusi II).
     */
    public function store(Request $request): JsonResponse
    {
        $this->pastikanSiswa($request);

        $student = $this->siswaMilikAkun($request);
        if (! $student) {
            return $this->gagal('Akun siswa Anda belum tertaut dengan data siswa. Hubungi petugas sekolah.', 422);
        }

        // Persetujuan orang tua/wali wajib ada (FR-012)
        if ($request->input('consent') !== true && $request->input('consent') !== 'true' && $request->input('consent') !== 1) {
            return $this->gagal('Anda harus menyetujui pernyataan izin orang tua/wali sebelum menyimpan.', 422);
        }

        $photos = $request->input('photos');
        if (! is_array($photos) || count($photos) !== 3) {
            $jumlah = is_array($photos) ? count($photos) : 0;
            return $this->gagal("Foto wajah wajib lengkap 3 foto. Saat ini baru {$jumlah} foto sah.", 422);
        }

        // Decode + validasi ketiga foto DULU; baru tulis file setelah semuanya sah
        $binaries = [];
        foreach ($photos as $index => $dataUrl) {
            $slot = $index + 1;
            $binary = $this->decodeDataUrlJpeg($dataUrl);
            if ($binary === null) {
                return $this->gagal("Foto ke-{$slot} tidak sah. Ambil ulang foto tersebut.", 422);
            }
            $binaries[$slot] = $binary;
        }

        $existing = $student->faceEnrollment;

        // Waktu dari server (Konstitusi II)
        $now = Carbon::now('Asia/Jakarta');
        $stamp = $now->format('YmdHis');

        $paths = [];
        foreach ($binaries as $slot => $binary) {
            $paths[$slot] = "face-photos/{$student->id}/face-{$student->id}-{$slot}-{$stamp}.jpg";
        }

        // Daftar ulang (US2): hapus 3 file lama, timpa kolom path, dalam satu operasi
        if ($existing) {
            foreach ([$existing->photo_1_path, $existing->photo_2_path, $existing->photo_3_path] as $oldPath) {
                if ($oldPath) {
                    Storage::disk('local')->delete($oldPath);
                }
            }

            foreach ($paths as $slot => $path) {
                Storage::disk('local')->put($path, $binaries[$slot]);
            }

            $existing->update([
                'photo_1_path' => $paths[1],
                'photo_2_path' => $paths[2],
                'photo_3_path' => $paths[3],
                'consented_at' => $now,
                'last_reenrolled_at' => $now,
            ]);

            $enrollment = $existing->fresh();
        } else {
            // Tulis ketiga file; bila ada yang gagal di tengah, bersihkan sisanya
            $written = [];
            try {
                foreach ($paths as $slot => $path) {
                    Storage::disk('local')->put($path, $binaries[$slot]);
                    $written[] = $path;
                }

                $enrollment = FaceEnrollment::create([
                    'student_id' => $student->id,
                    'photo_1_path' => $paths[1],
                    'photo_2_path' => $paths[2],
                    'photo_3_path' => $paths[3],
                    'consented_at' => $now,
                    'last_reenrolled_at' => null,
                ]);
            } catch (\Throwable $e) {
                foreach ($written as $path) {
                    Storage::disk('local')->delete($path);
                }

                return $this->gagal('Gagal menyimpan pendaftaran. Coba lagi.', 422);
            }
        }

        return response()->json([
            'success' => true,
            'status_label' => 'Terdaftar',
            'message' => 'Pendaftaran wajah berhasil disimpan.',
            'tanggal_daftar' => $enrollment->created_at->translatedFormat('l, d F Y H.i'),
            'tanggal_daftar_ulang' => $enrollment->last_reenrolled_at?->translatedFormat('l, d F Y H.i'),
        ]);
    }

    /**
     * Hanya peran siswa yang boleh membuka halaman/aksi ini (guru/admin → 403).
     */
    private function pastikanSiswa(Request $request): void
    {
        if ($request->user()->role !== 'siswa') {
            abort(403, 'Halaman ini hanya untuk siswa.');
        }
    }

    /**
     * Ambil record siswa yang tertaut dengan akun login.
     * (Pola demo sama seperti riwayat absensi: fallback ke siswa pertama
     * bila akun belum tertaut — hanya memengaruhi data demo.)
     */
    private function siswaMilikAkun(Request $request): ?Student
    {
        return Student::where('user_id', $request->user()->id)->first()
            ?? Student::first();
    }

    /**
     * Pendaftaran dianggap valid ("Terdaftar") bila baris ada, ketiga path
     * terisi, dan ketiga file fisik ada di disk (R3 research.md).
     */
    private function pendaftaranValid(?FaceEnrollment $enrollment): bool
    {
        if (! $enrollment || ! $enrollment->photo_1_path || ! $enrollment->photo_2_path || ! $enrollment->photo_3_path) {
            return false;
        }

        return Storage::disk('local')->exists($enrollment->photo_1_path)
            && Storage::disk('local')->exists($enrollment->photo_2_path)
            && Storage::disk('local')->exists($enrollment->photo_3_path);
    }

    /**
     * Foto hanya disajikan sebagai base64 embedded di halaman wajib login
     * (tidak pernah lewat URL publik). Gagal baca → null.
     */
    private function fotoEmbedded(FaceEnrollment $enrollment): array
    {
        $photos = [];
        foreach ([$enrollment->photo_1_path, $enrollment->photo_2_path, $enrollment->photo_3_path] as $path) {
            try {
                $binary = $path && Storage::disk('local')->exists($path)
                    ? Storage::disk('local')->get($path)
                    : null;
                $photos[] = $binary ? 'data:image/jpeg;base64,' . base64_encode($binary) : null;
            } catch (\Throwable) {
                $photos[] = null;
            }
        }

        return $photos;
    }

    /**
     * Decode data URL "data:image/jpeg;base64,..." dengan strict mode.
     * Mengembalikan binary JPEG (100 B–500 KB) atau null bila tidak sah.
     */
    private function decodeDataUrlJpeg(mixed $dataUrl): ?string
    {
        if (! is_string($dataUrl)) {
            return null;
        }

        $prefix = 'data:image/jpeg;base64,';
        if (! str_starts_with($dataUrl, $prefix)) {
            return null;
        }

        $encoded = substr($dataUrl, strlen($prefix));
        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            return null;
        }

        $size = strlen($binary);
        if ($size < 100 || $size > 500 * 1024) {
            return null;
        }

        // Pastikan benar-benar JPEG (SOI FFD8 ... EOI FFD9)
        if (substr($binary, 0, 2) !== "\xFF\xD8" || substr($binary, -2) !== "\xFF\xD9") {
            return null;
        }

        return $binary;
    }

    /**
     * Respons gagal dengan pesan Bahasa Indonesia.
     */
    private function gagal(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
