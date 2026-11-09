# Panduan Uji Coba: Milestone DEMO Absensi Kiosk

**Feature**: `002-demo-absensi-kiosk` | **Date**: 2026-11-09

---

## Persiapan Data
Jalankan migrasi dan seed demo:
```powershell
php artisan migrate:fresh --seed
```

Data seed akan menghasilkan:
- 1 Kelas: **X TKJ 1** (`/kiosk/X-TKJ-1`)
- 10 Siswa (`CARD-TKJ-001` s.d. `CARD-TKJ-010`)
  - 5 siswa sudah absen (Hadir & Terlambat)
  - Siswa `CARD-TKJ-009` & `CARD-TKJ-010` **belum absen**

---

## 5 Langkah Uji Demo Ujung-ke-Ujung

### 1. Buka Papan Rekap Guru
1. Buka browser: `http://localhost:8000/login`
2. Masuk dengan akun guru: `guru@knbi.sch.id` / `password`.
3. Buka menu **Rekap Harian Kelas**. Terlihat daftar 10 siswa; perhatikan bahwa siswa nomor 9 & 10 berstatus *"Belum Absen"*.

### 2. Buka Kiosk di Tab/Jendela Baru
1. Buka tab baru: `http://localhost:8000/kiosk/X-TKJ-1`.
2. Tampil layar Kiosk besar siap baca kartu (kotak input otomatis aktif).

### 3. Simulasi Tap Kartu Siswa
1. Ketik UID kartu siswa ke-9: `CARD-TKJ-009` lalu tekan **Enter**.
2. **Hasil di Kiosk**: Muncul kartu pop-up besar berisi nama siswa, jam server, dan status kehadiran (Hadir/Terlambat).

### 4. Periksa Auto-Refresh di Tab Guru
1. Kembali ke tab guru tanpa menekan tombol refresh browser.
2. Dalam $\le$ 4 detik, baris siswa ke-9 otomatis berubah menjadi **Hadir** (atau **Terlambat**) lengkap dengan jam tap!

### 5. Cek Riwayat di Akun Siswa
1. Buka jendela Incognito: `http://localhost:8000/login`.
2. Masuk dengan akun siswa: `siswa@knbi.sch.id` / `password`.
3. Buka menu **Riwayat Absensi**. Pastikan catatan kehadiran hari ini langsung muncul.
