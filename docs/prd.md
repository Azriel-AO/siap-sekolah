# Product Requirements Document (PRD) — SIAP Sekolah

> Sistem Absensi Siswa Berbasis Web dengan Kartu RFID & Face ID.
> Nama produk bersifat sementara dan dapat diganti tanpa memengaruhi isi dokumen.

---

## 1. Informasi Dokumen

| Item | Keterangan |
|---|---|
| Nama produk | SIAP Sekolah (nama sementara) |
| Jenis produk | Aplikasi absensi siswa (check-in masuk & check-out pulang) |
| Platform | Web — kiosk absen (PC/tablet + kamera + reader RFID USB di kelas) dan dashboard responsive untuk guru/siswa/admin |
| Sekolah | SMK Karya Nugraha (KNBI) |
| Lingkup sekolah | Kelas X–XII, jurusan TKJ, TKR, TBO, dan bidang keahlian lain dengan jadwal pulang berbeda-beda |
| Audiens dokumen | Tim developer, QA, dan tech lead |
| Versi dokumen | 1.0 (Draft) |
| Status | Menunggu review tim |
| Bahasa antarmuka | Indonesia (ID) |

---

## 2. Ringkasan Eksekutif

### 2.1 Latar Belakang & Masalah

Absensi di banyak sekolah masih dicatat lewat kertas yang dioper antar siswa. Model ini rawan **titip absen** (absen sekali untuk teman), catatan mudah hilang, rekap bulanan harus dihitung manual oleh wali kelas, dan orang tua tidak pernah tahu anaknya benar-benar masuk sekolah atau tidak. Masalah makin rumit di SMK: kelas X–XII antar jurusan punya **jadwal pulang berbeda**, sehingga pencatatan jam pulang sering bolong dan siswa bisa keluar duluan tanpa terdeteksi.

### 2.2 Solusi yang Diusulkan

SIAP Sekolah mencatat kehadiran lewat **dua titik absen digital**: absen masuk dan absen pulang. Siswa absen dengan **men-tap kartu pelajar RFID** atau lewat **pengenalan wajah (Face ID) via kamera** pada perangkat kiosk di kelasnya. Status kehadiran ditetapkan otomatis berdasarkan jadwal kelas masing-masing, orang tua menerima notifikasi WhatsApp/email, dan rekap lengkap siap ekspor kapan saja. Bagian paling kritis — absen pulang dengan jadwal per-jurusan yang berbeda — diatasi dengan **aturan validasi anti-loophole** yang dijelaskan di Bab 5.

### 2.3 Tujuan Produk

1. Menutup celah titip absen dan bolos: setiap tap disertai foto bukti dan divalidasi terhadap jadwal kelas siswa.
2. Menghilangkan rekap manual: persentase kehadiran dihitung otomatis, siap diekspor Excel/PDF.
3. Memberi orang bukti transparan ke orang tua melalui notifikasi WhatsApp/email.
4. Menyediakan data kehadiran terstruktur untuk tindak lanjut guru BK (siswa yang sering alpa/terlambat).
5. Tidak bergantung pada jadwal pulang yang sudah lengkap: jadwal diisi bertahap per kelas oleh admin, dan sistem tetap berjalan dengan jadwal default sementara.

---

## 3. Target Pengguna & Persona

**Siswa — Raka, 16 tahun, kelas X TKJ.** Membawa kartu pelajar RFID setiap hari. Ingin cepat absen (tap di kiosk kelas, selesai dalam hitungan detik) dan bisa melihat rekap kehadirannya sendiri tanpa bertanya ke wali kelas.

**Wali kelas — Bu Sari, 34 tahun, wali kelas XI TKJ.** Ingin tahu siapa yang belum absen saat jam masuk, menginput izin/sakit dengan keterangan, dan tidak lagi menghitung rekap manual tiap akhir bulan.

**Guru BK — Pak Dedi.** Ingin daftar siswa paling sering alpa/terlambat dan pemanggilan didasarkan data, bukan opini.

**Admin TU — Bu Lina.** Mengelola data siswa/kelas/jadwal, menerbitkan kartu, dan mengekspor laporan resmi sekolah.

**Orang tua.** Tidak punya akun; menerima notifikasi WhatsApp/email tentang kehadiran anaknya.

---

## 4. Ruang Lingkup

### 4.1 Dalam Lingkup MVP

