# SIAP KNBI — Sistem Informasi Absensi Pelajar

> **Aplikasi Web Absensi Siswa SMK Karya Nugraha (KNBI)**
> Status Saat Ini: **Milestone 0 — Fondasi Login & Dashboard**

Dokumen ini ditulis secara bertahap dan ramah pemula, khusus agar siapa pun — termasuk yang belum pernah menulis kode pemrograman — dapat menginstal, menjalankan, dan menguji aplikasi ini dari awal sampai selesai.

---

## 📌 Apa Itu Milestone 0?

Milestone 0 adalah **fondasi awal** aplikasi. Pada tahap ini, pintu masuk sistem (halaman masuk/login) dan beranda utama (dashboard) untuk 3 peran pengguna telah selesai dibangun:
1. **Administrator**: Pengelola sistem sekolah
2. **Guru**: Wali kelas / guru pengajar
3. **Siswa**: Pelajar yang diabsen

> ⚠️ **Catatan Penting**: Fitur tap kartu RFID, scan wajah (Face ID), rekap jam masuk/pulang, dan kamera absensi sengaja **belum dibuka** pada Milestone 0 sesuai prinsip pengembangan bertahap (Konstitusi Proyek Bagian X: *Sederhana Dulu, Kompleks Belakangan*).

---

## 💻 Prasyarat Sistem

Sebelum menjalankan aplikasi, pastikan komputer Anda memiliki 3 perangkat lunak berikut:

| Perangkat Lunak | Versi Minimal | Fungsi Sederhana | Cara Mendapatkan |
|---|---|---|---|
| **PHP** | 8.3 ke atas | Mesin penerjemah kode aplikasi | [windows.php.net](https://windows.php.net/download/) atau via perintah `winget install PHP.PHP.8.3` |
| **Composer** | 2.x ke atas | Pengunduh paket pustaka PHP | [getcomposer.org](https://getcomposer.org/download/) |
| **Node.js & npm** | 18 ke atas | Pengolah tampilan desain (Tailwind CSS) | [nodejs.org](https://nodejs.org/) |

> 💡 **Tips Pengguna Windows**: Jika Anda membuka Terminal PowerShell dengan hak administrator, Anda dapat menginstal PHP langsung dengan perintah:
> ```powershell
> winget install --id PHP.PHP.8.3
> ```

---

## 🚀 Panduan Menjalankan Aplikasi dari Nol

Ikuti 7 langkah praktis berikut di terminal (PowerShell / Command Prompt):

### Langkah 1: Buka Folder Proyek di Terminal
Buka PowerShell dan arahkan ke folder proyek ini:
```powershell
cd c:\Users\LKS\Projects\siap-sekolah
```

### Langkah 2: Siapkan File Pengaturan Lingkungan (`.env`)
Salin file contoh pengaturan menjadi file aktif:
```powershell
Copy-Item .env.example .env
```

### Langkah 3: Pasang Pustaka Backend (PHP)
Unduh pustaka yang dibutuhkan aplikasi:
```powershell
composer install
```
Lalu buat kunci keamanan enkripsi sesi:
```powershell
php artisan key:generate
```

### Langkah 4: Pasang Pustaka Tampilan & Kompilasi Desain
Pasang paket antarmuka lalu kompilasi aset tampilan:
```powershell
npm install
npm run build
```

### Langkah 5: Siapkan Database & Isi Akun Contoh
Buat tabel database lokal (SQLite) dan masukkan 3 akun uji coba:
```powershell
php artisan migrate:fresh --seed
```
*Database otomatis dibuat di file `database/database.sqlite` tanpa perlu menyalakan server MySQL terpisah.*

### Langkah 6: Jalankan Server Aplikasi
Nyalakan server lokal dengan perintah:
```powershell
php artisan serve
```
Setelah berjalan, terminal akan menampilkan pesan:
`INFO Server running on [http://127.0.0.1:8000].`

### Langkah 7: Buka di Browser
Buka browser Google Chrome, Mozilla Firefox, atau Microsoft Edge, lalu ketik alamat:
👉 **[http://localhost:8000](http://localhost:8000)**

---

## 🔑 Data Akun Uji Coba (Seed Accounts)

Gunakan salah satu dari 3 akun berikut untuk menguji masuk ke aplikasi:

| Peran | Alamat Email | Kata Sandi | Apa yang Ditampilkan di Dashboard? |
|---|---|---|---|
| **Administrator** | `admin@knbi.sch.id` | `password` | Header nama **Admin KNBI**, lencana biru **Administrator**, akses fitur master |
| **Guru** | `guru@knbi.sch.id` | `password` | Header nama **Bu Sari**, lencana hijau **Guru / Wali Kelas**, ringkasan pantau kelas |
| **Siswa** | `siswa@knbi.sch.id` | `password` | Header nama **Raka Siswa**, lencana kuning **Siswa**, riwayat absensi pribadi |

> 🔒 *Catatan Keamanan*: Kata sandi di database disimpan dalam format acak terenkripsi (bcrypt hashing). Kata sandi `password` hanya diperuntukkan bagi tahap pengujian lokal dan wajib diganti sebelum aplikasi dipakai resmi di sekolah.

---

## 🧪 Panduan Pengujian Manual (Langkah demi Langkah)

Lakukan 10 pengujian berikut di browser untuk memverifikasi aplikasi berfungsi 100%:

### Skenario 1: Uji Masuk Admin
1. Buka `http://localhost:8000` (otomatis dialihkan ke halaman masuk `/login`).
2. Masukkan email: `admin@knbi.sch.id` dan kata sandi: `password`.
3. Klik tombol **Masuk**.
4. **Hasil**: Halaman berpindah ke `/dashboard`. Tampak tulisan **SIAP — SMK Karya Nugraha**, nama pengguna **Admin KNBI**, dan lencana peran **Administrator**.

### Skenario 2: Uji Masuk Guru
1. Klik tombol **Keluar** di pojok kanan atas.
2. Masuk kembali dengan email: `guru@knbi.sch.id` dan kata sandi: `password`.
3. **Hasil**: Dashboard terbuka menampilkan nama **Bu Sari** dan lencana **Guru / Wali Kelas**.

### Skenario 3: Uji Masuk Siswa
1. Keluar dari akun guru, lalu masuk dengan email: `siswa@knbi.sch.id` dan kata sandi: `password`.
2. **Hasil**: Dashboard terbuka menampilkan nama **Raka Siswa** dan lencana **Siswa**.

### Skenario 4: Kata Sandi Salah
1. Di halaman login, ketik email: `admin@knbi.sch.id`, tetapi isi kata sandi asal: `salah123`.
2. Klik tombol **Masuk**.
3. **Hasil**: Halaman tetap di `/login` dan muncul pesan ramah: *"Email atau kata sandi salah. Silakan coba lagi."*

### Skenario 5: Form Kosong
1. Di halaman login, kosongkan kedua kotak input lalu klik **Masuk**.
2. **Hasil**: Muncul pesan validasi: *"Kolom ini wajib diisi."* di bawah kotak yang kosong.

### Skenario 6: Format Email Tidak Valid
1. Ketik email: `bukan-email-valid` dan kata sandi: `password`.
2. Klik **Masuk**.
3. **Hasil**: Muncul pesan: *"Format email tidak valid."*

### Skenario 7: Fungsi Tombol Keluar (Logout)
1. Masuk ke dashboard dengan akun mana saja.
2. Klik tombol **Keluar**.
3. **Hasil**: Sesi login berakhir dan Anda langsung dialihkan kembali ke halaman `/login`.

### Skenario 8: Keamanan Halaman (Anti-Penyusup)
1. Pastikan Anda sedang dalam keadaan keluar (logout) atau buka jendela *Incognito / Private Window*.
2. Ketik langsung di bilah alamat: `http://localhost:8000/dashboard`.
3. **Hasil**: Sistem menolak akses dan otomatis mengarahkan ke halaman `/login`.

### Skenario 9: Uji Tombol Kembali Browser (Back Button)
1. Masuk ke akun Anda, lalu klik tombol **Keluar**.
2. Tekan tombol **Back** (Kembali) di browser.
3. **Hasil**: Halaman dashboard tidak memunculkan data sesi sebelumnya karena sistem telah memasang proteksi pencegah riwayat cache.

### Skenario 10: Menu Navigasi Layanan Sekolah
1. Masuk ke dashboard.
2. Klik salah satu menu: **Data Siswa**, **Jadwal Kelas**, **Kiosk Absen**, atau **Laporan**.
3. **Hasil**: Tidak terjadi error teknis/halaman rusak; muncul pemberitahuan ramah bahwa fitur tersebut segera hadir pada tahap berikutnya.

---

## 🛠️ Mengatasi Masalah Umum (Troubleshooting)

- **Port 8000 sudah dipakai aplikasi lain?**
  Jalankan server di port lain, misalnya port 8080:
  ```powershell
  php artisan serve --port=8080
  ```
  Lalu buka `http://localhost:8080`.

- **Database ingin direset ke kondisi awal?**
  Jalankan ulang perintah migrasi dan benih data:
  ```powershell
  php artisan migrate:fresh --seed
  ```

---

## 📁 Struktur Dokumen Spesifikasi
Dokumentasi teknis proyek dikelola menggunakan standar **Spec Kit**:
- `specs/001-fondasi-login-dashboard/spec.md` — Spesifikasi kebutuhan fitur
- `specs/001-fondasi-login-dashboard/plan.md` — Rencana implementasi teknis
- `specs/001-fondasi-login-dashboard/data-model.md` — Desain tabel dan model data
- `specs/001-fondasi-login-dashboard/quickstart.md` — Skenario pengujian lengkap
- `specs/001-fondasi-login-dashboard/tasks.md` — Daftar rincian tugas pengerjaan
- `.specify/memory/constitution.md` — Konstitusi tata kelola proyek (Prinsip Non-Negotiable)
