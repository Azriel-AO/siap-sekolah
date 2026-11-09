# Feature Specification: Milestone DEMO — Absensi Kiosk RFID & Monitoring (13 November)

**Feature Branch**: `002-demo-absensi-kiosk`

**Created**: 2026-11-09

**Status**: Draft

**PRD References**: US-14 (Kiosk Absen Masuk & Pulang RFID), US-21/US-22 (Rekap Harian Guru/Wali Kelas), US-31 (Riwayat Absen Mandiri Siswa), Bab 5.4 (Aturan Anti-Loophole R1–R8)

---

## Ringkasan

Milestone DEMO berfokus pada **satu alur lengkap ujung-ke-ujung** yang siap didemokan pada 13 November 2026 untuk SMK Karya Nugraha (KNBI). Alur mencakup:
1. Siswa melakukan simulasi tap kartu RFID pada layar Kiosk kelas (input UID kartu).
2. Server mencatat jam tap secara mutlak dari server (R7), menentukan status (Hadir/Terlambat/Pulang) berdasarkan jadwal kelas, dan mencegah tap ganda (R3).
3. Guru wali kelas melihat papan rekap absensi harian kelasnya yang diperbarui secara otomatis (polling).
4. Siswa dapat login mandiri untuk melihat rekap riwayat jam masuk dan pulangnya sendiri.

Semua data pengujian disiapkan via database seed (1 kelas X TKJ 1, 10 siswa ber-UID unik, jadwal masuk 07.00 & pulang 15.30) sehingga demo langsung hidup tanpa konfigurasi manual.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Tap Absen Masuk & Pulang di Kiosk Kelas (Priority: P1) 🎯 MVP

Sebagai siswa yang membawa kartu pelajar RFID, saya melakukan tap kartu di perangkat kiosk kelas saya untuk mencatat kehadiran masuk di pagi hari atau kehadiran pulang di sore hari secara instan.

**Why this priority**: Inti dari sistem absensi adalah kemampuan mencatat kehadiran di titik kiosk kelas secara cepat dan akurat dengan aturan server.

**Independent Test**: Buka layar Kiosk kelas (`/kiosk/X-TKJ-1`), masukkan UID kartu salah satu siswa yang belum absen (misal: `CARD-TKJ-009`). Verifikasi layar kiosk langsung menampilkan kartu ucapan selamat datang berukuran besar dengan nama siswa, jam server, dan status kehadiran.

**Acceptance Scenarios**:

1. **Given** waktu server sebelum atau tepat pukul 07.00 WIB dan siswa belum absen hari ini,
   **When** UID kartu siswa dikirimkan pada kiosk kelasnya,
   **Then** sistem mencatat kehadiran masuk dengan status **Hadir**, waktu server tersimpan, dan layar kiosk menampilkan nama siswa, waktu masuk, serta status "Hadir" berwarna hijau selama 4 detik sebelum kembali ke mode siap baca.

2. **Given** waktu server setelah pukul 07.00 WIB dan siswa belum absen hari ini,
   **When** UID kartu siswa dikirimkan pada kiosk kelasnya,
   **Then** sistem mencatat kehadiran masuk dengan status **Terlambat**, dan layar kiosk menampilkan nama siswa, waktu masuk, serta status "Terlambat" berwarna kuning/oranye.

3. **Given** siswa sudah memiliki catatan absen masuk pada hari yang sama,
   **When** UID kartu siswa di-tap kembali pada kiosk kelasnya di sore hari (setelah jam masuk berlalu),
   **Then** sistem mencatat waktu kepulangan siswa (**Absen Pulang**) pada catatan hari itu, dan layar kiosk menampilkan ucapan "Selamat Pulang", nama siswa, serta jam pulang tercatat.

4. **Given** kartu RFID tidak terdaftar di database sekolah,
   **When** UID kartu dikirimkan pada kiosk,
   **Then** sistem menolak dan layar menampilkan pesan peringatan: *"Kartu tidak dikenali. Silakan hubungi petugas tata usaha."* tanpa mencatat kehadiran.

---

### User Story 2 — Pemantauan Rekap Kehadiran Harian Guru secara Otomatis (Priority: P2)

