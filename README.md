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
Aplikasi dilengkapi pengujian otomatis yang meniru skenario di atas. Jalankan di terminal:
```powershell
php artisan test tests/Feature/KioskAttendanceTest.php
```
**Hasil**: Semua tes berwarna hijau dengan ringkasan `OK (14 tests, ...)` — artinya seluruh aturan (jam server, cooldown, kelas terikat, privasi, foto bukti) terjaga.

---

## 📷 Panduan Uji Kamera Bukti Absen (Milestone Kamera)

Kiosk kini punya **kamera bukti absen**: saat siswa tap kartu, kiosk mengambil 1 foto dari kamera sebagai bukti (aturan **F1** di PRD). Foto tersimpan di folder privat server — **hanya guru/admin** yang bisa melihatnya di rekap harian. Fitur ini **bukan pengenalan wajah** — kamera hanya merekam bukti, sistem tidak mengenali siapa pun.

### Skenario 17: Kamera Aktif + Foto Tersimpan di Rekap Guru
1. Siapkan komputer/laptop yang punya **webcam** (atau colok webcam USB).
2. Pastikan server berjalan (`php artisan serve`) lalu reset data demo:
   ```powershell
   php artisan migrate:fresh --seed
   ```
3. **Tab 1 (Kiosk)**: buka `http://localhost:8000/kiosk/X-TKJ-1`.
4. Browser akan menampilkan jendela kecil bertuliskan *"... ingin menggunakan kamera Anda"*. Klik tombol **Izinkan / Allow**.
   - **Google Chrome / Edge**: klik **Izinkan** di jendela pop-up.
   - **Mozilla Firefox**: klik **Izinkan**, lalu centang *"Ingat keputusan ini"* jika ingin permanen.
5. **Hasil di pojok kanan bawah**: muncul kotak kecil **pratinjau kamera** (anda terlihat di dalamnya, dengan titik merah berkedip).
6. Ketik `CARD-TKJ-009` lalu **Enter**.
7. **Hasil**: tap tercatat seperti biasa, dan di belakang layar 1 foto dari kamera tersimpan sebagai bukti.
8. **Tab 2 (Guru)**: buka jendela Incognito, masuk sebagai `guru@knbi.sch.id` / `password`, klik menu **Rekap Harian**.
9. **Hasil**: di baris **Hendra Wijaya**, kolom foto kini berisi **thumbnail foto** yang tadi diambil kiosk. Klik thumbnail untuk **memperbesar**, klik lagi (atau tombol ✕) untuk menutup.
10. Tap lagi di kiosk (`CARD-TKJ-009` setelah 1 menit, atau kartu lain), lalu lihat rekap guru: foto terbaru muncul di kolom foto yang sesuai (masuk/pulang).

### Skenario 18: Kamera Tidak Ada / Izin Ditolak — Kiosk Tetap Normal
1. Buka kiosk di browser tanpa webcam (misalnya komputer PC biasa), atau saat jendela izin muncul klik **Tolak / Block**.
2. **Hasil**: kotak pratinjau kamera **tidak muncul** (disembunyikan rapi), **tidak ada pesan error**, dan seluruh alur tap kartu berjalan normal seperti biasa.
3. Tap `CARD-TKJ-010` lalu **Enter** — absen tetap tercatat. Di rekap guru, kolom foto baris Intan tampil **kosong** dengan ikon tanda "-" — itu wajar karena tidak ada kamera.

### Skenario 19: Foto Tidak Boleh Dilihat Siswa / Publik
1. Masuk sebagai siswa (`siswa@knbi.sch.id` / `password`), lalu ketik alamat `http://localhost:8000/guru/rekap-harian` langsung di bilah alamat.
2. **Hasil**: muncul halaman **403 — Akses Ditolak** ("Halaman ini hanya untuk guru dan admin").
3. Buka `http://localhost:8000/guru/rekap-harian` **tanpa login** (jendela Incognito).
4. **Hasil**: dialihkan ke halaman `/login`. Foto bukti absen tidak pernah bisa diakses tanpa akun guru/admin.