- Kiosk absen berbasis web di kelas: tap kartu RFID dan Face ID via kamera.
- Absen masuk & absen pulang dengan validasi jadwal per kelas dan aturan anti-loophole (Bab 5).
- Pengelolaan jadwal bertingkat (default sekolah → pola mingguan per kelas → pengecualian tanggal) plus peringatan kelas yang belum memiliki jadwal pulang.
- Status kehadiran: Hadir, Terlambat, Alpa, Izin, Sakit, Pulang Cepat (dengan/tanpa izin), Tidak Absen Pulang, Menunggu Pengesahan.
- Jalur darurat siswa tanpa kartu: QR darurat dari akun siswa, Face ID, dan pengesahan guru digital di kiosk — semua berjejak dan dibatasi kuota (Bab 5.5).
- Izin digital: wali kelas input izin/sakit; guru membuat izin pulang cepat sebelum siswa tap.
- Akun dan dashboard untuk admin, guru (wali kelas & guru mapel), dan siswa (lihat rekap sendiri).
- Notifikasi WhatsApp/email ke orang tua atas status final kehadiran & kepulangan.
- Rekap harian per kelas, rekap bulanan per siswa, persentase kehadiran, ekspor Excel/PDF.
- Ranking & alert siswa dengan frekuensi alpa/terlambat tinggi untuk guru BK.
- Foto bukti otomatis dari kamera pada setiap tap.

### 4.2 Di Luar Lingkup MVP (fase berikutnya)

- Perangkat fingerprint reader dan gerbang turnstile (MVP memakai reader RFID USB + webcam yang sudah umum; fingerprint butuh SDK khusus + aplikasi jembatan lokal di PC kiosk, dikerjakan setelah fondasi stabil).
- Absen per jam pelajaran oleh guru mapel (MVP hanya masuk & pulang).
- Mobile app native (web tetap responsive di HP).
- Pembayaran SPP, nilai rapor, e-learning, dan modul akademik lainnya.
- Absen kegiatan di luar kelas (perpustakaan, lab, ekstrakurikuler).

---

## 5. Konsep Inti & Logika Absensi

### 5.1 Dua Titik Absen per Hari

| Sesi | Kapan | Hasil status |
|---|---|---|
| Masuk | Saat siswa tiba di kelas | Hadir / Terlambat / Alpa Masuk |
| Pulang | Saat jam pelajaran kelasnya selesai | Pulang Valid / Pulang Cepat (dengan/tanpa izin) / Tidak Absen Pulang |

Status harian final siswa = kombinasi keduanya. Siswa yang masuk tapi tidak pernah tap pulang **tidak bisa lolos diam-diam** — lihat 5.4.

### 5.2 Jadwal per Kelas (dasar semua validasi)

> **Catatan kondisi saat ini:** sekolah belum memiliki jadwal pulang lengkap untuk semua kelas (TKJ 1–3, TKR 1–2, TBO, dan lainnya) saat dokumen ini disusun. Karena itu jadwal didesain sebagai **data konfigurasi yang diisi admin secara bertahap** — bukan angka yang dibenamkan di dalam sistem. Pengembangan tidak menunggu jadwal tersebut selesai.

Sistem menyimpan jadwal dalam **tiga lapis**; lapis yang lebih spesifik menimpa yang lebih umum:

| Lapis | Cakupan | Kapan dipakai |
|---|---|---|
| 1. Jadwal default sekolah | Berlaku untuk semua kelas | Fallback saat kelas belum punya jadwal sendiri |
| 2. Pola mingguan per kelas | Satu kelas, berulang tiap minggu (Sen–Jum) | Dipakai setiap hari biasa |
| 3. Pengecualian per tanggal | Kelas tertentu, tanggal tertentu | Hari ujian, acara sekolah, libur nasional |

Prioritas resolusi: **pengecualian tanggal > pola mingguan kelas > default sekolah**.

Contoh pola mingguan (murni ilustrasi format — angka diisi admin nanti, bukan keputusan PRD):

| Kelas | Jam Masuk | Pulang Sen–Kam | Pulang Jumat |
|---|---|---|---|
| X TKJ 1 | 06:00 | 15:30 | 12:00 |
| XI TKJ 2 | 06:00 | 15:30 | 12:00 |
| XII TKR | 06:00 | 16:00 | 12:00 |
| X TBO | 07:00 | 14:30 | 12:00 |

Aturan proteksi agar jadwal yang belum diisi tidak merusak absen:

1. Kelas tanpa pola mingguan otomatis memakai jadwal default sekolah, dan sistem menampilkannya di **daftar "jadwal belum diatur"** agar bisa dikejar admin satu per satu.
2. Absen masuk/pulang tetap berjalan dengan nilai default; wali kelas tetap bisa mengoreksi status seperti biasa (tercatat di log audit).
3. Pengecualian tanggal dapat menandai hari sebagai "tidak ada KBM" sehingga absen pulang tidak diwajibkan dan tidak memicu status Tidak Absen Pulang pada hari itu.

