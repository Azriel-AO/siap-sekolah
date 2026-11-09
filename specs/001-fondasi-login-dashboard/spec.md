# Feature Specification: Fondasi Login & Dashboard (Milestone 0)

**Feature Branch**: `001-fondasi-login-dashboard`

**Created**: 2026-11-09

**Status**: Draft

**PRD References**: US-01

---

## Ringkasan

Milestone 0 membangun fondasi aplikasi SIAP KNBI: sistem login berbasis email dan password
untuk tiga peran (admin, guru, siswa), serta dashboard pasca-login yang menampilkan identitas
pengguna dan menu navigasi. Belum ada fitur absensi, kartu, kamera, atau QR — cukup pintu masuk
yang aman dan tampilan awal yang dapat didemokan.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Login Berhasil Sesuai Peran (Priority: P1)

Pengguna (admin, guru, atau siswa) membuka aplikasi, memasukkan email dan password yang benar,
lalu diarahkan ke dashboard yang sesuai dengan perannya.

**Why this priority**: Login adalah pintu masuk ke seluruh sistem. Tanpa ini, tidak ada fitur
lain yang bisa diakses atau didemonstrasikan.

**Independent Test**: Dapat diuji sepenuhnya dengan membuka browser, mengisi form login dengan
data seed yang tersedia di README, dan memverifikasi tampilan dashboard yang muncul.

**Acceptance Scenarios**:

1. **Given** pengguna belum login dan membuka halaman utama,
   **When** pengguna memasukkan email dan password yang benar (dari data seed),
   **Then** pengguna diarahkan ke halaman dashboard yang sesuai dengan perannya,
   dan nama pengguna serta labelnya (Admin / Guru / Siswa) tampil di header.

2. **Given** pengguna sudah login sebagai admin,
   **When** pengguna membuka halaman `/dashboard`,
   **Then** tampil header "SIAP — SMK Karya Nugraha", nama admin, label "Admin",
   dan menu navigasi (Data Siswa, Jadwal Kelas, Kiosk Absen, Laporan).

3. **Given** pengguna sudah login sebagai guru,
   **When** pengguna membuka halaman `/dashboard`,
   **Then** tampil header "SIAP — SMK Karya Nugraha", nama guru, label "Guru",
   dan menu navigasi yang sama (placeholder, belum berfungsi).

4. **Given** pengguna sudah login sebagai siswa,
   **When** pengguna membuka halaman `/dashboard`,
   **Then** tampil header "SIAP — SMK Karya Nugraha", nama siswa, label "Siswa",
   dan menu navigasi yang sama (placeholder, belum berfungsi).

---

### User Story 2 — Login Gagal dengan Pesan Jelas (Priority: P2)

Pengguna memasukkan kredensial yang salah dan mendapat pesan kesalahan yang informatif —
bukan pesan teknis seperti "Error 401".

**Why this priority**: Pesan error yang jelas mencegah pengguna kebingungan dan mengurangi
beban dukungan teknis. Penting untuk pengalaman pengguna awal yang baik.

**Independent Test**: Dapat diuji dengan mengisi email/password yang salah dan memverifikasi
pesan yang muncul.

**Acceptance Scenarios**:

1. **Given** pengguna berada di halaman login,
   **When** pengguna memasukkan email yang terdaftar tapi password salah,
   **Then** muncul pesan "Email atau kata sandi salah. Silakan coba lagi."
   dan pengguna tetap di halaman login (tidak diarahkan ke mana pun).

2. **Given** pengguna berada di halaman login,
   **When** pengguna mengosongkan salah satu atau kedua kolom lalu menekan tombol masuk,
   **Then** muncul pesan validasi di bawah kolom yang kosong:
   "Kolom ini wajib diisi." dan form tidak dikirim.

3. **Given** pengguna memasukkan format email yang tidak valid (misal: "bukan-email"),
   **When** pengguna menekan tombol masuk,
   **Then** muncul pesan "Format email tidak valid." di bawah kolom email.

