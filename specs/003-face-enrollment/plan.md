# Implementation Plan: Pendaftaran Mandiri Foto Wajah (Face Enrollment)

**Branch**: `003-face-enrollment` | **Date**: 2026-11-10 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-face-enrollment/spec.md`

## Summary

Membangun **tahap 1 Face Enrollment (US-10/F3)**: siswa mendaftarkan sendiri 3 foto wajah referensi melalui halaman baru dengan kamera getUserMedia (pola gagal-aman yang sudah teruji di kiosk spec 002). Foto tersimpan di penyimpanan privat dan dicatat sebagai satu pendaftaran per siswa (`face_enrollments` terhubung `student_id`). Siswa yang sudah terdaftar melihat status + tanggal + pratinjau foto miliknya dan dapat mendaftar ulang (foto lama dihapus, diganti baru, dengan dialog konfirmasi). Guru/admin melihat badge "Wajah: Terdaftar/Belum" per siswa di rekap harian yang sudah ada. **Tidak ada pencocokan/pengenalan wajah** dan **alur tap kiosk tidak tersentuh sama sekali**.

Pendekatan teknis: pola MVC Laravel yang sudah dipakai spec 002 — satu controller baru, satu model + migration baru, satu view Blade (Alpine.js untuk kamera & countdown), penyimpanan base64→file privat ala `simpanFotoBukti()`, dan penambahan 1 field badge pada endpoint rekap guru yang sudah ada.

## Technical Context

**Language/Version**: PHP 8.3+ / Laravel 13 (sesuai Konstitusi IX)

**Primary Dependencies**: Blade template, Alpine.js (bawaan Breeze), Tailwind CSS via Vite, Eloquent ORM — tanpa package pihak ketiga baru (Konstitusi IX: pilih yang umum, hindari eksperimental)

**Storage**: SQLite (`database/database.sqlite`) untuk data; file foto di **disk `local` (privat, `storage/app/private/face-photos/`)** — tidak ekspos publik

**Testing**: PHPUnit / `php artisan test` (Feature test dengan `RefreshDatabase` + `Storage::fake('local')`, mengikuti pola `tests/Feature/KioskAttendanceTest.php`)

**Target Platform**: Browser desktop/laptop dengan webcam (Chrome/Edge/Firefox), server lokal `php artisan serve`

**Project Type**: Web application Laravel monolit (satu project, pola MVC yang sudah ada)

**Performance Goals**: Halaman pendaftaran terbuka < 1 detik lokal; simpan 3 foto selesai dalam 1 request; badge rekap muncul dalam siklus polling 4 detik yang sudah ada

**Constraints**: Foto per slot ≤ 500 KB (binary); 1 request simpan ≤ 3 foto; tanpa kamera → halaman tetap utuh; NOL perubahan pada `KioskController`, logika status/waktu kehadiran, dan tabel `attendances`

**Scale/Scope**: 1 halaman baru (siswa), 1 kolom badge baru (rekap guru), 1 tabel baru, 3 route baru; demo 10 siswa X TKJ 1

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---|---|---|
| I. Satu Sumber Kebenaran | ✅ LULUS | Mengacu US-10 (P1), F3 (tahap 1), dan kewajiban persetujuan orang tua Bab 6.2/9 PRD |
| II. Waktu Selalu dari Server | ✅ LULUS | `enrolled_at`, `last_reenrolled_at`, `consented_at` diambil dari `now()` server — klien tidak mengirim waktu |
| III. Verifikasi ≠ Status | ✅ LULUS | Tidak menyentuh penentuan status kehadiran sama sekali |
| IV. Status Kehadiran Terbatas | ✅ LULUS | Tidak ada status kehadiran baru; "Terdaftar/Belum" adalah status pendaftaran kredensial, bukan kehadiran |
| V. Audit Trail | ✅ LULUS* | Record pendaftaran sendiri adalah jejak: siapa (student_id), kapan (timestamp server), persetujuan (consented_at). Tabel audit global masih tahap lanjutan (konsisten dengan basis kode saat ini) |
| VI. R1–R8 & F1–F6 Harfiah | ✅ LULUS | Tidak menyentuh alur absen; F3 tahap 1 = pengumpulan referensi tanpa pengenalan (FR-011 spec); persetujuan orang tua diwujudkan sebagai pernyataan di layar (FR-012) |
| VII. Kiosk Terikat Kelas | ✅ LULUS | Kiosk tidak diubah |
| VIII. Privasi Data Siswa | ✅ LULUS | Tanpa pengenalan; foto di penyimpanan privat; disajikan hanya dalam halaman wajib login (pratinjau milik siswa sendiri + rekap guru/admin); pernyataan persetujuan sebelum merekam |
| IX. Stack Tetap | ✅ LULUS | Laravel 13 + Blade + Alpine + Tailwind (Vite) + SQLite; Eloquent; tanpa package baru |
| X. Sederhana Dulu | ✅ LULUS | Tahap 1 saja: pengumpulan referensi. Pengenalan wajah, embedding, kuota percobaan F3 = tahap berikutnya |
| XI. Dapat Diuji Manusia | ✅ LULUS | quickstart.md berisi langkah uji manual berbahasa Indonesia untuk pemula |
| XII. Bahasa Awam | ✅ LULUS | UI dan pesan error berbahasa Indonesia sederhana |

**Re-check setelah desain (Phase 1)**: tetap LULUS — desain menambah 1 tabel + 1 controller + 1 view; tidak ada kompleksitas baru di luar pola yang sudah ada (lihat Complexity Tracking: kosong).

## Project Structure

### Documentation (this feature)

```text
specs/003-face-enrollment/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
│   └── routes.md
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── FaceEnrollmentController.php    # Halaman, status JSON, simpan/daftar-ulang (peran siswa)
├── Models/
│   └── FaceEnrollment.php              # Model pendaftaran wajah (relasi ke Student)
database/
├── migrations/
│   └── xxxx_create_face_enrollments_table.php
resources/views/
└── face-enrollment/
    └── show.blade.php                  # Halaman pendaftaran (Alpine: kamera, countdown 3 dtk, slot ulang)

tests/Feature/
└── FaceEnrollmentTest.php              # Uji: simpan, daftar ulang, gagal-aman, privasi, badge guru
```

Modifikasi yang sudah ada (minimal):
- `resources/views/layouts/navigation.blade.php` + `resources/views/dashboard.blade.php` — tautkan menu "Pendaftaran Wajah" untuk peran siswa (pola menu yang sudah ada)
- `app/Http/Controllers/TeacherAttendanceController.php` + `resources/views/teacher/attendance-today.blade.php` — tambahkan field badge `face` + kolom badge (pola kolom yang sudah ada)

**Structure Decision**: Satu project Laravel monolit mengikuti struktur spec 002 — tidak ada sub-folder baru di luar pola (`Controllers/`, `Models/`, `migrations/`, `views/`). Pendaftaran wajah diberi controller sendiri agar `StudentAttendanceController` dan `KioskController` tidak tersentuh (FR-013).

## Complexity Tracking

> Tidak ada pelanggaran konstitusi. Desain memakai pola MVC standar yang sudah terbukti di spec 002; tidak ada pattern tambahan (tanpa service layer, tanpa repository, tanpa package baru).
