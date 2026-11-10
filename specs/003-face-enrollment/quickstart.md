# Panduan Uji Coba: Pendaftaran Mandiri Foto Wajah (Face Enrollment)

**Feature**: `003-face-enrollment` | **Date**: 2026-11-10

> Semua langkah ditulis untuk orang awam. Akun uji: `siswa@knbi.sch.id` /
> `guru@knbi.sch.id` (kata sandi: `password`). Kamera = webcam laptop atau webcam
> USB. Fitur ini **hanya mengumpulkan foto** — tidak ada pengenalan wajah.

---

## Persiapan (sekali di awal)

```powershell
php artisan migrate:fresh --seed
php artisan serve
```

Buka `http://localhost:8000` — lakukan langkah di bawah pada browser.

---

## Skenario 1: Siswa mendaftar wajah pertama kali (alur bahagia)

1. Login sebagai siswa: `siswa@knbi.sch.id` / `password`.
2. Klik menu **Pendaftaran Wajah** (bar navigasi atas) atau kartu di dashboard.
3. **Seharusnya**: muncul halaman berisi **pernyataan izin orang tua/wali** dengan kotak centang. Kamera **belum** menyala.
4. Centang kotak pernyataan, lalu klik **"Mulai Kamera & Ambil Foto"**.
5. Browser menanyakan izin kamera → klik **Izinkan**.
6. **Seharusnya**: pratinjau kamera tampil (wajah Anda terlihat).
7. Klik **"Ambil Foto"** → muncul hitungan mundur **3… 2… 1…** → foto pertama terisi di slot 1 (pratinjau foto tampil).
8. Ulangi hingga slot 1, 2, 3 terisi (setiap ambil diawali hitungan mundur 3 detik).
9. Klik **"Simpan Pendaftaran"**.
10. **Seharusnya**: muncul pesan sukses dan halaman berubah menampilkan status **"Terdaftar"**, tanggal pendaftaran (hari ini, waktu server), dan pratinjau 3 foto Anda.

## Skenario 2: Ulang pengambilan sebelum simpan

1. Ulangi Skenario 1 sampai langkah 8 (belum klik simpan).
2. Klik tombol **"Ulangi"** pada slot foto yang kurang bagus.
3. **Seharusnya**: slot itu kembali kosong, lalu bisa diambil ulang (countdown 3 detik lagi).
4. Setelah 3 slot terisi lagi, klik **"Simpan Pendaftaran"** → sukses seperti Skenario 1.

## Skenario 3: Simpan ditolak bila foto belum lengkap

1. Ambil hanya 1–2 foto, lalu klik **"Simpan Pendaftaran"**.
2. **Seharusnya**: muncul pesan yang menyebut foto masih kurang (contoh: *"Foto wajah wajib lengkap 3 foto. Saat ini baru 2 foto sah."*) dan **tidak ada** yang tersimpan.

## Skenario 4: Daftar ulang (ganti foto lama)

1. Masih sebagai siswa yang sudah terdaftar, buka menu **Pendaftaran Wajah**.
2. **Seharusnya**: status "Terdaftar" + tanggal + pratinjau foto lama.
3. Klik **"Daftar Ulang"** → muncul dialog konfirmasi → pilih **Batal** dulu.
   - **Seharusnya**: tidak ada yang berubah (foto lama aman).
4. Klik **"Daftar Ulang"** lagi → konfirmasi **Ya, Daftar Ulang**.
5. **Seharusnya**: kembali ke alur 3 foto (dengan pernyataan persetujuan lagi). Ambil 3 foto baru → simpan.
6. **Seharusnya**: status tetap "Terdaftar", tanggal daftar ulang terbaru tampil, pratinjau kini menampilkan **foto baru** (foto lama hilang dari server).

## Skenario 5: Guru melihat badge di rekap

1. Jendela Incognito, login guru: `guru@knbi.sch.id` / `password` → menu **Rekap Harian**.
2. **Seharusnya**: tabel rekap punya kolom baru **"Wajah"**: baris siswa yang barusan mendaftar menampilkan badge hijau **"Terdaftar"**; siswa lain abu-abu **"Belum"**.
3. Biarkan tab rekap terbuka; di tab lain, daftar wajah sebagai siswa lain tidak dimungkinkan (akun siswa demo hanya 1) — cukup pastikan badge tidak salah: siswa yang belum mendaftar wajahnya **tidak** boleh bertanda Terdaftar.
4. Bila siswa mendaftar saat tab rekap terbuka, badge berubah sendiri dalam ≤ 4 detik (polling) tanpa reload.

## Skenario 6: Kamera tidak ada / izin ditolak (gagal-aman)

1. Klik **Tolak/Block** saat browser menanyakan izin kamera (atau uji di PC tanpa webcam).
2. **Seharusnya**: pratinjau kamera **tidak muncul**, tampil pesan berbahasa Indonesia berisi cara mengizinkan kamera, **tidak ada error/layar rusak**, dan tampilan halaman tetap utuh.
3. Tombol ambil foto tidak aktif; data siswa dan sesi login aman.

## Skenario 7: Privasi foto

1. Sebagai **siswa**, buka langsung `http://localhost:8000/guru/rekap-harian` → **seharusnya** 403 (hanya guru/admin).
2. **Keluar/login** (bukan siswa pemilik, gunakan jendela incognito tanpa login) dan coba tebak-tebak URL file foto (mis. `/storage/face-photos/...` atau `/face-photos/...`) → **seharusnya** semua 404/403: tidak ada endpoint foto publik.
3. Foto milik siswa hanya tampak di: halaman status miliknya sendiri (setelah login sebagai dia) dan rekap guru/admin.
4. Sebagai **guru**, buka `http://localhost:8000/siswa/pendaftaran-wajah` → **seharusnya** 403 "Halaman ini hanya untuk siswa."

## Skenario 8: Kiosk tidak terpengaruh (regresi)

1. Buka `http://localhost:8000/kiosk/X-TKJ-1`, ketik `CARD-TKJ-009` lalu Enter.
2. **Seharusnya**: alur tap berjalan persis seperti sebelum fitur ini (status, jam server, cooldown) — tidak ada perubahan perilaku apa pun.
3. Jalankan uji otomatis lama + baru:

```powershell
php artisan test tests/Feature/KioskAttendanceTest.php
php artisan test tests/Feature/FaceEnrollmentTest.php
```

**Seharusnya**: semua hijau (`OK`).

---

## Ringkasan definisi "selesai"

| Kriteria | Bukti |
|---|---|
| Tap kartu → foto bukti tersimpan | Skenario 8 tidak berubah |
| 3 foto tersimpan terhubung siswa | Skenario 1 + data `face_enrollments` |
| Daftar ulang mengganti foto | Skenario 4 |
| Badge guru | Skenario 5 |
| Gagal-aman tanpa kamera | Skenario 6 |
| Foto privat (guru/admin & pemilik saja) | Skenario 7 |