### 5.3 Aturan Waktu Absen Masuk

Dengan jam masuk kelas 06:00 (aturan yang sama bergeser otomatis untuk kelas yang masuk 07:00):

| Rentang waktu tap | Status |
|---|---|
| 06:00 – 07:00 | Hadir |
| 07:01 – 08:00 | Terlambat |
| > 08:00 atau tidak tap | Alpa Masuk (dapat diubah wali kelas menjadi Izin/Sakit dengan keterangan) |

Semua batas waktu bersifat konfigurasi, bukan angka yang diprogram kaku.

### 5.4 Logika Anti-Loophole Absen Pulang

Masalah yang dijawab bagian ini: *jadwal pulang berbeda antar kelas/jurusan, bagaimana memastikan siswa tidak memanfaatkan jadwal kelas lain untuk pulang duluan, dan tidak bisa bolos sesi sore tanpa terdeteksi?*

Aturan yang berlaku (semua dieksekusi server, bukan di perangkat):

- **R1 — Validasi terhadap jadwal kelas sendiri.** Saat siswa tap pulang, sistem mengambil jadwal pulang kelas siswa tersebut untuk hari itu. Tidak ada jam pulang global. Jika jadwal kelas untuk hari itu belum diatur, sistem memakai jadwal default sekolah (Bab 5.2) dan menandai kelas tersebut di daftar "jadwal belum diatur".
- **R2 — Perangkat terikat kelas.** Setiap kiosk terdaftar sebagai milik satu kelas (device_id ↔ kelas). Tap di kiosk kelas lain dicatat sebagai **anomali**: tidak mengubah status, tersimpan sebagai log, dan memunculkan notifikasi ke wali kelas.
- **R3 — Jendela tap pulang valid:** dari jam pulang kelas sampai jam pulang + 60 menit (toleransi diatur admin, default 60). Di luar jendela ini tap tetap tercatat tapi diproses menurut R4/R5.
- **R4 — Tap terlalu awal → Pulang Cepat.** Jika ada izin pulang cepat yang dibuat guru dan sudah disetujui, tap valid dengan status "Pulang Cepat (Izin)". Jika tidak ada izin → status "Pulang Cepat Tanpa Izin" + notifikasi langsung ke orang tua.
- **R5 — Tidak tap sampai jendela tutup → Tidak Absen Pulang.** Sistem menandai siswa, memberi alert ke wali kelas (indikasi bolos sesi sore). Wali kelas wajib menindaklanjuti: konfirmasi jadi "Bolos" atau koreksi manual dengan alasan. Koreksi manual selalu mencatat nama penindak dan alasannya.
- **R6 — Izin hanya bisa dibuat oleh guru, sebelum tap.** Siswa tidak bisa membuat izin dari sisi siswa, dan tap tidak bisa menciptakan izin sendiri.
- **R7 — Bukti foto & jam server.** Setiap tap (RFID maupun Face ID) menyimpan: foto otomatis dari kamera, waktu **server** (jam perangkat diabaikan agar tidak dimanipulasi), ID perangkat, dan ID siswa.
- **R8 — Fallback terkendali.** Jika perangkat/kamera/kartu bermasalah, kehadiran disahkan oleh guru secara digital langsung di kiosk (Bab 5.5, US-38) — bukan pencatatan kertas. Pengesahan selalu menyertakan foto, identitas guru, dan alasan, serta ditandai jelas di rekap agar mudah diaudit.

Dengan R1–R5, tiga celah utama tertutup: pulang duluan dengan jam kelas lain (R1–R2), minta teman tap (R7 + foto), dan keluar tengah hari tanpa jejak (R5 + R4).

### 5.5 Rantai Verifikasi & Fallback (menghadapi siswa tanpa kartu)

Masalah yang dijawab bagian ini: *siswa lupa membawa kartu — bagaimana agar tetap bisa absen dengan jejak, tanpa membuka celah titip absen, dan tanpa menumpuk antrean di depan guru?*

**Prinsip dasar: metode verifikasi dan status kehadiran dipisah.** Semua jalur menghasilkan status kehadiran yang sama (dihitung dari waktu server); yang berbeda hanya atribut `metode_verifikasi` yang tersimpan di setiap tap. Tidak ada jalur darurat yang "lebih murah" secara status, dan tidak ada jalur yang anonim.

