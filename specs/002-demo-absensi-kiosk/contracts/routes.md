# Contracts: Routes (Milestone DEMO)

**Feature**: `002-demo-absensi-kiosk` | **Date**: 2026-11-09

---

## 1. Rute Kiosk (Layar Absensi Kelas)

| Method | URL | Middleware | Controller Action | Keterangan |
|---|---|---|---|---|
| `GET` | `/kiosk/{slug}` | `web` | `KioskController@show` | Layar Kiosk kelas X TKJ 1 (autofocus input) |
| `POST`| `/kiosk/{slug}/tap`| `web` | `KioskController@tap` | Menerima UID kartu, validasi jam server & simpan |

### Payload `POST /kiosk/{slug}/tap`
- Request: `{ card_uid: "CARD-TKJ-009" }`
- Response JSON:
  ```json
  {
    "success": true,
    "type": "check_in",
    "student_name": "Fajar Nugraha",
    "nis": "10240109",
    "status": "HADIR",
    "status_label": "Hadir Tepat Waktu",
    "time": "06:55:12",
    "message": "Selamat datang, Fajar Nugraha!"
  }
  ```

---

## 2. Rute Guru (Rekap Harian & Polling)

| Method | URL | Middleware | Controller Action | Keterangan |
|---|---|---|---|---|
| `GET` | `/guru/rekap-harian` | `auth` | `TeacherAttendanceController@index` | Papan rekap absensi hari ini kelas binaan |
| `GET` | `/guru/rekap-harian/data` | `auth` | `TeacherAttendanceController@data` | Endpoint JSON untuk polling auto-refresh |

---

## 3. Rute Siswa (Riwayat Absensi Pribadi)

| Method | URL | Middleware | Controller Action | Keterangan |
|---|---|---|---|---|
| `GET` | `/siswa/riwayat-absensi` | `auth` | `StudentAttendanceController@index` | Riwayat absensi diri sendiri |
