# Research: Fondasi Login & Dashboard (Milestone 0)

**Feature**: `001-fondasi-login-dashboard`
**Date**: 2026-11-09
**Status**: Selesai — tidak ada NEEDS CLARIFICATION (stack dikunci di konstitusi v2.0.0)

---

## Keputusan Teknis

### 1. Starter Kit Autentikasi: Laravel Breeze (bukan Jetstream atau Fortify)

**Decision**: Gunakan **Laravel Breeze** dengan stack `blade`.

**Rationale**:
- Breeze adalah starter kit paling ringan dan paling banyak tutorialnya
- Stack `blade` menghasilkan halaman Blade biasa — tidak ada Vue/React/Livewire yang membingungkan pemula
- Sudah termasuk Tailwind CSS via Vite — tidak perlu konfigurasi tambahan
- Perintah instalasi cukup 2 baris: `composer require laravel/breeze` + `php artisan breeze:install blade`
- Jetstream terlalu kompleks (tim/organisasi, 2FA) untuk Milestone 0

**Alternatives considered**:
- Jetstream — ditolak: terlalu banyak fitur tidak perlu untuk M0, membingungkan pemula
- Fortify — ditolak: perlu konfigurasi view manual, tidak ada scaffolding siap pakai
- Auth manual dari nol — ditolak: melanggar Prinsip IX (gunakan cara yang umum) dan X (sederhana dulu)

---

### 2. Kolom `role` di Tabel `users`: Enum String (bukan tabel terpisah)

**Decision**: Tambah kolom `role` bertipe `string` dengan nilai enum: `'admin'`, `'guru'`, `'siswa'`.
Disimpan langsung di tabel `users`, bukan tabel `roles` terpisah.

**Rationale**:
- Hanya 3 peran yang sudah tetap (Prinsip IV konstitusi membatasi lingkup)
- Relasi many-to-many (tabel pivot roles) adalah overkill untuk 3 nilai statis
- Satu kolom string jauh lebih mudah dipahami dan di-query pemula
- Contoh query: `auth()->user()->role === 'admin'` — langsung dimengerti

**Alternatives considered**:
- Tabel `roles` terpisah + pivot — ditolak: kompleksitas tidak perlu untuk M0
- Laravel Spatie Permission package — ditolak: konstitusi melarang package eksotis untuk kebutuhan sesederhana ini

---

### 3. Proteksi Rute: Middleware `auth` Bawaan Laravel

**Decision**: Gunakan middleware `auth` bawaan Laravel untuk melindungi rute `/dashboard`.

**Rationale**:
- `middleware('auth')` adalah fitur inti Laravel, sudah terdokumentasi di halaman pertama docs
- Tidak perlu package tambahan
- Redirect otomatis ke `/login` jika belum login sudah ditangani oleh `RedirectIfAuthenticated`

**Alternatives considered**:
- Gate/Policy Laravel — ditolak: untuk otorisasi aksi spesifik, bukan untuk proteksi halaman
- Custom middleware — ditolak: tidak perlu untuk use case sesederhana ini

---

### 4. Redirect Pasca-Login: Berbasis Peran (`role`)

**Decision**: Override method `redirectTo()` di `AuthenticatedSessionController`
(bawaan Breeze) untuk redirect berdasarkan `auth()->user()->role`.

**Rationale**:
- Semua tiga peran menuju `/dashboard` yang sama — cukup satu halaman dashboard
  yang menampilkan konten berbeda berdasarkan `$user->role`
- Tidak perlu tiga URL dashboard terpisah (`/admin/dashboard`, `/guru/dashboard`, dll.) di M0
- Perbedaan tampilan per peran hanya di label ("Admin"/"Guru"/"Siswa") — cukup kondisional Blade

**Alternatives considered**:
- Tiga halaman dashboard berbeda — ditolak: overkill untuk perbedaan yang hanya label teks
- Middleware role-based routing — ditolak: belum diperlukan di M0

---

### 5. Database: SQLite dengan File Tunggal

**Decision**: Gunakan SQLite (`DB_CONNECTION=sqlite`) dengan file `database/database.sqlite`.

**Rationale**:
- Tidak perlu instalasi MySQL/PostgreSQL server — developer cukup buka terminal dan jalankan
- Laravel 13 sudah mendukung SQLite out-of-the-box; tidak ada konfigurasi tambahan
- README lebih pendek dan lebih mudah diikuti pemula
- Migrasi ke MySQL saat deployment: cukup ganti `DB_CONNECTION=mysql` di `.env` dan isi kredensial

**Alternatives considered**:
- MySQL — ditolak: perlu instalasi server, konfigurasi user/password, troubleshooting koneksi
- PostgreSQL — ditolak: sama dengan MySQL, lebih jarang tutorialnya di komunitas PHP Indonesia

---

### 6. Data Seed: `DatabaseSeeder` + `UserSeeder`

**Decision**: Buat `UserSeeder` yang dipanggil dari `DatabaseSeeder`.
Gunakan `Hash::make()` untuk enkripsi password (memenuhi FR-017).

**Rationale**:
- Laravel Seeder adalah cara standar membuat data contoh
- `php artisan db:seed` adalah satu perintah — mudah dijalankan pemula
- Password di-hash dengan bcrypt (default `Hash::make()`) — tidak disimpan teks biasa

**Credentials seed yang akan didokumentasikan di README**:

| Nama | Email | Password | Peran |
|------|-------|----------|-------|
| Admin KNBI | admin@knbi.sch.id | password | admin |
| Bu Sari (Guru) | guru@knbi.sch.id | password | guru |
| Raka Siswa | siswa@knbi.sch.id | password | siswa |

> Password `password` dipilih karena mudah diingat untuk development.
> README WAJIB menyebut bahwa password ini harus diganti sebelum production.

---

## Semua NEEDS CLARIFICATION: Tidak Ada

Stack sudah dikunci di konstitusi v2.0.0. Tidak ada keputusan arsitektur yang ambigu.