**Urutan percobaan (cascade) di kiosk:**

| Urutan | Jalur | Syarat | Kecepatan | Jejak yang tersimpan |
|---|---|---|---|---|
| 1 | Kartu RFID | Kartu aktif | < 2 detik | Foto + jam server |
| 2 | QR darurat dari akun siswa | Siswa login di akunnya sendiri lewat HP, kuota tersedia | < 3 detik | Foto + jam server + log pembuatan QR |
| 3 | Face ID | Wajah terenrolled + izin orang tua | 2–3 detik, maks 3 percobaan | Foto + match score + log kegagalan |
| 4 | Pengesahan guru di kiosk | Guru login di kiosk (wali kelas kelas itu / guru piket) | Sesuai antrean | Foto + identitas guru + alasan |

**Flow keputusan:**

```
saat siswa tiba di kiosk:
  jika kartu berhasil di-tap & kartu aktif:
      hadirkan(metode="kartu")                    # jalur 1, selesai
  jika siswa punya akun aktif & akses HP:
      buat QR darurat (sekali pakai, kedaluwarsa 2 menit)
      kiosk validasi tanda tangan digital QR      # tetap jalan walau offline
      hadirkan(metode="qr_darurat")               # jalur 2, selesai
  jika wajah terenrolled:
      coba maksimal 3×; jika match ≥ ambang:
          hadirkan(metode="face")                 # jalur 3, selesai
  jika tidak ada yang berhasil:
      status = "Menunggu Pengesahan"              # BUKAN alpa
      guru login di kiosk → pilih siswa → foto → sahkan(alasan="tanpa kartu")
      hadirkan(metode="sah_guru", penanggung_jawab=guru)   # jalur 4
  jika sampai batas jam masuk tidak ada yang menyahkan:
      status mengikuti aturan jam masuk normal (Terlambat/Alpa)
```

Dua poin penting dari flow di atas:

1. Siswa yang lupa kartu **tidak otomatis alpa** — statusnya "Menunggu Pengesahan" sampai batas jam masuk; selama itu guru bisa menyahkan lewat kiosk.
2. Pengesahan guru **tidak mengubah telat jadi tepat** — waktu yang dipakai tetap waktu server; guru hanya memverifikasi identitas, bukan mengubah jam.

**Aturan anti-penyalahgunaan (F1–F6, melengkapi R1–R8):**

- **F1 — Semua jalur berjejak sama rapi.** Tap dari jalur mana pun menyimpan foto, jam server, metode, dan identitas pelaku; rekap menampilkan metode verifikasi (kartu/QR/face/sah guru) untuk kebutuhan audit.
- **F2 — QR darurat terbatas & terikat akun.** Hanya bisa dibuat dari akun siswa itu sendiri (login password), sekali pakai, kedaluwarsa 2 menit, kuota default 2×/bulan (diatur admin). Pembuatan QR tercatat di log.
- **F3 — Face ID dibatasi percobaan.** Maksimal 3 percobaan per sesi; setiap kegagalan dicatat untuk diagnosis (pencahayaan, pose). Setelah 3 gagal → lanjut ke jalur 4, bukan diulang terus.
- **F4 — Pengesahan guru berwewenang.** Hanya wali kelas kelas itu atau guru piket yang bisa menyahkan; pengesahan siswa kelas lain tercatat sebagai pengecualian. Guru tidak bisa menyahkan tanpa siswa hadir di depan kiosk — foto wajib diambil saat pengesahan.
- **F5 — Kuota fallback & akar masalah.** Jika siswa memakai jalur darurat melewati kuota bulanan (default 3×), sistem memberi alert ke wali kelas dan menugaskan tindak lanjut ke akar masalah: cetak kartu pengganti (US-09) atau enrollment wajah (US-10). Frekuensi fallback masuk rekap BK (US-39).
- **F6 — Mode ketat mendeteksi kartu bawaan orang lain (opsional).** Bila diaktifkan admin, tap kartu diikuti cek wajah kilat; wajah tidak cocok dengan pemilik kartu → tetap tercatat hadir tapi ditandai **anomali titip absen** + alert wali kelas (US-40).

---

## 6. Fitur & User Stories

Prioritas: **P1 = wajib di MVP**, P2 = penting, boleh menyusul setelah MVP stabil.

### 6.1 Autentikasi & Akun

