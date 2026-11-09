<!--
SYNC IMPACT REPORT (remove before committing)
=============================================
Version change: 1.0.0 → 2.0.0 (MAJOR — stack redefinition)
Modified principles:
  - Prinsip IX: "Stack Teknis Tetap" — DIGANTI TOTAL
    Lama: Next.js (React) + Tailwind CSS + Prisma + SQLite
    Baru: Laravel 13 + Blade + Tailwind CSS (via Vite) + SQLite (dapat pindah ke MySQL)
Modified sections:
  - "Stack & Arsitektur Teknis" — diperbarui sesuai Laravel (hapus Next.js App Router, Prisma)
  - "Alur Pengembangan & Kualitas > Penamaan & Konvensi" — disesuaikan konvensi Laravel
  - Prinsip II — dihapus referensi kode spesifik Next.js (`new Date()`, "API route")
Added sections: none
Removed sections: none
Follow-up TODOs:
  - TODO(SPEC_REVIEW): Spec 001-fondasi-login-dashboard ditulis sebelum pergantian stack.
    Spec itu sendiri sudah technology-agnostic (tidak menyebut framework), sehingga MASIH
    VALID. Namun jika sudah ada plan/tasks dari spec tersebut, plan/tasks itu perlu dibuat
    ulang dengan /speckit-plan menggunakan stack Laravel baru.
-->

# SIAP KNBI Constitution

> Konstitusi ini adalah dokumen tata kelola tertinggi untuk proyek **SIAP KNBI** —
> aplikasi absensi siswa berbasis web untuk SMK Karya Nugraha (KNBI).
> Semua spec, task, dan keputusan teknis WAJIB diuji terhadap prinsip-prinsip di bawah ini.
> Jika ada konflik antara konstitusi ini dan sumber lain, konstitusi ini yang menang —
> kecuali konflik dengan `docs/prd.md`, di mana PRD yang menang (Prinsip I).

---

## Core Principles

### I. Satu Sumber Kebenaran (NON-NEGOTIABLE)

`docs/prd.md` adalah satu-satunya referensi fitur dan aturan bisnis yang sah.

- Setiap spec dan task HARUS bisa dilacak ke nomor user story (US-01 s.d. US-40) di PRD.
- Jika ada konflik antara ide agen/developer dan PRD, **PRD yang menang**.
- Perubahan fitur HARUS dimulai dari amandemen PRD, bukan dari kode atau spec.
- Agent dilarang menambah, menghapus, atau memodifikasi fitur di luar cakupan PRD tanpa
  persetujuan eksplisit dan pembaruan PRD terlebih dahulu.

**Alasan**: Mencegah "feature creep" yang tidak terkontrol dan memastikan semua anggota tim
(termasuk yang baru bergabung) selalu merujuk pada dokumen yang sama. PRD adalah "kontrak"
antara tim developer dan sekolah.

### II. Waktu Selalu dari Server (NON-NEGOTIABLE)

Semua pencatatan waktu absen menggunakan **jam server**, bukan jam perangkat/kiosk/browser.

- Server WAJIB menjadi satu-satunya sumber timestamp untuk setiap record kehadiran.
- Kiosk dan klien TIDAK BOLEH mengirim timestamp mereka sendiri sebagai waktu absen yang valid.
- Implementasi HARUS mengambil waktu dari sisi server saat request diterima — bukan dari
  data yang dikirim oleh browser atau perangkat kiosk.
- Referensi PRD: **R7**.

**Alasan**: Jam perangkat bisa dimanipulasi (maju/mundur) untuk menghindari status terlambat.
Jam server adalah jam yang tidak bisa diubah siswa — ini adalah pondasi anti-kecurangan sistem.

### III. Metode Verifikasi ≠ Status Kehadiran (NON-NEGOTIABLE)

Kartu RFID, QR darurat, Face ID, dan pengesahan guru adalah **cara membuktikan identitas** saja.

- Status kehadiran akhir (Hadir, Terlambat, dst.) HANYA ditentukan oleh **aturan waktu dan
  jadwal kelas di server** — bukan oleh metode verifikasi yang digunakan.
- Semua metode verifikasi yang valid menghasilkan "identitas siswa terkonfirmasi"; status
  ditetapkan oleh logika jadwal setelah konfirmasi identitas.
