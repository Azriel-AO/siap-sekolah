# Implementation Plan: Fondasi Login & Dashboard (Milestone 0)

**Branch**: `001-fondasi-login-dashboard` | **Date**: 2026-11-09 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification dari `specs/001-fondasi-login-dashboard/spec.md`

---

## Summary

Membangun fondasi aplikasi SIAP KNBI: proyek Laravel 13 baru dengan autentikasi email+password
via Laravel Breeze, tiga peran pengguna (admin/guru/siswa), halaman dashboard pasca-login
per peran, proteksi rute via middleware, data seed tiga akun contoh, dan README langkah
demi langkah untuk pembaca yang belum pernah coding.

---

## Technical Context

**Language/Version**: PHP 8.3+

**Framework**: Laravel 13 (rilis terbaru, LTS planned; paling umum dipakai di Indonesia)

**Primary Dependencies**:
- `laravel/breeze` — starter kit autentikasi resmi Laravel (paling ringan, gratis, banyak tutorial)
- `tailwindcss` via Vite — sudah bundled dengan Breeze, tidak perlu instalasi terpisah

**Storage**: SQLite — file tunggal `database/database.sqlite`, tanpa server terpisah.
Migrasi ke MySQL cukup ganti `DB_CONNECTION` di `.env` tanpa ubah kode.

**Testing**: Manual via browser (sesuai Prinsip XI konstitusi — tim pemula)

**Target Platform**: Web browser (Chrome/Firefox/Edge terbaru), layar laptop 1366×768+ dan tablet 768px+

**Project Type**: Web application — Laravel monolitik (Blade templating, server-side rendering)

**Performance Goals**: Login selesai < 5 detik pada WiFi sekolah biasa (SC-001)

**Constraints**:
- Hanya 2 halaman: login dan dashboard
- Tidak ada AJAX/API endpoint — semua server-side Blade
- Tidak ada fitur di luar US-01 (Prinsip X konstitusi)

**Scale/Scope**: ~30 pengguna awal (1 admin, beberapa guru, siswa per kelas); SQLite cukup

---

## Constitution Check

*GATE: Harus lulus sebelum Phase 0 research. Re-check setelah Phase 1 design.*

| Prinsip | Status | Catatan |
|---------|--------|---------|
| I — Satu Sumber Kebenaran | ✅ LULUS | Fitur dapat dilacak ke US-01 di PRD |
| II — Waktu dari Server | ✅ LULUS | M0 tidak mencatat waktu absen; tidak berlaku di milestone ini |
| III — Verifikasi ≠ Status | ✅ LULUS | M0 tidak memiliki alur absensi sama sekali |
| IV — Status Terbatas | ✅ LULUS | M0 tidak memiliki status kehadiran |
| V — Audit Trail | ✅ LULUS | M0 tidak memiliki aksi manual yang perlu dijejak |
| VI — R1–R8 dan F1–F6 | ✅ LULUS | M0 tidak menyentuh alur absen/pulang |
| VII — Kiosk Terikat Kelas | ✅ LULUS | M0 tidak memiliki kiosk |
| VIII — Privasi Data Siswa | ✅ LULUS | M0 tidak menyimpan biometrik |
| IX — Stack Tetap | ✅ LULUS | Laravel 13 + Blade + Tailwind + SQLite sesuai konstitusi v2.0.0 |
| X — Sederhana Dulu | ✅ LULUS | M0 adalah Tahap 1 Fondasi; tidak ada fitur lanjutan |
| XI — Dapat Diuji Manusia | ✅ LULUS | quickstart.md memuat langkah manual step-by-step |
| XII — Bahasa Awam | ✅ LULUS | README dan UI dalam Bahasa Indonesia, sederhana |

**Semua gate lulus. Tidak ada pelanggaran yang perlu dijustifikasi.**

---

## Project Structure

### Documentation (feature ini)

```text
specs/001-fondasi-login-dashboard/
├── plan.md              ← file ini
├── research.md          ← keputusan teknis Phase 0
├── data-model.md        ← skema database Phase 1
├── quickstart.md        ← panduan validasi manual Phase 1
├── contracts/
│   └── routes.md        ← daftar URL dan middleware Phase 1
└── tasks.md             ← dibuat oleh /speckit-tasks (belum ada)
```

### Source Code (repository root — struktur Laravel standar)

```text
siap-sekolah/                    ← root project (folder ini)
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── DashboardController.php
│   │   └── Middleware/           ← (bawaan Laravel, tidak diubah)
│   └── Models/
│       └── User.php              ← tambah kolom 'role'
├── database/
│   ├── migrations/
│   │   └── xxxx_add_role_to_users_table.php  ← tambah kolom role
│   └── seeders/
│       └── UserSeeder.php        ← 3 akun contoh
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php     ← layout utama dengan header + nav
│       ├── auth/
│       │   └── login.blade.php   ← halaman login (dari Breeze, dikustomisasi)
│       └── dashboard.blade.php   ← halaman dashboard
├── routes/
│   └── web.php                   ← definisi rute
├── .env                          ← konfigurasi lokal (tidak di-commit)
├── .env.example                  ← template konfigurasi
└── README.md                     ← panduan untuk pemula
```

**Structure Decision**: Monolitik Laravel standar — satu project, Blade templating,
tidak ada pemisahan frontend/backend. Paling mudah dipelajari tim pemula; tidak perlu
konfigurasi server terpisah.

---

## Complexity Tracking

Tidak ada pelanggaran konstitusi yang perlu dijustifikasi.
