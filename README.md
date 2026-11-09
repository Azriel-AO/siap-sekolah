# SIAP KNBI — Sistem Informasi Absensi Pelajar

> **Aplikasi Web Absensi Siswa SMK Karya Nugraha (KNBI)**
> Status Saat Ini: **Milestone DEMO — Absensi Kiosk RFID & Monitoring** (di atas Fondasi Login & Dashboard)

Dokumen ini ditulis secara bertahap dan ramah pemula, khusus agar siapa pun — termasuk yang belum pernah menulis kode pemrograman — dapat menginstal, menjalankan, dan menguji aplikasi ini dari awal sampai selesai.

---

## 📌 Apa Itu Milestone DEMO?

Milestone DEMO membangun **alur absensi utuh pertama** di atas fondasi login (Milestone 0):

1. **Kiosk Absensi Kelas** — layar layar penuh untuk kelas **X TKJ 1**. Karena belum ada alat pembaca kartu RFID sungguhan, "tap" disimulasikan dengan **mengetik UID kartu** lalu menekan **Enter**. Sistem mencatat jam **dari server** (bukan jam komputer/browser), lalu menentukan status **Hadir** (masuk ≤ 07.00 WIB) atau **Terlambat** (> 07.00 WIB). Tap kedua dalam satu hari tercatat sebagai **absen pulang**.
2. **Rekap Harian Guru** — halaman `/guru/rekap-harian` menampilkan tabel 10 siswa kelas X TKJ 1 yang **diperbarui otomatis tiap 4 detik** tanpa menekan tombol refresh.
3. **Riwayat Absensi Siswa** — halaman `/siswa/riwayat-absensi` menampilkan catatan kehadiran milik siswa yang sedang login saja (privasi terjaga).

Aturan keamanan yang sudah berlaku: **anti-tap-ganda** (kartu yang sama diabaikan selama 60 detik) dan **kiosk terikat kelas** (kartu siswa kelas lain ditolak di kiosk X TKJ 1).

> ⚠️ **Catatan Penting**: Fitur QR darurat, pengesahan guru, Face ID, notifikasi WhatsApp, dan kamera absensi sengaja **belum dibangun** sesuai prinsip pengembangan bertahap (Konstitusi Proyek Bagian X: *Sederhana Dulu, Kompleks Belakangan*).

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
| **Guru** | `guru@knbi.sch.id` | `password` | Lencana hijau **Guru**, menu **Rekap Harian** untuk memantau kelas X TKJ 1 |
| **Siswa** | `siswa@knbi.sch.id` | `password` | Lencana kuning **Siswa**, menu **Riwayat Absensi** (akun siswa ini tertaut ke Raka Pratama) |

Kartu RFID siswa demo menggunakan UID berpola `CARD-TKJ-001` s.d. `CARD-TKJ-010` (siswa nomor urut 1–10). Saat seed, 8 siswa pertama sudah ada catatan absensinya; siswa ke-9 (`CARD-TKJ-009`, Hendra Wijaya) dan ke-10 (`CARD-TKJ-010`, Intan Permata) sengaja dibiarkan **Belum Absen** agar demo tap terasa "langsung".

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
2. Klik salah satu menu: **Data Siswa** atau **Jadwal Kelas**.
3. **Hasil**: Tidak terjadi error teknis/halaman rusak; muncul pemberitahuan ramah bahwa fitur tersebut segera hadir pada tahap berikutnya.
4. Menu **Kiosk Absen** kini aktif — klik untuk membuka layar Kiosk kelas X TKJ 1 di tab baru (lihat Skenario 11). Menu **Rekap Harian** (akun guru) dan **Riwayat Absensi** (akun siswa) juga sudah aktif (Skenario 12–13).

---

## 🎯 Panduan Demo Milestone DEMO (Kiosk, Rekap Guru, Riwayat Siswa)

Bagian ini adalah cara menguji fitur baru — cukup dengan 2 tab browser. Ikuti berurutan.

### Skenario 11: Simulasi Tap Kartu di Kiosk
1. Pastikan server berjalan (`php artisan serve`) dan data demo sudah di-seed.
2. Buka tab baru di browser, ketik alamat: `http://localhost:8000/kiosk/X-TKJ-1`.
3. **Hasil**: Tampil layar penuh Kiosk kelas **X TKJ 1** dengan kotak input yang langsung aktif (kursor berkedip).
4. Ketik UID kartu: `CARD-TKJ-009` lalu tekan **Enter**.
5. **Hasil**: Muncul kartu pop-up besar berisi nama **Hendra Wijaya**, jam menunjukkan **waktu komputer server saat itu**, dan status **Hadir** (jika jam WIB sekarang ≤ 07.00) atau **Terlambat** (jika lewat dari 07.00).
6. Ketik lagi UID yang sama: `CARD-TKJ-009` lalu **Enter**.
7. **Hasil**: Pop-up berubah menjadi **Absen Pulang Berhasil** (tap kedua di hari yang sama otomatis dicatat sebagai jam pulang).
8. Ketik sekali lagi UID yang sama lalu **Enter**.
9. **Hasil**: Muncul pesan bahwa siswa sudah menyelesaikan absen masuk dan pulang hari ini.
10. Ketik UID yang tidak dikenal: `CARD-SALAH-XXX` lalu **Enter**.
11. **Hasil**: Muncul pesan *"Kartu tidak dikenali..."* — tidak ada data yang tercatat.