- Referensi PRD: lihat alur status di Bab 5.

**Alasan**: Memisahkan "siapa" (identifikasi) dari "kapan" (penilaian status) mencegah
kondisi di mana metode tertentu secara tidak sengaja memberi siswa status yang berbeda.
Semua siswa diperlakukan sama oleh aturan waktu.

### IV. Status Kehadiran Terbatas (NON-NEGOTIABLE)

Hanya **8 status kehadiran** yang diizinkan dalam seluruh sistem:

| Status | Kode | Keterangan |
|--------|------|------------|
| Hadir | `HADIR` | Absen masuk tepat waktu |
| Terlambat | `TERLAMBAT` | Absen masuk setelah batas waktu |
| Alpa | `ALPA` | Tidak absen masuk tanpa keterangan |
| Izin | `IZIN` | Tidak hadir dengan surat izin resmi |
| Sakit | `SAKIT` | Tidak hadir karena sakit dengan keterangan |
| Pulang Cepat (Izin) | `PULANG_CEPAT_IZIN` | Pulang sebelum jadwal dengan izin guru |
| Pulang Cepat (Tanpa Izin) | `PULANG_CEPAT_TANPA_IZIN` | Pulang sebelum jadwal tanpa izin |
| Tidak Absen Pulang | `TIDAK_ABSEN_PULANG` | Absen masuk tapi tidak absen pulang |
| Menunggu Pengesahan | `MENUNGGU_PENGESAHAN` | Tap darurat, menunggu konfirmasi guru |

- **DILARANG** membuat status baru tanpa terlebih dahulu mengubah PRD dan konstitusi ini.
- Semua komponen UI, laporan, dan ekspor HARUS hanya menampilkan status dari daftar di atas.
- Referensi PRD: Bab 4.1.

**Alasan**: Status yang tidak terdefinisi di PRD berarti aturan bisnis yang belum dipikirkan
matang. Membatasi status mencegah inkonsistensi data dan logika yang sulit di-debug.

### V. Semua Aksi Manual Berjejak — Audit Trail (NON-NEGOTIABLE)

Setiap tindakan yang mengubah data kehadiran secara manual WAJIB mencatat:

1. **Siapa** — akun pengguna yang melakukan tindakan (user ID + nama + peran)
2. **Kapan** — timestamp dari server (bukan klien)
3. **Alasan** — teks keterangan yang diisi pengguna, tidak boleh kosong
4. **Foto** — bukti visual bila relevan (misalnya pengesahan guru, koreksi status)

Tindakan yang wajib berjejak mencakup (tidak terbatas pada):
- Koreksi manual status kehadiran oleh guru/admin
- Pengesahan guru untuk tap darurat
- Perubahan jadwal kelas
- Penerbitan dan pemblokiran kartu RFID
- Pemberian izin pulang cepat

Referensi PRD: **F1** (Audit Trail).

**Alasan**: Jejak audit adalah mekanisme akuntabilitas. Tanpa ini, tidak ada cara mengetahui
"siapa yang mengubah nilai siswa ini dan kenapa" — ini penting untuk kepercayaan sekolah
terhadap sistem.

### VI. Terapkan R1–R8 dan F1–F6 Secara Harfiah (NON-NEGOTIABLE)

Semua aturan anti-loophole di PRD Bab 5.4 (R1–R8) dan aturan anti-penyalahgunaan fallback
di PRD Bab 5.5 (F1–F6) adalah **bagian dari konstitusi ini** dan berlaku mengikat.

- Setiap spec atau task yang menyentuh alur absen masuk/pulang dan jalur darurat HARUS
  membaca dan mengacu pada Bab 5.4–5.5 PRD sebelum ditulis.
- Implementasi TIDAK BOLEH menyederhanakan atau melewati aturan R1–R8 dan F1–F6, bahkan
  jika terasa lebih mudah secara teknis.
- Referensi PRD: **Bab 5.4** (aturan anti-loophole) dan **Bab 5.5** (aturan anti-penyalahgunaan).

**Alasan**: Aturan-aturan ini lahir dari analisis celah absensi nyata di SMK. Melewatinya,
meski dengan niat baik, akan membuka kembali celah yang sudah diidentifikasi dan disetujui
oleh pemangku kepentingan sekolah.