| ID | User Story | Prio |
|---|---|---|
| US-01 | Sebagai pengguna (admin/guru/siswa), saya ingin login dengan akun yang role-nya berbeda agar hanya melihat fungsi sesuai wewenang saya. | P1 |
| US-02 | Sebagai pengguna, saya ingin reset password lewat email agar bisa kembali masuk jika lupa sandi. | P1 |
| US-03 | Sebagai siswa, saya ingin login dan melihat rekap kehadiran saya sendiri agar tahu posisi kehadiran saya. | P1 |
| US-04 | Sebagai admin, saya ingin mengelola akun pengguna dan mereset sandi wali kelas agar operasional tidak macet. | P1 |

### 6.2 Data Master (siswa, kelas, jadwal, kartu)

| ID | User Story | Prio |
|---|---|---|
| US-05 | Sebagai admin, saya ingin mengelola data siswa, kelas, dan jurusan (X–XII) agar sistem mencerminkan struktur sekolah. | P1 |
| US-06 | Sebagai admin, saya ingin mengatur pola mingguan jam masuk & jam pulang per kelas (Senin–Jumat) agar jadwal tiap jurusan terakomodasi sekali isi. | P1 |
| US-07 | Sebagai admin, saya ingin mengatur satu jadwal default sekolah sebagai fallback agar kelas yang belum punya jadwal sendiri tetap bisa absen. | P1 |
| US-08 | Sebagai admin, saya ingin mengimpor data siswa dan pola jadwal per kelas dari Excel agar setup awal puluhan kelas tidak dilakukan satu per satu. | P2 |
| US-09 | Sebagai admin, saya ingin menerbitkan dan mengganti kartu RFID siswa dengan membaca UID kartu via reader (termasuk saat kartu hilang) agar siswa tetap bisa absen tanpa mencetak kode. | P1 |
| US-10 | Sebagai siswa, saya ingin mendaftar wajah (Face ID) lewat kamera agar bisa absen tanpa mengeluarkan kartu. | P1 |

**Alur pendaftaran kredensial terpadu (enrollment terpadu).** Saat pendaftaran siswa baru atau penggantian kredensial, admin membuka satu halaman profil siswa dan mendaftarkan semua kredensial dari tempat yang sama, semuanya terikat ke satu akun siswa: (1) tap kartu RFID untuk menautkan UID (US-09); (2) perekaman wajah via kamera, 3–5 pose, disimpan sebagai embedding — hanya boleh dijalankan setelah persetujuan orang tua tercatat (Bab 9 poin 3); (3) slot pendaftaran sidik jari disiapkan di alur yang sama namun aktif pada fase berikutnya (butuh SDK pabrikan + aplikasi jembatan lokal di PC kiosk, Bab 4.2). Konsekuensi desain: setiap kredensial dapat dicabut/diganti terpisah tanpa memengaruhi kredensial lain, dan di kiosk, kredensial mana pun yang dipakai harus me-resolve ke siswa yang sama (metode verifikasi berbeda, identitas sama).

### 6.3 Jadwal & Pengecualian

| ID | User Story | Prio |
|---|---|---|
| US-11 | Sebagai admin, saya ingin membuat pengecualian per tanggal (ujian, acara sekolah, hari libur) yang mengubah jam masuk/pulang kelas tertentu agar hari khusus tidak memicu status yang salah. | P2 |
| US-12 | Sebagai admin, saya ingin daftar dan peringatan kelas yang belum memiliki jadwal pulang agar pendataan jadwal tidak ada yang terlewat. | P1 |
| US-13 | Sebagai wali kelas, saya ingin melihat jadwal kelas saya beserta pengecualian yang akan datang agar saya tahu kapan siswa saya wajib tap pulang. | P2 |

### 6.4 Kiosk Absen di Kelas

| ID | User Story | Prio |
|---|---|---|
| US-14 | Sebagai siswa, saya ingin men-tap kartu RFID saya di kiosk kelas agar absen masuk tercatat dalam hitungan detik. | P1 |
| US-15 | Sebagai siswa, saya ingin absen lewat Face ID di kiosk saat saya tidak membawa kartu. | P2 |
| US-16 | Sebagai siswa, saya ingin kiosk menampilkan konfirmasi (nama, jam, status, foto saya) agar saya yakin absen saya tercatat. | P1 |
| US-17 | Sebagai wali kelas, saya ingin kiosk tetap merekam absen saat internet mati (antrian lokal, sinkron otomatis) agar absen pagi tidak kacau. | P1 |
| US-18 | Sebagai wali kelas, saya ingin tap di kiosk kelas yang salah ditandai sebagai anomali agar siswa tidak curang lewat kelas lain. | P1 |

### 6.5 Verifikasi Alternatif & Fallback (siswa tanpa kartu)

