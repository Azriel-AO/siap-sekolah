# Data Model: Fondasi Login & Dashboard (Milestone 0)

**Feature**: `001-fondasi-login-dashboard`
**Date**: 2026-11-09

---

## Entitas & Tabel Database

### Tabel: `users`

> Tabel ini sudah dibuat oleh Laravel Breeze saat instalasi.
> Kita hanya menambahkan satu kolom: `role`.

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| `id` | `bigint` UNSIGNED | ❌ | auto | Primary key, auto-increment |
| `name` | `varchar(255)` | ❌ | — | Nama lengkap pengguna |
| `email` | `varchar(255)` | ❌ | — | Email unik, digunakan untuk login |
| `email_verified_at` | `timestamp` | ✅ | NULL | Tidak dipakai di M0 (Breeze menyertakannya) |
| `password` | `varchar(255)` | ❌ | — | Kata sandi ter-hash (bcrypt) |
| `role` | `varchar(10)` | ❌ | `'siswa'` | Peran: `'admin'` / `'guru'` / `'siswa'` |
| `remember_token` | `varchar(100)` | ✅ | NULL | Token "ingat saya" (bawaan Laravel) |
| `created_at` | `timestamp` | ✅ | NULL | Waktu akun dibuat |
| `updated_at` | `timestamp` | ✅ | NULL | Waktu akun terakhir diubah |

**Nilai valid kolom `role`**: `'admin'` · `'guru'` · `'siswa'`

**Aturan validasi**:
- `email` HARUS unik di seluruh tabel
- `role` HARUS salah satu dari tiga nilai di atas — tidak boleh nilai lain
- `password` HARUS disimpan dalam bentuk hash, tidak boleh teks biasa

---

### Tabel: `sessions`

> Dikelola sepenuhnya oleh Laravel. Tidak perlu menulis kode apapun untuk tabel ini.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | `varchar(255)` | ID sesi unik |
| `user_id` | `bigint` | Foreign key ke `users.id` (nullable saat guest) |
| `ip_address` | `varchar(45)` | IP pengguna |
| `user_agent` | `text` | Informasi browser pengguna |
| `payload` | `longtext` | Data sesi (terenkripsi) |
| `last_activity` | `int` | Timestamp aktivitas terakhir |

**Catatan**: Tabel `sessions` hanya diperlukan jika konfigurasi `SESSION_DRIVER=database`
di `.env`. Untuk M0, cukup gunakan driver `file` (default) — tidak perlu tabel ini.

---

## Diagram Relasi

```
users (1) ─── (*) sessions
```

Sesederhana itu untuk Milestone 0. Tidak ada relasi ke tabel lain.

---

## Model Eloquent: `User`

File: `app/Models/User.php`

**Atribut yang perlu ditambahkan ke `$fillable`**:
```
'role'
```

**Tidak perlu** accessor/mutator atau relasi tambahan di M0.

---

## Migration yang Dibutuhkan

### Migration 1 (bawaan Breeze — sudah ada, tidak diubah)
`create_users_table` — membuat tabel `users` tanpa kolom `role`

### Migration 2 (kita buat)
`add_role_to_users_table` — menambahkan kolom `role` ke tabel `users`

```
Schema::table('users', function (Blueprint $table) {
    $table->string('role', 10)->default('siswa')->after('password');
});
```

---

## Seed Data

File: `database/seeders/UserSeeder.php`

| `name` | `email` | `password` | `role` |
|--------|---------|------------|--------|
| Admin KNBI | admin@knbi.sch.id | Hash::make('password') | admin |
| Bu Sari | guru@knbi.sch.id | Hash::make('password') | guru |
| Raka Siswa | siswa@knbi.sch.id | Hash::make('password') | siswa |
