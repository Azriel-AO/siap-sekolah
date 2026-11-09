# Data Model: Milestone DEMO Absensi Kiosk

**Feature**: `002-demo-absensi-kiosk` | **Date**: 2026-11-09

---

## 1. Tabel: `school_classes`
Menyimpan identitas kelas dan jadwal resmi.

| Kolom | Tipe | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint unsigned` | ❌ | Primary Key |
| `name` | `string(50)` | ❌ | Nama kelas, contoh: "X TKJ 1" |
| `slug` | `string(50)` | ❌ | Slug unik URL, contoh: "X-TKJ-1" |
| `department` | `string(50)` | ❌ | Jurusan, contoh: "Teknik Komputer & Jaringan" |
| `entry_time` | `time` | ❌ | Batas masuk tepat waktu (default `07:00:00`) |
| `exit_time` | `time` | ❌ | Jadwal kepulangan resmi (default `15:30:00`) |
| `timestamps` | `timestamp` | ✅ | Created/updated at |

---

## 2. Tabel: `students`
Menyimpan data identitas siswa.

| Kolom | Tipe | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint unsigned` | ❌ | Primary Key |
| `school_class_id`| `bigint unsigned` | ❌ | FK ke `school_classes.id` |
| `user_id` | `bigint unsigned` | ✅ | FK ke `users.id` (untuk login siswa mandiri) |
| `nis` | `string(20)` | ❌ | Nomor Induk Siswa (unik) |
| `name` | `string(100)` | ❌ | Nama lengkap siswa |
| `gender` | `string(1)` | ❌ | L / P |
| `timestamps` | `timestamp` | ✅ | Created/updated at |

---

## 3. Tabel: `rfid_cards`
Menyimpan UID kartu pelajar RFID yang terikat ke siswa.

| Kolom | Tipe | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint unsigned` | ❌ | Primary Key |
| `student_id` | `bigint unsigned` | ❌ | FK ke `students.id` (unik: 1 kartu aktif per siswa) |
| `card_uid` | `string(50)` | ❌ | UID fisik kartu (unik) |
| `is_active` | `boolean` | ❌ | Default `true` |
| `timestamps` | `timestamp` | ✅ | Created/updated at |

---

## 4. Tabel: `attendances`
Menyimpan rekaman absensi harian per siswa.

| Kolom | Tipe | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint unsigned` | ❌ | Primary Key |
| `student_id` | `bigint unsigned` | ❌ | FK ke `students.id` |
| `school_class_id`| `bigint unsigned` | ❌ | FK ke `school_classes.id` |
| `date` | `date` | ❌ | Tanggal absen (YYYY-MM-DD) |
| `check_in_at` | `time` | ✅ | Waktu tap masuk (server) |
| `check_out_at`| `time` | ✅ | Waktu tap pulang (server) |
| `status` | `string(30)` | ❌ | `HADIR`, `TERLAMBAT`, `ALPA`, `PULANG_CEPAT` |
| `last_tap_at` | `timestamp` | ✅ | Timestamp tap terakhir (untuk anti-double-tap) |
| `kiosk_device`| `string(50)` | ✅ | ID atau slug kiosk pengabsen |
| `timestamps` | `timestamp` | ✅ | Created/updated at |

> **Unique constraint**: `['student_id', 'date']` (1 record kehadiran per siswa per hari).