---

### User Story 3 — Logout Berfungsi (Priority: P2)

Pengguna yang sudah login dapat keluar dari sesi dan diarahkan kembali ke halaman login.
Setelah logout, mengakses dashboard secara langsung akan dialihkan ke login.

**Why this priority**: Logout yang berfungsi adalah persyaratan keamanan dasar — tanpa ini,
sesi tidak bisa diakhiri dan perangkat bersama (misalnya kiosk) menjadi risiko.

**Independent Test**: Dapat diuji dengan menekan tombol "Keluar" lalu mencoba mengakses
kembali URL dashboard.

**Acceptance Scenarios**:

1. **Given** pengguna sudah login dan berada di dashboard,
   **When** pengguna menekan tombol "Keluar",
   **Then** sesi pengguna dihapus dan pengguna diarahkan ke halaman login.

2. **Given** pengguna sudah logout,
   **When** pengguna mencoba membuka halaman `/dashboard` secara langsung (via URL),
   **Then** pengguna dialihkan otomatis ke halaman login.

3. **Given** pengguna belum login,
   **When** pengguna mencoba membuka halaman `/dashboard` secara langsung,
   **Then** pengguna dialihkan otomatis ke halaman login.

---

### Edge Cases

- Apa yang terjadi jika pengguna menekan tombol kembali di browser setelah logout?
  → Pengguna tetap di halaman login; sesi tidak dipulihkan.
- Apa yang terjadi jika pengguna membuka dua tab browser dengan akun berbeda?
  → Masing-masing tab menyimpan sesinya sendiri (perilaku bawaan browser).
- Apa yang terjadi jika server mati saat proses login?
  → Pengguna melihat pesan "Terjadi kesalahan. Silakan coba lagi nanti." dan tetap di
  halaman login.
- Apa yang terjadi jika menu navigasi (placeholder) di-klik?
  → Tidak terjadi apa-apa atau muncul pesan "Fitur ini belum tersedia." — tidak boleh
  menampilkan error teknis.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem WAJIB menyediakan halaman login yang dapat diakses publik (tanpa autentikasi).
- **FR-002**: Halaman login WAJIB memiliki kolom email, kolom kata sandi, dan tombol "Masuk".
- **FR-003**: Sistem WAJIB memvalidasi bahwa kedua kolom tidak kosong sebelum mengirim ke server.
- **FR-004**: Sistem WAJIB memvalidasi format email yang dimasukkan (harus berformat x@y.z).
- **FR-005**: Sistem WAJIB menolak kombinasi email/password yang salah dengan pesan ramah pengguna.
- **FR-006**: Sistem WAJIB mengarahkan pengguna ke halaman dashboard setelah login berhasil.
- **FR-007**: Dashboard WAJIB menampilkan nama aplikasi "SIAP — SMK Karya Nugraha" di header.
- **FR-008**: Dashboard WAJIB menampilkan nama lengkap pengguna yang sedang login di header.
- **FR-009**: Dashboard WAJIB menampilkan label peran pengguna: "Admin", "Guru", atau "Siswa".
- **FR-010**: Dashboard WAJIB menampilkan empat item menu navigasi sebagai placeholder:
  "Data Siswa", "Jadwal Kelas", "Kiosk Absen", dan "Laporan".
- **FR-011**: Menu navigasi placeholder TIDAK BOLEH memunculkan error teknis saat diklik;
  boleh tidak melakukan apa-apa atau menampilkan pesan "Segera hadir".
- **FR-012**: Dashboard WAJIB menampilkan tombol "Keluar".
- **FR-013**: Tombol "Keluar" WAJIB mengakhiri sesi pengguna dan mengarahkan ke halaman login.
- **FR-014**: Semua halaman selain login WAJIB dilindungi: pengguna yang belum login
  yang mencoba mengaksesnya HARUS dialihkan ke halaman login.
