# Contracts: Rute Web (Milestone 0)

**Feature**: `001-fondasi-login-dashboard`
**Date**: 2026-11-09
**File**: `routes/web.php`

---

## Daftar Rute

> Analogi: "rute" adalah seperti alamat rumah di internet.
> Ketika pengguna mengetik `/login` di browser, Laravel tahu harus menampilkan halaman apa.

| Method | URL | Middleware | Controller / Action | Keterangan |
|--------|-----|------------|---------------------|------------|
| GET | `/` | — | Redirect ke `/dashboard` | Halaman utama selalu ke dashboard |
| GET | `/login` | `guest` | `AuthenticatedSessionController@create` | Tampilkan form login |
| POST | `/login` | `guest` | `AuthenticatedSessionController@store` | Proses login |
| POST | `/logout` | `auth` | `AuthenticatedSessionController@destroy` | Proses logout |
| GET | `/dashboard` | `auth` | `DashboardController@index` | Halaman dashboard |

---

## Penjelasan Middleware

**`auth`** — "Hanya untuk yang sudah login"
> Jika pengguna belum login dan mencoba buka `/dashboard`, Laravel otomatis
> mengarahkan ke `/login`. Ini yang memenuhi FR-014.

**`guest`** — "Hanya untuk yang belum login"
> Jika pengguna sudah login dan mencoba buka `/login`, Laravel otomatis
> mengarahkan ke `/dashboard`. Mencegah pengguna login dua kali.

---

## Alur Login (Diagram Sederhana)

```
Pengguna buka /login
       ↓
Isi email + password → klik "Masuk"
       ↓
POST /login
       ↓
[Validasi server]
   ├── GAGAL → kembali ke /login + pesan error
   └── BERHASIL → redirect ke /dashboard
              ↓
         GET /dashboard (middleware auth)
              ↓
         Tampilkan dashboard dengan nama + role pengguna
```

---

## Alur Logout

```
Pengguna klik tombol "Keluar"
       ↓
POST /logout (form dengan CSRF token)
       ↓
Sesi dihapus
       ↓
Redirect ke /login
```

---

## Catatan Keamanan

- Semua form POST menggunakan **CSRF token** (`@csrf`) — Laravel menolak request tanpa token ini
  secara otomatis. Ini mencegah serangan dari website lain yang berpura-pura menjadi pengguna.
- Tombol "Keluar" HARUS berupa form POST (bukan link `<a>`), karena logout harus mengubah
  state server dan perlu perlindungan CSRF.