> 💡 **Catatan privasi**: foto bukti absen disimpan di folder privat server (`storage/app/private/attendance-photos/`) dan tidak pernah diumumkan ke internet. Hanya halaman rekap yang wajib login sebagai guru/admin yang menampilkannya (Konstitusi VIII).

---

## 🙂 Panduan Uji Pendaftaran Wajah (Milestone Face Enrollment)

Menu baru **"Pendaftaran Wajah"** (khusus siswa): siswa login, mengizinkan kamera, lalu mengambil **3 foto wajah** sebagai data referensi absensi. **Ini BUKAN pengenalan wajah** — sistem tidak mengenali atau mencocokkan siapa pun, hanya menyimpan foto referensi. Foto tersimpan di folder privat server (`storage/app/private/face-photos/`), tidak bisa diakses dari URL publik, dan hanya tampil di halaman status milik siswa itu sendiri serta rekap guru/admin.

### Skenario 20: Siswa Mendaftar Wajah Pertama Kali
1. Pastikan server berjalan (`php artisan serve`) dan data demo sudah di-seed.
2. Login sebagai siswa: `siswa@knbi.sch.id` / `password`.
3. Klik menu **Pendaftaran Wajah** (bar navigasi atas) atau kartu **Pendaftaran Wajah** di dashboard.
4. **Seharusnya**: muncul **pernyataan izin orang tua/wali** dengan kotak centang — kamera belum menyala.
5. Centang kotak pernyataan → browser menanyakan izin kamera → klik **Izinkan**.
6. **Seharusnya**: pratinjau kamera tampil (wajah Anda terlihat).
7. Klik **"Ambil Foto"** → muncul hitungan mundur **3… 2… 1…** → foto pertama terisi di slot 1. Ulangi sampai slot 1, 2, 3 terisi (tiap pengambilan diawali countdown 3 detik).
8. Klik **"Simpan Pendaftaran"**.
9. **Seharusnya**: pesan sukses muncul, halaman berubah jadi status **"Terdaftar"** beserta **tanggal pendaftaran** (waktu server) dan pratinjau 3 foto Anda.

### Skenario 21: Ulang Pengambilan Foto Sebelum Simpan
1. Ulangi Skenario 20 sampai langkah 7 (jangan klik simpan dulu).
2. Klik tombol **"Ulangi"** pada slot foto yang kurang bagus.
3. **Seharusnya**: slot itu kembali kosong; klik **"Ambil Foto"** lagi untuk mengisinya (countdown 3 detik lagi).

### Skenario 22: Simpan Ditolak Bila Foto Belum Lengkap
1. Ambil hanya 1–2 foto, lalu klik **"Simpan Pendaftaran"**.
2. **Seharusnya**: muncul pesan bahwa foto wajib lengkap 3 foto (contoh: *"Foto wajah wajib lengkap 3 foto. Saat ini baru 2 foto sah."*) dan **tidak ada** yang tersimpan.

### Skenario 23: Daftar Ulang (Ganti Foto Lama)
1. Masih sebagai siswa yang sudah terdaftar, buka menu **Pendaftaran Wajah**.
2. **Seharusnya**: status **"Terdaftar"** + tanggal pendaftaran + pratinjau 3 foto lama.
3. Klik **"Daftar Ulang"** → dialog konfirmasi muncul → klik **Batal** dulu.
   - **Seharusnya**: tidak ada yang berubah; foto lama aman.
4. Klik **"Daftar Ulang"** lagi → klik **"Ya, Daftar Ulang"**.
5. **Seharusnya**: kembali ke alur 3 foto (pernyataan persetujuan tampil lagi). Ambil 3 foto baru → **Simpan Pendaftaran**.
6. **Seharusnya**: status tetap "Terdaftar", muncul **tanggal daftar ulang terakhir** terbaru, dan pratinjau kini menampilkan foto baru (foto lama terhapus dari server).

