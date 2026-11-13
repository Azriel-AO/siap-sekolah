# E-PRESKNBI — Sistem Absensi Gerbang SMK Karya Nugraha Boyolali

Aplikasi absensi pelajar terpusat (**Kiosk Gerbang Tunggal** + **Panel Admin Web**) berbasis kartu **RFID** dan **Biometrik** (Sidik Jari ZKTeco ZK9500 & Kamera Wajah Liveness) untuk **SMK Karya Nugraha Boyolali**.

---

## ✨ Fitur Utama

1. **Kiosk Absen Gerbang Otomatis (`/kiosk`)**
   - Satu titik absen gerbang untuk seluruh kelas (Kelas 10, 11, dan 12).
   - Mendukung pembacaan **Kartu Pelajar RFID** (USB Keyboard Wedge) dan **Pemindai Sidik Jari ZKTeco ZK9500** secara otomatis tanpa menekan tombol.
   - Pengambilan foto bukti kehadiran otomatis dari kamera saat tap masuk maupun pulang.
   - Penentuan status **Hadir** / **Terlambat** berdasarkan jam server (`Asia/Jakarta`) dan perlindungan anti-tap-ganda (cooldown 60 detik).

2. **Terminal Pendaftaran Siswa Mandiri (`/pendaftaran`)**
   - Pendaftaran mandiri satu alur: pengisian data diri & NISN, pengambilan **3 foto wajah otomatis dengan deteksi Liveness** (hadap lurus, tengok kanan, tengok kiri, dan kedip mata menggunakan MediaPipe lokal), perekaman sidik jari ZK9500, serta penautan kartu RFID.
   - Dilengkapi panduan kotak posisi wajah di tengah layar serta opsi ambil foto manual bila diperlukan.

3. **Rekapitulasi Kehadiran & Pencatatan Izin/Sakit (`/admin/rekapitulasi` & `/admin/kehadiran-manual`)**
   - Grafik dan tabel rekapitulasi kehadiran harian, mingguan, bulanan, dan semester per kelas.
   - Pencatatan manual status **Izin (I)** dan **Sakit (S)** oleh admin sekolah.

4. **Export Daftar Hadir Format Resmi Sekolah (`/admin/daftar-hadir`)**
   - Pratinjau matriks kehadiran bulanan (tanggal 1 s.d. 31) lengkap dengan kalkulasi hari efektif, persentase kehadiran, dan keterangan `√` (Hadir), `I` (Izin), `S` (Sakit), serta `A` (Alpa).
   - Unduh file Excel (`.xlsx`) berformat resmi wali kelas SMK Karya Nugraha Boyolali (kop tengah, tabel bergaris kotak, simbol berwarna, dan kolom NISN/INDUK berformat teks).

5. **Pemantauan Kesehatan Sistem (`/admin/status-sistem`)**
   - Halaman pemeriksaan status Server Web, Database SQLite, dan koneksi sensor sidik jari ZKTeco ZK9500 secara langsung dari browser.

---

## 💻 Kebutuhan Sistem

| Komponen | Spesifikasi |
|---|---|
| **Sistem Operasi** | Windows 10 / Windows 11 (64-bit) |
| **PHP** | PHP 8.3 ke atas (dengan ekstensi `pdo_sqlite`, `sqlite3`, `zip`, `gd`, `mbstring`, `openssl`) |
| **Composer** | Composer 2.x |
| **Node.js & npm** | Node.js 18+ & npm |
| **Sensor Sidik Jari** | ZKTeco ZK9500 USB Fingerprint Scanner + Driver ZKFinger SDK Windows |
| **Pembaca Kartu RFID** | USB RFID Reader 125kHz / 13.56MHz (mode Keyboard Wedge / HID) |
| **Kamera** | Webcam USB / kamera internal terhubung ke Box PC |
| **Browser** | Google Chrome atau Microsoft Edge terbaru |

---

## 🚀 Instalasi (Step-by-Step)

### 1. Clone Repository & Pasang Paket
Buka PowerShell di komputer Box PC, lalu jalankan:
```powershell
git clone https://github.com/Azriel-AO/siap-sekolah.git
cd siap-sekolah
composer install
npm install
```

### 2. Konfigurasi File Lingkungan (`.env`)
Salin file `.env.example` menjadi `.env` dan buat kunci enkripsi aplikasi:
```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Buka file `.env` menggunakan Notepad dan pastikan pengaturan produksi berikut:
```env
APP_NAME="E-PRESKNBI"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite

