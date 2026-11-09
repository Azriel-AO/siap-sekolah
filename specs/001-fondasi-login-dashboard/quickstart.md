# Panduan Validasi Manual: Fondasi Login & Dashboard (Milestone 0)

**Feature**: `001-fondasi-login-dashboard`
**Date**: 2026-11-09
**Untuk**: Siapa saja — termasuk yang belum pernah coding

> Dokumen ini menjelaskan cara membuktikan bahwa aplikasi berjalan dengan benar,
> langkah demi langkah. Ikuti urutan ini setelah selesai menjalankan aplikasi.
> Cara menjalankan aplikasi ada di `README.md` di folder utama project.

---

## Prasyarat Sebelum Mulai

Pastikan sudah menjalankan perintah-perintah berikut (ada di README):

```bash
php artisan migrate
php artisan db:seed
npm run dev      # (biarkan berjalan di terminal terpisah)
php artisan serve
```

Lalu buka browser dan ketik: **http://localhost:8000**

---

## Skenario 1 — Login Berhasil sebagai Admin

**Tujuan**: Membuktikan bahwa admin bisa masuk dan melihat dashboard dengan label "Admin".

**Langkah**:
1. Buka browser, ketik `http://localhost:8000`
2. Seharusnya diarahkan otomatis ke `http://localhost:8000/login`
3. Di kolom **Email**, ketik: `admin@knbi.sch.id`
4. Di kolom **Kata Sandi**, ketik: `password`
5. Klik tombol **Masuk**

**Hasil yang diharapkan** ✅:
- Halaman berpindah ke `http://localhost:8000/dashboard`
- Di bagian atas (header) tampil tulisan **"SIAP — SMK Karya Nugraha"**
- Di header juga tampil nama **"Admin KNBI"** dan label **"Admin"**
- Ada 4 menu: Data Siswa, Jadwal Kelas, Kiosk Absen, Laporan
- Ada tombol **"Keluar"**

---

## Skenario 2 — Login Berhasil sebagai Guru

**Langkah**:
1. Klik tombol **Keluar** (dari skenario sebelumnya, atau buka tab baru)
2. Di `http://localhost:8000/login`, masukkan:
   - Email: `guru@knbi.sch.id`
   - Kata Sandi: `password`
3. Klik **Masuk**

**Hasil yang diharapkan** ✅:
- Dashboard tampil dengan nama **"Bu Sari"** dan label **"Guru"**
- Tampilan header dan menu sama seperti admin

---

## Skenario 3 — Login Berhasil sebagai Siswa

**Langkah**:
1. Logout, lalu masukkan:
   - Email: `siswa@knbi.sch.id`
   - Kata Sandi: `password`

**Hasil yang diharapkan** ✅:
- Dashboard tampil dengan nama **"Raka Siswa"** dan label **"Siswa"**

---

## Skenario 4 — Login Gagal (Password Salah)

**Langkah**:
1. Di halaman login, masukkan:
   - Email: `admin@knbi.sch.id`
   - Kata Sandi: `salah123`
2. Klik **Masuk**

**Hasil yang diharapkan** ✅:
- Halaman tetap di `/login` (tidak berpindah ke dashboard)
- Muncul pesan: **"Email atau kata sandi salah. Silakan coba lagi."**
- Kolom email masih terisi, kolom password dikosongkan

---

## Skenario 5 — Validasi Form Kosong

**Langkah**:
1. Di halaman login, biarkan kedua kolom **kosong**
2. Klik **Masuk**

**Hasil yang diharapkan** ✅:
- Muncul pesan di bawah kolom email: **"Kolom ini wajib diisi."**
- Form tidak dikirim ke server

---

## Skenario 6 — Validasi Format Email

**Langkah**:
1. Di kolom email, ketik: `bukan-email`
2. Di kolom kata sandi, ketik: `password`
3. Klik **Masuk**

**Hasil yang diharapkan** ✅:
- Muncul pesan: **"Format email tidak valid."**
- Form tidak dikirim

---

## Skenario 7 — Logout Berfungsi

**Langkah**:
1. Login dengan akun mana saja
2. Klik tombol **Keluar**

**Hasil yang diharapkan** ✅:
- Diarahkan kembali ke halaman `/login`
- Sesi tidak ada lagi

---

## Skenario 8 — Proteksi Halaman Dashboard

**Tujuan**: Membuktikan bahwa orang yang belum login tidak bisa masuk ke dashboard.

**Langkah**:
1. Pastikan sudah logout (atau buka jendela browser **Incognito/Private**)
2. Ketik langsung di address bar: `http://localhost:8000/dashboard`

**Hasil yang diharapkan** ✅:
- Browser diarahkan otomatis ke `http://localhost:8000/login`
- Tidak ada konten dashboard yang tampil

---

## Skenario 9 — Tombol "Kembali" Setelah Logout

**Langkah**:
1. Login, lalu logout
2. Tekan tombol **Kembali** (back) di browser

**Hasil yang diharapkan** ✅:
- Tetap di halaman `/login`, atau kalaupun kembali ke halaman dashboard sebelumnya,
  kontennya tidak tampil dan diarahkan lagi ke `/login`
- Sesi tidak dipulihkan hanya dengan menekan back

---

## Skenario 10 — Menu Navigasi Placeholder

**Langkah**:
1. Login dengan akun mana saja
2. Klik menu **"Data Siswa"**, lalu coba menu lainnya

**Hasil yang diharapkan** ✅:
- Tidak ada error teknis (tidak ada halaman "500 Server Error" atau "Route Not Found")
- Boleh tidak terjadi apa-apa, atau muncul pesan **"Segera hadir"**

---

## Checklist Akhir

Centang semua sebelum menyatakan Milestone 0 selesai:

- [x] Skenario 1: Login admin ✅ dashboard tampil nama + label "Admin"
- [x] Skenario 2: Login guru ✅ dashboard tampil nama + label "Guru"
- [x] Skenario 3: Login siswa ✅ dashboard tampil nama + label "Siswa"
- [x] Skenario 4: Password salah ✅ pesan error ramah
- [x] Skenario 5: Form kosong ✅ pesan validasi
- [x] Skenario 6: Format email salah ✅ pesan validasi
- [x] Skenario 7: Logout ✅ diarahkan ke login
- [x] Skenario 8: Akses langsung dashboard tanpa login ✅ diarahkan ke login
- [x] Skenario 9: Tombol back setelah logout ✅ sesi tidak pulih
- [x] Skenario 10: Menu placeholder ✅ tidak ada error teknis
- [x] README ✅ bisa diikuti orang yang belum pernah coding
- [x] Semua teks UI dalam Bahasa Indonesia ✅