### Skenario 24: Badge "Wajah" di Rekap Guru
1. Buka jendela Incognito, login guru: `guru@knbi.sch.id` / `password` → menu **Rekap Harian**.
2. **Seharusnya**: tabel punya kolom baru **"Wajah"** — siswa yang sudah mendaftar (Raka Pratama) tampil badge hijau **"Terdaftar"**; siswa lain badge abu-abu **"Belum"**.
3. Badge ikut terbarui otomatis bersama polling 4 detik — tanpa perlu reload halaman.

### Skenario 25: Kamera Tidak Ada / Izin Ditolak — Halaman Tetap Normal
1. Saat browser menanyakan izin kamera, klik **Tolak / Block** (atau uji di PC tanpa webcam).
2. **Seharusnya**: pratinjau kamera **tidak muncul**, tampil pesan berbahasa Indonesia berisi cara mengizinkan kamera; **tidak ada** layar error/rusak; sesi login tetap aman; tombol ambil foto nonaktif.
3. Absen kartu RFID di kiosk tetap berjalan seperti biasa.

### Skenario 26: Privasi Foto & Akses Dibatasi
1. Sebagai siswa, buka `http://localhost:8000/siswa/pendaftaran-wajah` → hanya bisa melihat foto **miliknya sendiri**. Ketik nama file fotonya di bilah alamat (mis. `/storage/face-photos/...`) → **seharusnya 404/403** — tidak ada endpoint foto publik.
2. Tanpa login (Incognito), buka `http://localhost:8000/siswa/pendaftaran-wajah` → **seharusnya** dialihkan ke `/login`.
3. Sebagai guru, buka `http://localhost:8000/siswa/pendaftaran-wajah` → **seharusnya 403** "Halaman ini hanya untuk siswa."
4. **Seharusnya** foto tidak pernah tampil ke siswa lain maupun pengunjung.

> 💡 **Catatan privasi**: foto wajah adalah data anak di bawah umur (Konstitusi VIII). Foto tersimpan di folder privat server (`storage/app/private/face-photos/`), hanya disajikan di halaman yang wajib login, dan fitur ini **tidak melakukan pengenalan/pencocokan wajah** (PRD F3 tahap 1).

### Skenario 27: Kiosk Tidak Terpengaruh (Regresi)
1. Buka `http://localhost:8000/kiosk/X-TKJ-1`, ketik `CARD-TKJ-009` lalu Enter.
2. **Seharusnya**: alur tap berjalan persis seperti sebelum fitur ini — status, jam server, cooldown tidak berubah sama sekali.
3. Uji otomatis semua tes:
   ```powershell
   php artisan test tests/Feature/KioskAttendanceTest.php
   php artisan test tests/Feature/FaceEnrollmentTest.php
   ```
   **Hasil**: semua hijau (14 + 11 tes).

---

## 🌐 Deployment untuk Guru (Memasang SIAP di Komputer Sekolah)

Panduan memasang SIAP KNBI di komputer/server sekolah agar bisa dipakai harian — ditulis untuk guru/TU, bukan ahli IT. Langkah dijalankan **sekali** saat pemasangan. Bagian yang belum sempat diuji disebutkan dengan jelas; jangan dianggap pasti.

### 1. Prasyarat komputer
- **PHP 8.3 atau lebih baru** + **Composer** (untuk paket PHP)
- **Node.js LTS** + **npm** (untuk membangun tampilan)
- **SQLite** (sudah bawaan PHP di Windows/XAMPP/Laragon — tidak perlu instal server database)
- Opsional: **MySQL/MariaDB** bila sekolah sudah punya server database (lihat langkah 4)

### 2. Siapkan file .env
Di folder aplikasi, salin contoh konfigurasi:
```powershell
copy .env.example .env
```
(di Linux: `cp .env.example .env`)

Buka `.env` di Notepad, ubah 3 baris ini:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000
```
- `APP_ENV=production` & `APP_DEBUG=false` — mode produksi; pesan error teknis tidak ditampilkan ke pengguna, hanya "Terjadi kesalahan".
- `APP_URL` — sesuaikan: `http://localhost:8000` bila jalan di komputer itu sendiri, atau `http://192.168.1.x:8000` / domain sekolah bila diakses dari komputer lain.
- `APP_TIMEZONE=Asia/Jakarta` sudah terisi bawaan — **jangan diubah** (jam server menentukan status Hadir/Terlambat).