### Skenario 12: Anti Tap Ganda (Cooldown 60 Detik)
1. Reset data demo terlebih dahulu agar siswa ke-9 kembali "Belum Absen":
   ```powershell
   php artisan migrate:fresh --seed
   ```
2. Buka `http://localhost:8000/kiosk/X-TKJ-1`, tap `CARD-TKJ-010` lalu **Enter** (absen masuk tercatat).
3. Dalam waktu kurang dari 1 menit, tap lagi `CARD-TKJ-010` lalu **Enter**.
4. **Hasil**: Muncul pesan *"Kartu baru saja dicatat. Silakan tunggu sebentar."* — tap kedua **tidak** dihitung sebagai absen pulang. Tunggu 1 menit barulah tap berikutnya tercatat sebagai pulang.

### Skenario 13: Kiosk Terikat Kelas (Kartu Kelas Lain Ditolak)
1. Di layar Kiosk `X-TKJ-1`, tidak ada kartu kelas lain pada data demo — untuk mengetes ini, diperlukan data siswa kelas lain (fitur kelola data siswa hadir di tahap berikutnya). Kondisi terpasang di kode: kartu milik siswa kelas lain akan ditolak dengan pesan bahwa kiosk ini khusus kelas tertentu.

### Skenario 14: Rekap Harian Guru Terbarui Otomatis (Live)
1. Buka browser, masuk sebagai guru (`guru@knbi.sch.id` / `password`).
2. Klik menu **Rekap Harian** (di bar navigasi atas) atau kartu **Rekap Harian** di dashboard.
3. **Hasil**: Tabel 10 siswa X TKJ 1 dengan 4 kartu ringkasan (Total, Hadir, Terlambat, Belum Absen). Ada indikator *"Auto-refresh aktif (tiap 4 detik)"*.
4. Perhatikan siswa ke-9 dan ke-10 berstatus **Belum Absen**.
5. Buka tab baru: `http://localhost:8000/kiosk/X-TKJ-1` dan tap `CARD-TKJ-009` lalu **Enter**.
6. Kembali ke tab guru **tanpa menekan tombol refresh** dan tunggu maksimal 4 detik.
7. **Hasil**: Baris Hendra Wijaya berubah sendiri menjadi **Hadir** atau **Terlambat** lengkap dengan jam masuk, dan kartu ringkasan **Belum Absen** berkurang satu.
8. **Jam yang tampil selalu jam server** — jika jam komputer Anda diubah, status kehadiran tidak ikut berubah (anti-kecurangan).

### Skenario 15: Riwayat Absensi Milik Siswa Sendiri
1. Buka jendela Incognito/Private, masuk sebagai siswa (`siswa@knbi.sch.id` / `password`).
2. Klik menu **Riwayat Absensi** atau kartu **Riwayat Absensi** di dashboard.
3. **Hasil**: Tampil profil **Raka Pratama** (NIS 10240101) beserta tabel catatan absensi hari ini: tanggal, jam masuk, jam pulang, dan status.
4. **Hasil penting**: Nama siswa lain (misalnya Adinda Rahma atau Hendra Wijaya) **tidak muncul** — siswa hanya bisa melihat riwayatnya sendiri.

### Skenario 16: Uji Otomatis (Opsional, untuk yang ingin belajar)
Aplikasi dilengkapi 11 pengujian otomatis yang meniru skenario di atas. Jalankan di terminal:
```powershell
php artisan test tests/Feature/KioskAttendanceTest.php
```
**Hasil**: Semua tes berwarna hijau dengan ringkasan `OK (11 tests, ...)` — artinya seluruh aturan (jam server, cooldown, kelas terikat, privasi) terjaga.

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
- `specs/001-fondasi-login-dashboard/` — Spec Milestone 0 (login & dashboard)
- `specs/002-demo-absensi-kiosk/spec.md` — Spesifikasi Milestone DEMO (kiosk & monitoring)
- `specs/002-demo-absensi-kiosk/plan.md` — Rencana implementasi teknis
- `specs/002-demo-absensi-kiosk/data-model.md` — Desain tabel (kelas, siswa, kartu RFID, kehadiran)
- `specs/002-demo-absensi-kiosk/quickstart.md` — Skenario pengujian singkat
- `specs/002-demo-absensi-kiosk/tasks.md` — Daftar rincian tugas pengerjaan
- `.specify/memory/constitution.md` — Konstitusi tata kelola proyek (Prinsip Non-Negotiable)