| ID | User Story | Prio |
|---|---|---|
| US-35 | Sebagai siswa yang lupa membawa kartu, saya ingin membuat QR darurat sekali pakai dari akun saya sendiri lewat HP agar tetap bisa absen tanpa menunggu guru. | P1 |
| US-36 | Sebagai sistem, saya memvalidasi QR darurat dengan tanda tangan digital, sekali pakai, kedaluwarsa 2 menit, dan kuota bulanan agar jalur darurat tidak disalahgunakan dan tetap berfungsi saat kiosk offline. | P1 |
| US-37 | Sebagai siswa tanpa kartu yang wajahnya sudah terdaftar, saya ingin absen via Face ID dengan maksimal 3 percobaan agar kegagalan tidak menghambat antrean. | P2 |
| US-38 | Sebagai guru (wali kelas/guru piket), saya ingin mengesahkan kehadiran siswa langsung di kiosk dengan foto dan alasan agar siswa tanpa kartu tetap tercatat dengan jejak penanggung jawab. | P1 |
| US-39 | Sebagai wali kelas, saya ingin melihat frekuensi penggunaan jalur darurat per siswa dan menerima alert saat melewati kuota bulanan agar kasus "lupa kartu" kronis ditindaklanjuti ke akar-akarnya (kartu pengganti/enrollment wajah). | P2 |
| US-40 | Sebagai sistem, saya menandai anomali titip absen saat wajah tidak cocok dengan pemilik kartu yang di-tap (mode ketat) agar kartu yang dipinjam teman tetap terdeteksi. | P2 |

### 6.6 Status, Izin & Koreksi

| ID | User Story | Prio |
|---|---|---|
| US-19 | Sebagai sistem, saya menetapkan status Hadir/Terlambat/Alpa otomatis dari jadwal kelas agar tidak ada penilaian manual. | P1 |
| US-20 | Sebagai guru, saya ingin membuat izin pulang cepat (jam & alasan) sebelum siswa tap pulang agar kepulangan dini tercatat sah. | P1 |
| US-21 | Sebagai wali kelas, saya ingin menginput status Izin/Sakit dengan keterangan (dan lampiran foto surat bila ada) agar catatan resmi. | P1 |
| US-22 | Sebagai wali kelas, saya ingin menindaklanjuti siswa "Tidak Absen Pulang" (konfirmasi bolos atau koreksi dengan alasan) agar tidak ada kasus menggantung. | P1 |
| US-23 | Sebagai sistem, saya menyimpan log koreksi manual (siapa, kapan, alasannya) agar setiap perubahan bisa diaudit. | P1 |

### 6.7 Monitoring & Rekap

| ID | User Story | Prio |
|---|---|---|
| US-24 | Sebagai wali kelas, saya ingin papan kehadiran kelas hari ini secara real-time (siapa belum absen masuk/pulang) agar bisa mengambil tindakan cepat. | P1 |
| US-25 | Sebagai guru, saya ingin mencari riwayat kehadiran per siswa per bulan saat berkomunikasi dengan orang tua. | P1 |
| US-26 | Sebagai siswa, saya ingin melihat rekap kehadiran saya (harian/bulanan, jumlah terlambat) agar bisa memperbaiki diri. | P1 |
| US-27 | Sebagai admin, saya ingin dashboard persentase kehadiran seluruh kelas hari ini agar kondisi sekolah terpantau sekilas. | P1 |

### 6.8 Laporan & Guru BK

| ID | User Story | Prio |
|---|---|---|
| US-28 | Sebagai admin/wali kelas, saya ingin mengekspor rekap harian dan bulanan ke Excel/PDF agar laporan resmi bisa dicetak. | P1 |
| US-29 | Sebagai guru BK, saya ingin daftar peringkat siswa paling sering alpa/terlambat per bulan agar pemanggilan berbasis data. | P1 |
| US-30 | Sebagai guru BK, saya ingin alert otomatis saat siswa mencapai ambang alpa (misal 3× dalam sebulan, ambang diatur admin) agar tidak ada siswa terlewat. | P2 |

### 6.9 Notifikasi Orang Tua

| ID | User Story | Prio |
|---|---|---|
| US-31 | Sebagai orang tua, saya ingin menerima WhatsApp/email saat status anak saya final hari itu (Izin/Sakit/Alpa/Pulang Cepat Tanpa Izin/Tidak Absen Pulang) agar saya tahu hari itu juga. | P1 |
| US-32 | Sebagai orang tua, saya ingin notifikasi "anak sudah pulang" setelah tap pulang valid agar tenang anaknya sudah keluar sekolah. | P1 |