### VII. Kiosk Terikat Kelas (NON-NEGOTIABLE)

Setiap perangkat kiosk terdaftar untuk **satu kelas** melalui pasangan `device_id ↔ kelas`.

- Tap yang dilakukan di kiosk kelas lain dianggap **anomali**.
- Anomali WAJIB dicatat sebagai log audit tetapi TIDAK BOLEH mengubah status kehadiran siswa.
- UI kiosk HARUS menampilkan pesan yang jelas kepada siswa jika mereka tap di kiosk yang
  salah (bukan kelas mereka).
- Referensi PRD: **R2**.

**Alasan**: Mencegah siswa absen dari kelas lain ("nitip absen lintas kelas"). Kiosk yang
terikat kelas memastikan kehadiran fisik di ruang kelas yang benar.

### VIII. Privasi Data Siswa (NON-NEGOTIABLE)

Data biometrik siswa (Face ID) dan foto bukti absen HARUS dilindungi:

- Face ID **hanya menyimpan embeddings** (vektor angka), **bukan foto wajah** asli.
- Penggunaan Face ID WAJIB mendapat **persetujuan orang tua** terlebih dahulu (formulir
  persetujuan digital/fisik).
- Percobaan Face ID dibatasi **maksimal 3 kali per sesi** untuk mencegah brute-force.
- Foto bukti absen (dari kamera kiosk) **hanya boleh diakses oleh guru dan admin**;
  tidak boleh ditampilkan ke publik atau siswa lain.
- Referensi PRD: **F3**.

**Alasan**: Siswa di bawah umur (kelas X–XII SMK) dilindungi oleh regulasi data anak.
Menyimpan foto wajah penuh menimbulkan risiko privasi dan hukum yang tidak perlu bila
embeddings sudah cukup untuk identifikasi.

### IX. Stack Teknis Tetap (NON-NEGOTIABLE)

Stack teknologi tidak dapat diganti tanpa amandemen konstitusi yang disetujui:

| Layer | Teknologi |
|-------|-----------|
| Framework | Laravel 13 |
| Templating / UI | Blade + Tailwind CSS (via Laravel Vite) |
| Database default | SQLite (dapat dipindah ke MySQL saat deployment tanpa menulis ulang kode) |
| Autentikasi | Laravel Starter Kit resmi (Breeze) |
| Prasyarat server | PHP 8.3+ dan Composer |
| Bahasa UI | Bahasa Indonesia, kata sederhana dan umum |

- Pilih implementasi yang **paling umum dan banyak tutorialnya** — utamakan tutorial
  berbahasa Indonesia — bukan yang paling canggih atau terbaru.
- Hindari package pihak ketiga yang eksperimental atau kurang terdokumentasi.
- Semua teks label, pesan error, dan notifikasi di UI WAJIB dalam Bahasa Indonesia.

**Alasan**: Tim developer belum berpengalaman. Laravel adalah framework PHP yang paling banyak
digunakan di Indonesia, dengan ekosistem tutorial, forum, dan komunitas lokal yang besar.
Blade adalah template engine bawaan Laravel yang tidak perlu dipelajari terpisah. SQLite
memudahkan setup awal tanpa server database terpisah, dan mudah dipindah ke MySQL saat
sekolah membutuhkan kapasitas lebih.

### X. Sederhana Dulu, Kompleks Belakangan (NON-NEGOTIABLE)

Fitur-fitur lanjutan TIDAK BOLEH dibangun sebelum fondasi selesai dan teruji:

**Urutan Tahap yang Wajib Diikuti:**

```
Tahap 1 (Fondasi): Login + Data Master + Kiosk Kartu RFID + Dashboard Dasar
      ↓ [demo utuh + disetujui]
Tahap 2: QR Darurat + Pengesahan Guru + Izin Digital
      ↓ [demo utuh + disetujui]
Tahap 3: Face ID + Notifikasi WhatsApp/Email
      ↓ [demo utuh + disetujui]
Tahap 4: Mode Offline Kiosk + Fitur Lanjutan Lainnya
```

- Setiap tahap HARUS bisa didemokan secara utuh dan fungsional sebelum lanjut ke tahap berikutnya.
- Task untuk tahap berikutnya TIDAK BOLEH di-implement sebelum tahap sebelumnya disetujui.