- **FR-015**: Sistem WAJIB menyediakan data akun contoh (seed) minimal 1 admin, 1 guru,
  1 siswa dengan kredensial yang terdokumentasi di README.
- **FR-016**: README WAJIB memuat langkah-langkah menjalankan aplikasi dari nol,
  ditulis untuk pembaca yang belum pernah coding.
- **FR-017**: Kata sandi akun seed HARUS disimpan dalam bentuk terenkripsi (hashed)
  di database — tidak boleh disimpan sebagai teks biasa.
- **FR-018**: Semua teks label, pesan, dan navigasi di UI WAJIB dalam Bahasa Indonesia.

### Key Entities

- **Pengguna (User)**: Memiliki atribut nama lengkap, email (unik), kata sandi (terenkripsi),
  dan peran (admin / guru / siswa). Ini adalah entitas pusat untuk autentikasi dan otorisasi.
- **Sesi (Session)**: Mewakili status login aktif pengguna. Berumur terbatas; dihapus saat
  logout. Terikat pada satu pengguna.
- **Peran (Role)**: Mendefinisikan hak akses pengguna. Tiga nilai: admin, guru, siswa.
  Peran menentukan tampilan dan akses ke fitur di tahap berikutnya.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Pengguna dapat menyelesaikan proses login (dari halaman login hingga dashboard)
  dalam waktu di bawah 5 detik pada koneksi internet biasa (seperti WiFi sekolah).
- **SC-002**: Semua tiga akun seed (admin, guru, siswa) dapat login dan logout tanpa error
  pada 100% percobaan.
- **SC-003**: Pengguna yang salah memasukkan kredensial melihat pesan yang dapat dipahami
  tanpa membaca panduan — diuji dengan pengguna awam (guru atau staf TU).
- **SC-004**: Pengguna yang belum login tidak dapat mengakses halaman dashboard dengan cara
  apapun (langsung via URL, tombol back, dll.) — 0 kasus bypass.
- **SC-005**: README dapat diikuti oleh orang yang belum pernah coding untuk menjalankan
  aplikasi dari nol tanpa perlu bantuan tambahan — diuji dengan minimal 1 orang non-developer.
- **SC-006**: Halaman login dan dashboard tampil dengan benar di browser Chrome versi terbaru
  pada layar laptop (1366×768 ke atas) maupun tablet (768px ke atas).

---

## Assumptions

- Pengguna mengakses aplikasi menggunakan browser modern (Chrome, Firefox, Edge terbaru).
- Perangkat pengguna terkoneksi ke internet atau jaringan lokal sekolah.
- Aplikasi dijalankan di lingkungan development lokal (localhost) selama Milestone 0;
  deployment ke server produksi adalah tahap berikutnya.
- Data seed dibuat secara manual menggunakan skrip; belum ada antarmuka pendaftaran pengguna
  baru (fitur manajemen akun adalah tahap berikutnya).
- Sesi login menggunakan mekanisme cookie/token yang dikelola oleh framework;
  tidak perlu membangun sistem sesi dari nol.
- Menu navigasi (Data Siswa, Jadwal Kelas, Kiosk Absen, Laporan) adalah placeholder yang
  belum mengarah ke halaman manapun — ini adalah batasan Milestone 0 yang disengaja.
- Tidak ada fitur "lupa kata sandi" di Milestone 0; pengguna yang lupa password meminta
  bantuan admin secara langsung.
- Konstitusi proyek (`.specify/memory/constitution.md`) berlaku: stack Laravel 13 + Blade
  + Tailwind CSS + SQLite, autentikasi via Laravel Breeze, semua teks UI Bahasa Indonesia,
  waktu dari server.
- Komputer yang menjalankan aplikasi memerlukan PHP 8.3+, Composer, dan Node.js (untuk
  kompilasi Tailwind via Vite) — semua gratis dan tersedia untuk Windows/macOS/Linux.
- README wajib memuat cara instalasi PHP, Composer, dan Node.js bagi pembaca yang belum
  pernah menginstalnya.