### 6.10 Pengaturan Sistem

| ID | User Story | Prio |
|---|---|---|
| US-33 | Sebagai admin, saya ingin mengatur parameter waktu (batas terlambat, toleransi pulang, ambang alert BK) tanpa perlu programmer. | P1 |
| US-34 | Sebagai admin, saya ingin log aktivitas semua aksi sensitif (koreksi status, ganti kartu, ubah jadwal) agar jejak audit lengkap. | P2 |

---

## 7. Persyaratan Non-Fungsional

| Kategori | Persyaratan |
|---|---|
| Performa | Proses scan → konfirmasi di kiosk < 2 detik. Antrean per kelas ≤ 36 siswa habis < 2 menit. Page dashboard < 3 detik. |
| Kapasitas | Mendukung minimal 600 siswa, 30 kelas, dan 30 kiosk melakukan tap serentak pada rentang 06:00–07:00. |
| Offline-first | Kiosk menyimpan tap di penyimpanan lokal saat jaringan mati dan menyinkronkan otomatis begitu online, tanpa duplikat. |
| Keamanan & privasi | HTTPS wajib; data wajah disimpan sebagai fitur biometrik (embedding), foto bukti diakses hanya oleh role tertentu; persetujuan orang tua menjadi prasyarat fitur Face ID; semua endpoint dilindungi role. |
| Integritas waktu | Semua status berbasis waktu server; jam perangkat tidak dipercaya. |
| Akurasi biometrik | Ambang kecocokan wajah dan jumlah percobaan (maks 3) diatur admin; kegagalan Face ID tidak boleh memblokir jalur verifikasi lain. |
| Ketersediaan | Uptime 99% pada jam sekolah (06:00–17:00); backup database harian. |
| Kompatibilitas | Kiosk: Chrome/Edge versi terbaru di Windows/Android tablet, kamera USB/bawaan + reader RFID USB mode keyboard. Dashboard responsive: Chrome, Safari, Firefox di desktop & HP. |
| Aksesibilitas | Kontras WCAG AA; font kiosk cukup besar dibaca saat berdiri; feedback suara singkat saat tap berhasil/gagal. |

---

## 8. Metrik Sukses

| Metrik | Target 3 bulan setelah rilis |
|---|---|
| Kelas dengan absen masuk & pulang lengkap tiap hari | ≥ 95% |
| Insiden titip absen terdeteksi (via foto/verifikasi) | 100% tercatat sebagai log |
| Orang tua menerima notifikasi < 5 menit setelah status final | ≥ 90% |
| Waktu penyusunan rekap bulanan wali kelas | Dari ±2 jam manual → < 5 menit (ekspor) |
| Kasus "Tidak Absen Pulang" yang ditindaklanjuti wali kelas | 100% |
| Siswa tanpa kartu tetap tercatat dengan jejak (QR darurat/Face ID/pengesahan guru) | ≥ 98% kasus |

---

## 9. Asumsi & Ketergantungan

1. Setiap kelas dilengkapi 1 perangkat kiosk: PC/tablet dengan kamera + reader RFID USB mode keyboard + koneksi internet (hardware disiapkan sekolah).
2. Kartu pelajar RFID dibagikan untuk seluruh siswa sebelum go-live; admin mendaftarkan UID tiap kartu langsung via reader saat penerbitan (US-09) — tanpa perlu mencetak kode.
3. Fitur Face ID memerlukan persetujuan/izin orang tua (kebijakan data biometrik); sebelum disetujui, siswa cukup memakai kartu.
4. Notifikasi WhatsApp menggunakan layanan gateway pihak ketiga (kandidat: Fonnte/Wablas/Twilio — diputuskan tech lead); email via SMTP sekolah.
5. Sekolah belum memiliki jadwal pulang lengkap saat dokumen ini disusun (kelas TKJ 1–3, TKR 1–2, TBO, dan lainnya). Jadwal didata bertahap oleh admin TU sebagai bagian dari M1; sebelum tersedia, sistem memakai jadwal default sekolah dan menandai kelas yang belum diatur — pengembangan tidak menunggu jadwal tersebut.
6. Kebijakan status (batas terlambat, toleransi pulang, ambang alert BK) ditetapkan sekolah dan diatur lewat halaman konfigurasi, bukan hardcoded.

---