Sebagai guru wali kelas X TKJ 1, saya membuka dashboard untuk melihat daftar 10 siswa di kelas saya beserta status kehadiran hari ini (Hadir, Terlambat, Alpa, atau Belum Absen) dan jam tap mereka, yang otomatis terbarui tanpa perlu memuat ulang halaman secara manual.

**Why this priority**: Wali kelas perlu memantau kehadiran siswa secara real-time di jam pertama sekolah untuk mengetahui siapa yang belum hadir dan siapa yang terlambat.

**Independent Test**: Login sebagai guru (`guru@knbi.sch.id`), buka halaman Rekap Absensi Kelas. Buka tab browser lain di halaman Kiosk, lakukan tap untuk siswa yang belum absen. Kembali ke tab guru, dalam hitungan beberapa detik tabel rekap otomatis memperbarui status siswa tersebut menjadi Hadir/Terlambat beserta jam tap-nya.

**Acceptance Scenarios**:

1. **Given** guru wali kelas telah login,
   **When** guru membuka menu rekap absensi hari ini untuk kelas X TKJ 1,
   **Then** sistem menampilkan tabel seluruh 10 siswa lengkap dengan kolom: NIS, Nama Siswa, Jam Masuk, Jam Pulang, dan Status Kehadiran (Hadir / Terlambat / Belum Absen).

2. **Given** halaman rekap guru sedang terbuka di browser,
   **When** terjadi pencatatan tap baru di kiosk kelas,
   **Then** halaman rekap guru secara otomatis memperbarui data tabel dalam waktu maksimal 5 detik (melalui mekanisme polling berkala) tanpa mereload seluruh halaman.

3. **Given** ada siswa yang belum melakukan tap masuk hingga batas waktu tertentu dan tidak ada keterangan izin/sakit,
   **When** rekap ditampilkan,
   **Then** siswa tersebut ditandai dengan status jelas: *"Belum Absen"* (atau *"Alpa"* jika hari telah ditutup).

---

### User Story 3 — Riwayat Kehadiran Mandiri oleh Siswa (Priority: P2)

Sebagai siswa, saya dapat login ke akun SIAP saya untuk melihat riwayat kehadiran saya sendiri, memastikan tap kartu saya di kiosk telah tercatat dengan benar.

**Why this priority**: Menghilangkan kebutuhan siswa bertanya manual ke wali kelas mengenai rekam kehadirannya dan menumbuhkan transparansi.

**Independent Test**: Login sebagai siswa (`siswa@knbi.sch.id`), buka menu riwayat absensi. Pastikan daftar absensi menampilkan tanggal, jam masuk, jam pulang, dan status kehadiran hanya untuk siswa yang bersangkutan.

**Acceptance Scenarios**:

1. **Given** siswa telah login ke dashboard siswa,
   **When** siswa mengakses halaman riwayat absensinya,
   **Then** sistem menampilkan riwayat absensi tanggal hari ini dan hari-hari sebelumnya yang mencakup tanggal, jam masuk, jam pulang, dan status kehadiran.

2. **Given** siswa sedang melihat riwayat absensinya,
   **When** siswa memeriksa data,
   **Then** siswa HANYA dapat melihat data kehadiran miliknya sendiri dan tidak dapat melihat atau mengubah catatan siswa lain.

---

### User Story 4 — Perlindungan Anti-Double Tap & Kiosk Terikat Kelas (Priority: P3)

Sebagai pengelola sistem sekolah, saya ingin sistem menerapkan aturan anti-kecurangan: tap berulang dalam rentang detik yang sama diabaikan, dan kartu yang di-tap di kiosk kelas lain ditolak/dicatat sebagai anomali.

**Why this priority**: Memenuhi prinsip konstitusi R2 (Kiosk terikat kelas) dan R3 (Cooldown tap ganda) untuk menjaga keabsahan data saat demo berlangsung.

**Independent Test**: Lakukan tap kartu siswa dua kali berturut-turut dalam selang waktu 10 detik. Pastikan sistem mengabaikan tap kedua dengan pesan "Kartu baru saja di-tap" dan tidak menduplikasi rekaman absensi.