### 3. Buat APP_KEY (kunci enkripsi sesi)
```powershell
php artisan key:generate
```
Perintah ini mengisi `APP_KEY=...` di `.env` secara otomatis. Diverifikasi lewat `php artisan key:generate --show` (perintah isinya memang terdaftar & berjalan; belum dijalankan untuk menimpa `.env` agar konfigurasi pengembangan tidak berubah). Tanpa kunci ini, sesi login tidak aman dan aplikasi menolak jalan.

### 4. Database
**Bawaan (SQLite, paling mudah — sudah diuji):**
- Pastikan file database ada: `database/database.sqlite` (sudah tersedia di proyek ini).
- `.env` cukup berisi `DB_CONNECTION=sqlite` — tidak perlu `DB_HOST`, `DB_DATABASE`, dll.
- **Pindah ke MySQL**: cukup ubah bagian `DB_` di `.env`:
  ```env
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=siap_knbi
  DB_USERNAME=root
  DB_PASSWORD=...
  ```
  Kode aplikasi **tidak perlu diubah** — semua query lewat migrasi Laravel & Eloquent. **Catatan jujur:** perpindahan ke MySQL ini adalah desain yang dijamin oleh arsitektur (migrasi & Eloquent), tetapi **belum pernah dicoba pada instalasi nyata** di proyek ini; SQLite yang teruji. Sebelum hari demo, coba dulu migrasi + seluruh tes di lingkungan MySQL bila sekolah memilih opsi ini.

### 5. Buat tabel-tabel database
```powershell
php artisan migrate --force
```
`--force` berarti "lanjut tanpa ditanya" (wajib di mode production). Diuji pada basis data SQLite baru yang bersih: semua migrasi termasuk `face_enrollments` berjalan sukses.
- **JANGAN PERNAH** menjalankan `php artisan migrate:fresh` di komputer sekolah yang sudah berisi data — semua data siswa & absensi akan terhapus permanen.

### 6. Buat akun admin awal
```powershell
php artisan db:seed --class=UserSeeder --force
```
Diuji pada basis data SQLite baru: perintah ini membuat akun-akun awal: `admin@knbi.sch.id` (admin), `guru@knbi.sch.id` (guru), `siswa@knbi.sch.id` dan `intan@knbi.sch.id` (siswa). **Semua kata sandi awal: `password`.** Seeder ini aman dijalankan berulang kali (idempoten): akun yang sudah ada cukup diperbarui, **tidak** membuat ganda. Untuk sekolah nyata, akun siswa demo tidak perlu dipakai — gunakan menu **Data Siswa** di dashboard admin untuk membuat akun siswa sungguhan.

