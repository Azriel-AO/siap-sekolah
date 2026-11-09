# Research: Milestone DEMO Absensi Kiosk RFID

**Feature**: `002-demo-absensi-kiosk` | **Date**: 2026-11-09

---

## 1. Input Tap RFID di Kiosk
- **Decision**: Form HTML standar dengan `input autofocus` yang menangkap ketikan UID dan tombol Enter (perilaku standar reader USB RFID).
- **Rationale**: Hardware reader RFID USB bekerja sebagai keyboard emulator (HID keyboard). Mengirim string UID diakhiri Enter. Mengetik manual di browser bekerja persis sama dengan reader fisik.
- **Alternative**: WebUSB API / serial port (ditolak: rumit, butuh browser khusus & driver).

---

## 2. Jam Server & Aturan Masuk/Pulang
- **Decision**: Waktu tap diambil dari `Carbon::now('Asia/Jakarta')`.
- **Status Masuk**: Jika jam server $\le$ 07.00 WIB $\rightarrow$ `HADIR`; jika $>$ 07.00 WIB $\rightarrow$ `TERLAMBAT`.
- **Absen Pulang**: Jika siswa sudah absen masuk pada hari yang sama, tap berikutnya mencatat `jam_pulang`.
- **Anti-Double-Tap**: Jika tap terjadi dalam selang $\le$ 60 detik dari tap terakhir kartu yang sama, kembalikan respon pemberitahuan tanpa membuat record ganda (PRD R3).

---

## 3. Real-Time Rekap Guru (Polling)
- **Decision**: Polling JSON via `fetch()` setiap 4 detik menggunakan JavaScript / Alpine.js bawaan Breeze.
- **Rationale**: Paling stabil, tanpa server WebSocket tambahan (Pusher/Reverb), langsung jalan di SQLite lokal, hemat kuota dan waktu pengembangan.
- **Alternative**: Laravel Reverb / WebSockets (ditolak: butuh worker daemon terpisah, rawan kendala port di PC sekolah).

---

## 4. Kiosk Terikat Kelas
- **Decision**: URL Kiosk `/kiosk/{class_slug}` (misal `/kiosk/X-TKJ-1`). Kiosk memeriksa apakah `rfid_card->student->school_class_id` cocok dengan kelas Kiosk.
- **Rationale**: Memenuhi aturan Konstitusi VII & PRD R2 secara harfiah.