**Alasan**: Membangun fitur kompleks di atas fondasi yang belum stabil menghasilkan bug
berlapis yang sulit dilacak. Demo per tahap memastikan sekolah bisa memberi masukan nyata
sebelum investasi waktu lebih jauh.

### XI. Setiap Fitur Harus Bisa Diuji Manusia (NON-NEGOTIABLE)

Setiap implementasi fitur WAJIB disertai **langkah pengujian manual** yang:

- Ditulis dalam Bahasa Indonesia yang mudah dipahami orang awam
- Dimulai dari cara menjalankan aplikasi (perintah di terminal)
- Menjelaskan langkah demi langkah apa yang harus diklik/diisi
- Menyatakan hasil yang diharapkan ("seharusnya muncul...")
- Mencakup skenario error/gagal yang umum

**Alasan**: Tim belum memiliki QA engineer berpengalaman. Langkah pengujian manual yang
jelas memungkinkan siapa saja — termasuk guru atau staf TU — memverifikasi bahwa fitur
bekerja sesuai harapan sebelum digunakan di sekolah.

### XII. Bahasa Penjelasan = Bahasa Awam (NON-NEGOTIABLE)

Semua dokumentasi teknis, penjelasan keputusan, dan pesan error HARUS dapat dipahami oleh
orang yang belum berpengalaman coding:

- Gunakan **analogi sehari-hari** untuk menjelaskan konsep teknis
  (contoh: "database seperti buku besar yang diisi komputer")
- **Hindari jargon** tanpa penjelasan langsung di tempat yang sama
- Pesan error di UI HARUS berisi: apa yang terjadi + apa yang harus dilakukan pengguna
  (bukan hanya kode error atau pesan teknis)
- Komentar kode BOLEH dalam Bahasa Indonesia untuk bagian yang kritis/kompleks

**Alasan**: Tim developer sedang belajar. Dokumentasi dan pesan yang terlalu teknis
memperlambat onboarding dan membuat debug mandiri lebih sulit. Investasi di kejelasan
bahasa menghemat waktu komunikasi jangka panjang.

---

## Batasan Status Kehadiran

Status kehadiran yang sah hanya yang tercantum di **Prinsip IV** di atas.
Bagian ini mendefinisikan aturan transisi status yang berlaku untuk seluruh sistem:

- Status `MENUNGGU_PENGESAHAN` adalah status sementara; sistem HARUS menetapkan
  status final dalam waktu paling lama 1 × 24 jam (atau ditolak menjadi `ALPA`).
- Status `ALPA` ditetapkan secara otomatis oleh sistem (Laravel Scheduler / cron) untuk
  siswa yang tidak absen masuk pada hari sekolah aktif tanpa keterangan izin/sakit.
- Status `TIDAK_ABSEN_PULANG` ditetapkan otomatis oleh sistem untuk siswa yang absen
  masuk tapi tidak absen pulang setelah waktu pulang + toleransi terlewati.
- Hanya admin dan guru (wali kelas) yang boleh mengubah status secara manual, dan
  setiap perubahan WAJIB mencatat audit trail (Prinsip V).

---

## Stack & Arsitektur Teknis

### Teknologi Wajib

Lihat **Prinsip IX** untuk daftar stack resmi. Berikut panduan implementasi tambahan:

- **Laravel 13** digunakan sebagai satu-satunya framework backend; routing, controller,
  dan middleware WAJIB mengikuti konvensi Laravel.
- **Laravel Breeze** (starter kit resmi) digunakan untuk autentikasi; jangan membangun
  sistem login dari nol.
- **Blade** adalah satu-satunya template engine; jangan mencampur dengan Livewire atau
  Vue/React kecuali ada keputusan amandemen konstitusi.
- **Tailwind CSS** dikompilasi via **Vite** (sudah termasuk dalam setup Laravel baru);
  jangan menggunakan CDN Tailwind untuk production.
- **SQLite** digunakan sebagai database default untuk development dan production awal.
  Skema database dikelola sepenuhnya via **Laravel Migrations** — tidak boleh mengubah
  skema secara manual langsung di file database.
- **Eloquent ORM** digunakan sebagai satu-satunya cara akses database; query raw SQL
  HANYA boleh digunakan jika Eloquent tidak mendukung operasi tersebut.