**Ganti kata sandi admin** (aplikasi belum punya halaman ganti kata sandi): buka PowerShell di folder aplikasi, jalankan contoh berikut sampai `saved` (teruji jalan di PowerShell Windows; tanda `` ` `` sebelum `$` adalah cara PowerShell meloloskan tanda dolar ke PHP):
```powershell
php artisan tinker --execute="`$u = App\Models\User::find(1); `$u->password = bcrypt('rahasia-baru-123'); `$u->save(); echo 'saved';"
```
Ganti `rahasia-baru-123` dengan kata sandi pilihan Anda. `User::find(1)` = akun dengan id 1, yaitu `admin@knbi.sch.id` yang dibuat seeder pada database baru. Untuk mengganti akun selain admin (mis. guru), gunakan pencarian email berikut — juga teruji:
```powershell
php artisan tinker --execute="`$u = App\Models\User::where('email','guru@knbi.sch.id')->first(); `$u->password = bcrypt('rahasia-guru-123'); `$u->save(); echo 'saved';"
```

### 7. Bangun tampilan (sekali, dan setiap ada update aplikasi)
```powershell
npm ci
npm run build
```
`npm ci` memasang paket persis sesuai `package-lock.json` (bila gagal — mis. komputer offline atau npm tidak diizinkan — gunakan `npm install`). `npm run build` **diuji sukses** di instalasi ini (Vite: menghasilkan `public/build/`, sekitar 2 detik). Tanpa langkah ini halaman tampak tanpa gaya/CSS.

### 8. Izin folder (hanya Linux/Mac)
Bila dipasang di Linux, web server harus bisa menulis ke 2 folder:
```bash
chmod -R 775 storage bootstrap/cache
```
(Di Windows tidak perlu langkah ini. **Catatan:** perintah ini standar untuk aplikasi Laravel, tetapi belum diuji langsung di instalasi ini karena lingkungan pengembangan adalah Windows.)

### 9. Dokumen akar web (web root)
Di web server (Apache/Nginx), arahkan **document root ke folder `public/`** aplikasi — bukan ke folder utama proyek — karena `public/index.php` adalah satu-satunya pintu masuk. Contoh Nginx: `root /var/www/siap-sekolah/public;`.

### 10. PENTING — kamera kiosk & HTTPS
Kamera browser hanya boleh menyala pada **konteks aman**, yaitu:
- `https://...` (dengan sertifikat), atau
- `http://localhost` / `http://127.0.0.1` (komputer kiosk itu sendiri).

Artinya: bila kiosk diakses lewat `http://192.168.1.x` (bukan localhost, bukan HTTPS), **pratinjau kamera TIDAK akan menyala**. Solusi: pasang HTTPS (mis. reverse proxy Let's Encrypt, atau sertifikat internal untuk intranet sekolah), atau jalankan kiosk dari komputer yang membuka `http://localhost:8000`. Absensi tap kartu **tetap berfungsi normal tanpa kamera** — kamera hanya untuk foto bukti.

### 11. Uji coba setelah pemasangan
1. Buka `APP_URL` di browser → harus dialihkan ke halaman **/login**.
2. Login `admin@knbi.sch.id` / kata sandi yang sudah Anda ganti → dashboard muncul, menu **Data Siswa** & **Data Kelas** terlihat.
3. Login `guru@knbi.sch.id` → buka **Rekap Harian** → siswa tampil beserta badge "Wajah".
4. Login sebagai salah satu akun siswa → menu **Pendaftaran Wajah** tampil.
5. Isi data master: `admin@knbi.sch.id` → menu **Data Kelas** → buat kelas (mis. "X TKJ 1") → menu **Data Siswa** → tambah siswa satu per satu (nama, NIS, UID kartu, opsi buat akun login). Sebelum ada siswa + kartu, kiosk belum bisa mencatat apa pun.
6. Buka `http://localhost:<port>/kiosk/<slug-kelas>` di komputer kiosk → tap kartu percobaan → tercatat di rekap guru.
7. Backup berkala: salin file `database/database.sqlite` dan folder `storage/app/` ke tempat aman (mis. flashdisk sekolah).

### 12. Troubleshooting pemasangan
- **500 Internal Server Error** → hapus cache konfigurasi: `php artisan config:clear`; pastikan `storage/` bisa ditulis (langkah 8) dan `.env` valid (langkah 2–3).
- **"No application encryption key has been specified"** → kunci belum dibuat, ulangi langkah 3.
- **Halaman tampil tanpa CSS** → langkah 7 belum dijalankan.
- **Kamera kiosk tidak menyala** → lihat langkah 10 (HTTPS/localhost).

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
- `specs/003-face-enrollment/spec.md` — Spesifikasi Face Enrollment (tahap 1 PRD F3/US-10)
- `specs/003-face-enrollment/plan.md` — Rencana implementasi teknis
- `specs/003-face-enrollment/data-model.md` — Desain tabel `face_enrollments`
- `specs/003-face-enrollment/quickstart.md` — Skenario pengujian singkat
- `specs/003-face-enrollment/tasks.md` — Daftar rincian tugas pengerjaan
- `.specify/memory/constitution.md` — Konstitusi tata kelola proyek (Prinsip Non-Negotiable)