**Acceptance Scenarios**:

1. **Given** seorang siswa baru saja berhasil melakukan tap absen di kiosk,
   **When** kartu yang sama di-tap kembali dalam rentang waktu kurang dari 60 detik (cooldown anti-double-tap),
   **Then** sistem menolak pencatatan ganda dan menampilkan pesan informasi: *"Kartu Anda baru saja tercatat. Silakan tunggu sebentar."*

2. **Given** kiosk terikat pada kelas X TKJ 1,
   **When** kartu milik siswa dari kelas lain di-tap pada kiosk tersebut,
   **Then** sistem menolak tap absensi dengan pesan: *"Siswa tidak terdaftar di kelas ini (Kiosk khusus X TKJ 1)."* dan mencatat anomali pada log.

---

### Edge Cases

- **Apa yang terjadi jika siswa melakukan tap pulang sebelum jam masuk selesai (misal beberapa menit setelah tap masuk)?**
  Sistem menolak dengan pesan bahwa jam pulang belum tiba, kecuali telah melewati batas interval masuk atau jadwal pulang kelas (PRD R1).
- **Apa yang terjadi jika keyboard barcode/RFID reader mengirim tombol Enter setelah UID?**
  Form kiosk dirancang untuk otomatis menangani pengiriman input saat event submit/Enter tanpa mengharuskan kursor mengklik tombol di layar.
- **Apa yang terjadi jika koneksi internet terputus saat polling rekap guru?**
  Polling rekap guru menangani error koneksi secara senyap (graceful retry) dan menampilkan indikator kecil status sinkronisasi tanpa merusak tampilan tabel yang sudah ada.
- **Apa yang terjadi jika dua siswa melakukan tap bergantian dengan jeda 2 detik?**
  Masing-masing siswa memiliki UID berbeda, sehingga keduanya diproses secara normal tanpa terkena batasan cooldown (cooldown hanya berlaku per UID kartu yang sama).

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem WAJIB menyediakan seed data yang memuat:
  - 1 entitas Kelas: "X TKJ 1" dengan jam masuk 07.00 WIB dan jam pulang 15.30 WIB.
  - 10 entitas Siswa terdaftar pada kelas tersebut, masing-masing memiliki NIS, Nama Indonesia realistis, dan UID kartu unik.
  - Minimal 5 siswa sudah memiliki data absensi hari ini (sebagian Hadir jam 06.45–06.55, sebagian Terlambat jam 07.05–07.15, dan minimal 2 siswa belum absen) agar dashboard terlihat dinamis saat awal dibuka.
- **FR-002**: Sistem WAJIB menyediakan halaman Kiosk khusus kelas yang dapat diakses melalui URL beralamat kelas (contoh: `/kiosk/X-TKJ-1`).
- **FR-003**: Halaman Kiosk WAJIB memiliki input teks UID yang selalu fokus otomatis (autofocus) untuk menerima pembacaan kartu RFID fisik maupun simulasi pengetikan manual.
- **FR-004**: Sistem WAJIB mencatat waktu kehadiran menggunakan **jam server** (bukan jam perangkat browser klien), sesuai Konstitusi Prinsip II dan PRD R7.
- **FR-005**: Sistem WAJIB menentukan status kehadiran berdasarkan aturan jam kelas:
  - Masuk $\le$ 07.00: status `Hadir`
  - Masuk $>$ 07.00: status `Terlambat`
  - Tap kedua di hari yang sama: dicatat sebagai jam pulang siswa