FINGERPRINT_DRIVER=bridge
FINGERPRINT_BASE_URL=http://127.0.0.1:8787
FINGERPRINT_TIMEOUT=15
FINGERPRINT_MATCH_THRESHOLD=40
```

### 3. Migrasi Database & Pembuatan Akun Admin
Pastikan file `database\database.sqlite` tersedia (buat file kosong jika belum ada), lalu jalankan migrasi dan seeder:
```powershell
if (!(Test-Path database\database.sqlite)) { New-Item -ItemType File database\database.sqlite }
php artisan migrate --force
php artisan db:seed --class=UserSeeder --force
```
> **Catatan**: Perintah `UserSeeder` di atas bersifat idempoten dan membuat akun Administrator default (`admin@knbi.sch.id`). Jangan pernah menjalankan perintah destruktif yang menghapus tabel pada komputer produksi yang sudah berisi data presensi siswa.

### 4. Build Aset Antarmuka (Frontend)
Kompilasi aset CSS dan JavaScript produksi menggunakan Vite:
```powershell
npm run build
```

### 5. Build Bridge Sidik Jari (`fingerbridge.exe`)
Layanan jembatan sensor ZKTeco ZK9500 dikompilasi langsung dari kode sumber `bridge\FingerBridge.cs` menggunakan compiler C# bawaan Windows (.NET Framework) dan pustaka resmi ZKFinger SDK:

1. Pastikan driver **ZKFinger SDK** sudah terinstal di Windows sehingga `libzkfp.dll` dan `libzkfpcsharp.dll` tersedia di `C:\Windows\SysWOW64\`.
2. Salin kedua file DLL tersebut ke dalam folder `bridge\`:
   ```powershell
   Copy-Item C:\Windows\SysWOW64\libzkfp.dll bridge\
   Copy-Item C:\Windows\SysWOW64\libzkfpcsharp.dll bridge\
   ```
3. Kompilasi `bridge\FingerBridge.cs` menjadi `bridge\fingerbridge.exe` (target 32-bit `x86` sesuai arsitektur `libzkfp.dll`):
   ```powershell
   C:\Windows\Microsoft.NET\Framework\v4.0.30319\csc.exe /platform:x86 /target:exe /out:bridge\fingerbridge.exe /reference:bridge\libzkfpcsharp.dll /reference:System.Web.Extensions.dll bridge\FingerBridge.cs
   ```
4. Uji jalankan `bridge\fingerbridge.exe` dan pastikan muncul status `Sensor: TERHUBUNG`, atau buka `http://127.0.0.1:8787/estado` di browser hingga menampilkan `{"conectado":true,"modelo":"ZK9500"}`.

### 6. Bersihkan Cache & Jalankan Server
```powershell
php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8000
```

---

## 🖥️ Autostart Box PC (Menyala Otomatis Saat Reboot)

Agar seluruh layanan menyala otomatis tanpa perlu membuka PowerShell setiap kali Box PC dinyalakan ulang oleh petugas sekolah:

1. Buka Windows File Explorer ke folder `box\` di dalam proyek.
2. Klik dua kali (**double-click**) file:
   👉 **`box\pasang-autostart.bat`**
3. Skrip tersebut memasang dua shortcut otomatis ke folder Startup Windows (`shell:startup`):
   - **`E-PRESKNBI-Mulai-Sistem.lnk`** (`box\mulai-sistem.bat`): menyalakan server web Laravel (`port 8000`), layanan sidik jari `fingerbridge.exe` (`port 8787`) secara tersembunyi di latar belakang, serta membuka layar penuh Google Chrome Kiosk ke `http://localhost:8000/kiosk`.
   - **`E-PRESKNBI-Watchdog.lnk`** (`box\watchdog.bat`): memantau setiap ±20 detik dan menghidupkan kembali server web atau `fingerbridge.exe` secara otomatis apabila berhenti.
4. Untuk menyalakan ulang layanan secara manual kapan saja tanpa restart PC, cukup klik dua kali **`box\mulai-sistem.bat`**.

---

## 🌐 Panduan Akses & Kredensial Default

| Layanan | Alamat URL | Keterangan |
|---|---|---|
| **Layar Kiosk Gerbang** | `http://localhost:8000/kiosk` | Dibuka langsung pada monitor Box PC gerbang |
| **Terminal Pendaftaran Siswa** | `http://localhost:8000/pendaftaran` | Dibuka pada Box PC (wajib `localhost` agar kamera & sensor aktif) |
| **Login Panel Admin** | `http://<ip-box>:8000/login` atau `http://localhost:8000/login` | Dapat diakses dari Box PC maupun komputer TU/Admin di jaringan lokal |
| **Cek Kesehatan Sistem** | `http://<ip-box>:8000/admin/status-sistem` | Halaman indikator status Server, Database, dan Sensor ZK9500 |

### Kredensial Login Admin Default
- **Email**: `admin@knbi.sch.id`
- **Kata Sandi**: `password`

> **Penting**: Segera ganti kata sandi admin setelah instalasi produksi melalui terminal:
> ```powershell
> php artisan tinker --execute="`$u = App\Models\User::where('email','admin@knbi.sch.id')->first(); `$u->password = bcrypt('KataSandiBaruAnda'); `$u->save(); echo 'OK';"
> ```

---

## 🛠️ Troubleshooting (Pemecahan Masalah)

1. **Kamera tidak menyala di halaman `/pendaftaran` atau `/kiosk`?**
   - Browser modern hanya mengizinkan akses kamera (`getUserMedia`) pada koneksi `http://localhost` (atau `127.0.0.1`) dan `https://`.
   - Pastikan halaman `/pendaftaran` dan `/kiosk` dibuka melalui **`http://localhost:8000`**, bukan melalui alamat IP (`http://192.168.x.x:8000`), serta pastikan izin kamera di bilah alamat Chrome berstatus **Allow / Izinkan**.

2. **Sensor sidik jari tidak terhubung / status Bridge merah di `/admin/status-sistem`?**
   - Pastikan kabel USB sensor **ZKTeco ZK9500** tercolok dengan baik ke Box PC.
   - Pastikan file `bridge\fingerbridge.exe`, `bridge\libzkfp.dll`, dan `bridge\libzkfpcsharp.dll` berada di dalam folder `bridge\`.
   - Klik dua kali `box\mulai-sistem.bat` atau `bridge\start-fingerbridge.vbs`, lalu cek `http://127.0.0.1:8787/estado` dan file log `bridge\fingerbridge.log`.

3. **Error `Class "ZipArchive" not found` saat unduh Excel Daftar Hadir?**
   - Buka file konfigurasi `php.ini` pada instalasi PHP 8.3 Anda.
   - Cari baris `;extension=zip`, hapus tanda titik koma (`;`) di depannya menjadi `extension=zip`, simpan file, lalu nyalakan ulang server PHP.

4. **Tampilan halaman tidak rapi / CSS tidak muncul setelah update?**
   - Jalankan `npm run build` dan `php artisan view:clear` untuk memperbarui bundel aset tampilan.
