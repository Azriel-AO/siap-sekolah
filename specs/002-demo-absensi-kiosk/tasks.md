# Tasks: Milestone DEMO — Absensi Kiosk RFID & Monitoring

**Input**: Design documents from `specs/002-demo-absensi-kiosk/`
**Prerequisites**: `plan.md`, `spec.md`, `data-model.md`, `contracts/routes.md`, `quickstart.md`
**Stack**: Laravel 13 + Blade + Tailwind CSS + SQLite

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Dapat dikerjakan secara paralel (file berbeda, tanpa dependensi)
- **[Story]**: Mengacu ke User Story dari `spec.md` (`[US1]`, `[US2]`, `[US3]`, `[US4]`)
- Menyertakan path file lengkap pada setiap tugas

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Persiapan infrastruktur database dan dependensi bersama.

- [ ] T001 Verify database configuration and prepare demo namespace in `config/database.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Skema database, relasi Eloquent, dan data awal yang memblokir seluruh User Story.

**⚠️ CRITICAL**: Seluruh User Story bergantung pada selesainya tabel dan data seed pada fase ini.

- [ ] T002 Create migrations for `school_classes`, `students`, `rfid_cards`, and `attendances` in `database/migrations/`
- [ ] T003 [P] Create Eloquent models `SchoolClass`, `Student`, `RfidCard`, and `Attendance` with relationships in `app/Models/`
- [ ] T004 Create comprehensive demo seeder in `database/seeders/DemoAttendanceSeeder.php` with 1 class (X TKJ 1), 10 students, 10 RFID cards, and partial attendances for today
- [ ] T005 Register `DemoAttendanceSeeder` in `database/seeders/DatabaseSeeder.php` and run migration & seed via `php artisan migrate:fresh --seed`

**Checkpoint**: Database siap dan 10 data siswa kelas X TKJ 1 terisi — pengerjaan User Story dapat dimulai.

---

## Phase 3: User Story 1 - Tap Absen Masuk & Pulang di Kiosk Kelas (Priority: P1) 🎯 MVP

**Goal**: Siswa melakukan simulasi tap kartu (ketik UID) di Kiosk kelas X TKJ 1; sistem mencatat waktu server, menentukan status Hadir/Terlambat, dan menampilkan kartu konfirmasi visual berukuran besar.

**Independent Test**: Buka `/kiosk/X-TKJ-1`, ketik UID kartu siswa ke-9 (`CARD-TKJ-009`) lalu Enter. Layar menampilkan pop-up hijau/kuning berisi nama, jam, dan status kehadiran.

- [ ] T006 [US1] Implement Kiosk controller with tap logic, server timestamp, and status determination in `app/Http/Controllers/KioskController.php`
- [ ] T007 [P] [US1] Create full-screen kiosk interface with autofocus input and large status popup in `resources/views/kiosk/show.blade.php`
- [ ] T008 [US1] Register Kiosk routes (`GET /kiosk/{slug}` and `POST /kiosk/{slug}/tap`) in `routes/web.php`

**Checkpoint**: Alur tap Kiosk berfungsi mandiri (MVP Demo tercapai).

---

## Phase 4: User Story 2 - Pemantauan Rekap Kehadiran Harian Guru (Priority: P2)

**Goal**: Guru wali kelas memantau kehadiran 10 siswa hari ini melalui tabel rekap yang diperbarui otomatis tanpa reload.

**Independent Test**: Login sebagai guru di `/guru/rekap-harian`. Lakukan tap pada Kiosk di tab terpisah. Tabel rekap otomatis terbarui dalam $\le$ 4 detik.

- [ ] T009 [US2] Implement teacher attendance controller and polling data endpoint in `app/Http/Controllers/TeacherAttendanceController.php`
- [ ] T010 [P] [US2] Create teacher daily attendance board with automated JavaScript polling in `resources/views/teacher/attendance-today.blade.php`
- [ ] T011 [US2] Register teacher attendance routes (`GET /guru/rekap-harian` and `GET /guru/rekap-harian/data`) in `routes/web.php`

**Checkpoint**: Dashboard pemantauan guru sinkron dengan aktivitas Kiosk.

---

## Phase 5: User Story 3 - Riwayat Kehadiran Mandiri Siswa (Priority: P2)

**Goal**: Siswa dapat login dan melihat histori kehadiran serta jam masuk/pulang miliknya sendiri.

**Independent Test**: Login sebagai siswa (`siswa@knbi.sch.id`), buka `/siswa/riwayat-absensi`, pastikan hanya riwayat kehadiran milik siswa tersebut yang tampil.

- [ ] T012 [US3] Implement student attendance controller in `app/Http/Controllers/StudentAttendanceController.php`
- [ ] T013 [P] [US3] Create student personal attendance history view in `resources/views/student/attendance-history.blade.php`
- [ ] T014 [US3] Register student attendance route (`GET /siswa/riwayat-absensi`) in `routes/web.php`

**Checkpoint**: Transparansi data absensi ke siswa aktif.

---

## Phase 6: User Story 4 - Anti-Double Tap & Kiosk Terikat Kelas (Priority: P3)

**Goal**: Mencegah kecurangan tap ganda (cooldown 60 detik) dan memastikan kartu siswa hanya diterima pada kiosk kelasnya.

**Independent Test**: Tap kartu yang sama dua kali dalam 10 detik di Kiosk; tap kedua ditolak dengan notifikasi *"Kartu baru saja di-tap"*.

- [ ] T015 [US4] Add 60-second cooldown check and class-device matching validation in `app/Http/Controllers/KioskController.php`

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Integrasi menu navigasi, panduan pengujian di README, dan verifikasi akhir.

- [ ] T016 Link new menus from dashboard navigation in `resources/views/layouts/navigation.blade.php` and `resources/views/dashboard.blade.php`
- [ ] T017 Update `README.md` with step-by-step instructions for demonstrating Kiosk tap, teacher live rekap, and student history
- [ ] T018 Execute validation test cases per `specs/002-demo-absensi-kiosk/quickstart.md` and automated tests in `tests/Feature/KioskAttendanceTest.php`

---

## Dependencies & Execution Order

```
Phase 1: Setup (T001)
   ↓
Phase 2: Foundation (T002-T005) [BLOCKS US1-US4]
   ↓
Phase 3: US1 Kiosk Tap (T006-T008) [P1 - MVP]
   ↓
Phase 4: US2 Rekap Guru (T009-T011) [P2]
   ↓
Phase 5: US3 Riwayat Siswa (T012-T014) [P2]
   ↓
Phase 6: US4 Aturan Anti-Loophole (T015) [P3]
   ↓
Phase 7: Polish & Verification (T016-T018)
```