- **FR-006**: Layar Kiosk WAJIB menampilkan umpan balik visual berukuran besar dan jelas (nama siswa, foto/avatar inisial, jam tap server, dan status kehadiran) selama 3–5 detik sebelum otomatis mereset input untuk pengetikan kartu berikutnya.
- **FR-007**: Sistem WAJIB menerapkan aturan anti-double-tap (PRD R3): tap dengan UID yang sama dalam waktu $\le$ 60 detik diabaikan dan memunculkan pemberitahuan bahwa kartu baru saja dibaca.
- **FR-008**: Sistem WAJIB memastikan Kiosk terikat pada satu kelas (PRD R2): tap siswa dari luar kelas tersebut ditolak dengan pesan kesalahan yang jelas.
- **FR-009**: Dashboard guru WAJIB menampilkan rekap kehadiran harian kelas yang memuat daftar seluruh siswa di kelasnya dengan status (Hadir, Terlambat, Belum Absen) serta jam tap masuk dan pulang.
- **FR-010**: Halaman rekap guru WAJIB memiliki mekanisme pembaruan otomatis (polling berkala setiap 3–5 detik) untuk memperbarui data tanpa me-refresh seluruh halaman.
- **FR-011**: Halaman siswa WAJIB menampilkan riwayat catatan kehadiran tanggal hari ini dan hari-hari sebelumnya yang hanya memuat data kehadiran siswa yang bersangkutan.
- **FR-012**: Semua teks pada antarmuka Kiosk, Dashboard Guru, dan Riwayat Siswa WAJIB menggunakan Bahasa Indonesia yang sederhana dan lugas (Konstitusi Prinsip IX & XII).
- **FR-013**: README WAJIB memuat panduan pengujian alur demo langkah-demi-langkah dari membuka Kiosk, simulasi tap UID, hingga melihat perubahan langsung pada dashboard guru dan akun siswa.

### Key Entities

- **Kelas (`classes`)**: Menyimpan data identitas kelas (nama kelas: "X TKJ 1", jurusan: "TKJ", tingkat: "X", jam_masuk: "07:00", jam_pulang: "15:30").
- **Siswa (`students`)**: Menyimpan identitas siswa (nis, nama lengkap, jenis kelamin, id_kelas, id_user jika tertaut akun login).
- **Kartu RFID (`rfid_cards`)**: Menyimpan pasangan kartu dengan siswa (uid_kartu unik, id_siswa, status_aktif: boolean).
- **Kehadiran (`attendances`)**: Menyimpan rekaman absensi per siswa per tanggal (id_siswa, tanggal_absen, jam_masuk, jam_pulang, status_kehadiran: Hadir/Terlambat/Alpa/Pulang Cepat, device_id/kiosk_id, catatan).

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Waktu respon proses tap kartu di Kiosk hingga muncul nama dan status di layar tidak melebihi **1 detik** pada lingkungan lokal.
- **SC-002**: Layar Kiosk siap menerima input kartu berikutnya secara otomatis tanpa perlu pengguna menyentuh mouse atau mengklik layar.
- **SC-003**: Perubahan data kehadiran dari Kiosk muncul di tabel rekap dashboard guru dalam waktu maksimal **5 detik** secara otomatis tanpa tombol muat ulang manual.
- **SC-004**: Siswa yang login hanya dapat melihat catatan absensi miliknya sendiri pada 100% percobaan pengujian.
- **SC-005**: 100% dari 10 skenario data seed siswa kelas X TKJ 1 dapat didemokan tanpa error fatal (halaman 500 / blank page).
- **SC-006**: Panduan pengujian di README dapat dijalankan oleh pihak non-teknis dalam waktu kurang dari 5 menit untuk memperagakan alur tap kiosk hingga rekap guru.

---

## Assumptions

- Simulasi pembaca kartu RFID USB pada laptop pengembang bekerja persis seperti input keyboard standar (mengirimkan deretan teks UID kartu lalu menekan tombol Enter).
- Kelas demo adalah kelas "X TKJ 1" dengan jadwal resmi masuk pukul 07.00 dan pulang pukul 15.30.
- Untuk keperluan pengujian di luar jam sekolah nyata (misal pengujian di siang atau sore hari), sistem menyediakan opsi simulasi waktu atau jam uji yang mudah dipahami di README agar evaluator dapat melihat kedua kondisi (Hadir vs Terlambat).
- Database default adalah SQLite (`database/database.sqlite`) sesuai Konstitusi v2.0.0.
- Fitur kamera/Face ID, scanner QR darurat, notifikasi pesan WhatsApp ke wali murid, dan ekspor dokumen ditangguhkan sesuai batasan keras Milestone DEMO.