### Konvensi Laravel yang Wajib Diikuti

- Penamaan model: `PascalCase` tunggal (contoh: `User`, `Siswa`, `Kehadiran`)
- Penamaan tabel database: `snake_case` jamak (contoh: `users`, `siswas`, `kehadirans`)
- Penamaan controller: `PascalCase` + `Controller` (contoh: `LoginController`)
- Penamaan file Blade: `kebab-case` (contoh: `dashboard.blade.php`, `login.blade.php`)
- Nama variabel dan fungsi PHP: `camelCase` (sesuai konvensi Laravel/PHP)
- Komentar dan pesan UI: Bahasa Indonesia

### Referensi Utama

- PRD: `docs/prd.md` — sumber fitur dan aturan bisnis
- Skema DB: `database/migrations/` — definisi tabel via Laravel Migrations
- Konstitusi: `.specify/memory/constitution.md` (dokumen ini)

---

## Alur Pengembangan & Kualitas

### Urutan Kerja per Fitur

Setiap fitur baru HARUS mengikuti urutan berikut:

1. Verifikasi fitur ada di PRD (ada nomor US-nya)
2. Jalankan `/speckit-specify` untuk membuat spec
3. Jalankan `/speckit-plan` untuk membuat rencana implementasi
4. Jalankan `/speckit-tasks` untuk memecah menjadi task
5. Jalankan `/speckit-implement` untuk mengerjakan task
6. Tulis langkah pengujian manual (Prinsip XI)
7. Demo ke pemangku kepentingan sebelum lanjut

### Aturan Code Review

- Setiap perubahan pada logika status kehadiran WAJIB direview terhadap Prinsip III dan IV.
- Setiap perubahan pada alur absen (masuk/pulang) WAJIB direview terhadap Prinsip VI (R1–R8, F1–F6).
- Perubahan yang menambah timestamp dari sisi klien WAJIB ditolak (Prinsip II).
- Semua teks yang tampil di UI WAJIB dalam Bahasa Indonesia (Prinsip IX).

---

## Governance

Konstitusi ini adalah dokumen tata kelola tertinggi proyek SIAP KNBI.

### Hierarki Dokumen

```
docs/prd.md  (aturan bisnis — menang jika konflik dengan konstitusi)
      ↓
.specify/memory/constitution.md  (prinsip teknis & tata kelola)
      ↓
Spec per fitur (.specify/specs/)
      ↓
Rencana implementasi (.specify/plans/)
      ↓
Task list (.specify/tasks/)
      ↓
Kode aplikasi
```

### Prosedur Amandemen

1. Ajukan perubahan dengan menyebut prinsip yang diubah dan alasannya.
2. Jika perubahan menyentuh aturan bisnis (status kehadiran, aturan waktu, dll.),
   PRD HARUS diperbarui terlebih dahulu.
3. Jalankan `/speckit-constitution` dengan deskripsi perubahan untuk memperbarui dokumen ini.
4. Bump versi sesuai aturan semantic versioning:
   - **MAJOR**: Prinsip dihapus atau didefinisi ulang secara fundamental (contoh: ganti stack).
   - **MINOR**: Prinsip baru ditambahkan atau diperluas secara material.
   - **PATCH**: Klarifikasi, perbaikan typo, penjelasan tambahan tanpa perubahan makna.
5. Catat perubahan dalam Sync Impact Report (komentar HTML di atas file) sebelum commit.

### Compliance Review

- Setiap sprint atau milestone, lakukan review: apakah spec dan kode yang ada sudah sesuai konstitusi?
- Prinsip yang dilanggar HARUS diperbaiki sebelum fitur dianggap selesai.
- Gunakan file ini sebagai checklist saat melakukan code review.

### Ketentuan Tambahan

- Konstitusi ini tidak mengatur detail implementasi teknis spesifik (itu urusan spec dan plan).
- Konstitusi ini TIDAK BOLEH diubah hanya karena sulit secara teknis; kesulitan teknis harus
  diselesaikan dengan solusi teknis, bukan dengan melonggarkan prinsip.

---

**Version**: 2.0.0 | **Ratified**: 2026-11-09 | **Last Amended**: 2026-11-09