## 10. Risiko Utama

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Kamera/reader di kelas rusak | Absen kelas itu berhenti | Fallback manual wali kelas (R8) + perangkat cadangan per lantai |
| Internet sekolah mati saat jam masuk | Tap tidak terkirim | Kiosk offline-first (US-17): antrian lokal + sinkronisasi |
| Titip absen kartu oleh teman | Data kehadiran tidak jujur | Foto bukti tiap tap (R7) + mode ketat wajah-vs-kartu (US-40) + anomali kiosk salah kelas (R2) |
| Siswa "lupa kartu" berulang | Jalur darurat jadi kebiasaan, akar masalah tak selesai | Kuota bulanan + alert wali kelas + tindak lanjut kartu pengganti/enrollment (F5, US-39) |
| Siswa keberatan data wajah | Adopsi Face ID rendah | Face ID opsional; kartu RFID tetap jalur utama; izin orang tua |
| Jadwal pulang belum lengkap saat go-live | Absen pulang divalidasi dengan angka yang salah | Jadwal default sekolah + daftar "kelas belum diatur" (US-12); pendataan jadwal masuk M1 |
| Jadwal pulang sering berubah (ujian, acara) | Validasi pulang salah | Pengecualian per tanggal (US-11); perubahan berlaku mulai tanggal yang dipilih |
| Kartu hilang/dipinjam | Siswa tak bisa absen / salah identitas | Penggantian kartu cepat (US-09); kode kartu lama otomatis mati |

---

## 11. Milestone Rilis

| Fase | Isi | Perkiraan |
|---|---|---|
| M1 — Fondasi | Akun & role, data master (siswa/kelas/kartu), jadwal bertingkat (default, pola mingguan, pengecualian), impor Excel, pendataan jadwal per kelas | Minggu 1–4 |
| M2 — Mesin Absen | Kiosk tap kartu RFID + foto bukti, jalur darurat tanpa kartu (QR darurat, Face ID, pengesahan guru; F1–F6), logika masuk & pulang (R1–R8), izin & koreksi, offline queue | Minggu 5–9 |
| M3 — Informasi & Rilis | Dashboard, rekap + ekspor, alert BK, notifikasi WA/email, uji pilot 2 kelas, rollout | Minggu 10–14 |
| MVP+ | Face ID penuh, absen per jam pelajaran, mobile app | Setelah stabil |

---

## 12. Glosarium

- **Kiosk absen**: perangkat (PC/tablet + kamera + reader RFID) yang terpasang di kelas khusus untuk mencatat kehadiran.
- **Kartu RFID kartu pelajar**: kartu berchip yang UID-nya terbaca oleh reader USB. Reader mode keyboard bekerja seperti alat ketik: "mengetik" UID + Enter ke kolom aktif, sehingga halaman web cukup menyediakan satu kolom input terfokus. UID terikat ke data siswa saat penerbitan kartu.
- **Face ID**: pengenalan wajah via kamera untuk memverifikasi bahwa yang absen benar pemilik akun.
- **Embedding**: representasi matematis wajah yang disimpan sebagai data biometrik, bukan foto mentah.
- **Titip absen**: absen yang dilakukan seseorang atas nama orang lain.
- **Pulang Cepat Tanpa Izin (PTI)**: tap pulang sebelum jam pulang kelas tanpa izin guru.
- **Tidak Absen Pulang**: siswa tidak melakukan tap pulang sampai jendela waktu berakhir; indikasi bolos sesi sore.
- **BK**: Bimbingan Konseling — unit sekolah yang menangani pembinaan siswa.
- **Offline-first**: kemampuan aplikasi tetap bekerja saat jaringan mati dan menyinkronkan data setelah online.
- **Jadwal default (fallback)**: jam masuk/pulang sekolah yang berlaku untuk semua kelas yang belum memiliki jadwal sendiri.
- **Pola mingguan**: jadwal per kelas yang berulang setiap minggu (Senin–Jumat).
- **Pengecualian tanggal**: aturan jadwal khusus untuk tanggal tertentu (ujian, acara, libur) yang menimpa pola mingguan.
- **KBM**: Kegiatan Belajar-Mengajar.
- **QR darurat**: kode sekali pakai yang dibuat siswa dari akunnya sendiri saat lupa kartu; kedaluwarsa cepat dan dibatasi kuota.
- **Pengesahan guru (digital endorsement)**: pencatatan kehadiran oleh guru langsung di kiosk untuk siswa tanpa kartu; selalu disertai foto, identitas guru, dan alasan.
- **Mode ketat**: pengaturan opsional yang menjalankan verifikasi wajah tambahan pada tap kartu untuk mendeteksi kartu yang dipinjam orang lain.
- **Kuota fallback**: batas maksimum penggunaan jalur darurat per siswa per bulan sebelum memicu alert dan tindak lanjut.
