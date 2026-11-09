# Implementation Plan: Milestone DEMO — Absensi Kiosk RFID & Monitoring

**Branch**: `002-demo-absensi-kiosk` | **Date**: 2026-11-09 | **Spec**: [spec.md](spec.md)

---

## Summary

Membangun alur demo lengkap absensi berbasis kartu RFID untuk SMK Karya Nugraha (KNBI) dengan pola standar Laravel:
1. Model & Migration data: Kelas (`school_classes`), Siswa (`students`), Kartu RFID (`rfid_cards`), Kehadiran (`attendances`).
2. Seeder 1 kelas (X TKJ 1), 10 siswa dengan UID kartu unik, jadwal masuk 07.00 & pulang 15.30, dan catatan kehadiran sebagian hari ini.
3. Kiosk terikat kelas (`/kiosk/{class_slug}`) dengan form tap kartu autofocus, validasi jam server (R7), logika Hadir vs Terlambat, Absen Pulang, dan anti-double-tap cooldown (R3).
4. Dashboard guru rekap hari ini dengan polling otomatis via fetch JSON / Blade partial.
5. Halaman siswa untuk melihat riwayat absensi diri sendiri.

---

## Technical Context

- **Framework**: Laravel 13
- **Frontend**: Blade + Tailwind CSS (Vite) + Alpine.js (bawaan Breeze)
- **Database**: SQLite (`database/database.sqlite`)
- **Real-Time Polling**: Polling ringan via JavaScript `fetch()` interval 4-5 detik ke endpoint JSON rekap harian kelas
- **Waktu Server**: `now('Asia/Jakarta')` langsung dari PHP Carbon, klien tidak mengirim waktu

---

## Constitution Check

| Prinsip | Status | Catatan |
|---|---|---|
| I. Satu Sumber Kebenaran | ✅ LULUS | Mengacu pada US-14, US-21, US-22, US-31 dan PRD Bab 5.4 |
| II. Waktu Selalu dari Server | ✅ LULUS | Timestamp tap menggunakan jam server (R7) |
| III. Verifikasi ≠ Status | ✅ LULUS | RFID hanya membuktikan identitas, status ditentukan server |
| IV. Status Kehadiran Terbatas | ✅ LULUS | Hanya status baku: Hadir, Terlambat, Alpa, Pulang |
| V. Audit Trail | ✅ LULUS | Log tap mencatat timestamp server dan kiosk id |
| VI. R1–R8 & F1–F6 Harfiah | ✅ LULUS | R2 (kiosk kelas), R3 (cooldown 60s), R7 (jam server) |
| VII. Kiosk Terikat Kelas | ✅ LULUS | Kiosk memeriksa kecocokan siswa dengan kelas kiosk |
| VIII. Privasi Data | ✅ LULUS | Tidak ada Face ID / foto publik di milestone ini |
| IX. Stack Tetap | ✅ LULUS | Laravel 13 + Blade + Tailwind + SQLite |
| X. Sederhana Dulu | ✅ LULUS | Tidak ada kamera, WhatsApp, atau QR darurat |
| XI. Dapat Diuji Manusia | ✅ LULUS | Langkah uji manual jelas di quickstart & README |
| XII. Bahasa Awam | ✅ LULUS | Istilah sederhana, UI Bahasa Indonesia |

---

## Project Structure

```text
app/
├── Http/Controllers/
│   ├── KioskController.php         # Halaman Kiosk & proses tap RFID
│   ├── TeacherAttendanceController.php # Halaman rekap & endpoint polling JSON
│   └── StudentAttendanceController.php # Riwayat absensi siswa
└── Models/
    ├── SchoolClass.php             # Data kelas & jadwal
    ├── Student.php                 # Data siswa
    ├── RfidCard.php                # Pasangan kartu UID & siswa
    └── Attendance.php              # Catatan masuk & pulang

database/
├── migrations/
│   ├── xxxx_create_school_classes_table.php
│   ├── xxxx_create_students_table.php
│   ├── xxxx_create_rfid_cards_table.php
│   └── xxxx_create_attendances_table.php
└── seeders/
    └── DemoAttendanceSeeder.php    # Kelas X TKJ 1, 10 siswa, kehadiran hari ini

resources/views/
├── kiosk/
│   └── show.blade.php              # Tampilan kiosk layar besar autofocus
├── teacher/
│   └── attendance-today.blade.php  # Papan rekap absensi guru dengan polling
└── student/
    └── attendance-history.blade.php # Riwayat absensi siswa
```

---

## Complexity Tracking

Tidak ada pelanggaran konstitusi. Pola Laravel MVC standar.
