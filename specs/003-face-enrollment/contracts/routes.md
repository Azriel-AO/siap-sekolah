# Contracts: Routes & Payload — Pendaftaran Wajah

**Feature**: `003-face-enrollment` | **Date**: 2026-11-10

Semua respons error berbahasa Indonesia; semua waktu dari server (Konstitusi II).

---

## 1. Halaman Pendaftaran (web, HTML)

```http
GET /siswa/pendaftaran-wajah
```

- **Middleware**: `auth`, `prevent-back-history` — hanya peran **siswa** (guru/admin → 403 "Halaman ini hanya untuk siswa.")
- **Nama route**: `siswa.wajah`
- **View**: `face-enrollment/show`
- **Konten halaman**: pernyataan persetujuan (jika belum terdaftar / saat daftar ulang), area kamera, 3 slot foto, tombol ambil (countdown 3 detik), tombol simpan; bila sudah terdaftar → status "Terdaftar", tanggal pendaftaran (dari server), tanggal daftar ulang terakhir (bila ada), pratinjau 3 foto embedded (base64), tombol "Daftar Ulang" (dialog konfirmasi).
- **Gagal-aman kamera**: perangkat tidak ada / izin ditolak / tidak didukung → pratinjau disembunyikan + pesan cara mengizinkan kamera; halaman tetap utuh.

## 2. Status Pendaftaran (JSON, untuk pembaruan halaman tanpa reload)

```http
GET /siswa/pendaftaran-wajah/status
```

- **Middleware**: sama dengan halaman; hanya peran siswa; hanya data milik siswa login
- **Nama route**: `siswa.wajah.status`
- **Respons 200**:

```json
{
  "terdaftar": true,
  "status_label": "Terdaftar",
  "tanggal_daftar": "Senin, 09 November 2026 14.05",
  "tanggal_daftar_ulang": null,
  "photos": ["data:image/jpeg;base64,...", "...", "..."],
  "message": "Wajah Anda sudah terdaftar."
}
```

- `terdaftar: false` bila belum ada pendaftaran (atau pendaftaran tidak valid ala R3); `photos` berupa `[]`.

## 3. Simpan / Daftar Ulang (JSON)

```http
POST /siswa/pendaftaran-wajah
Content-Type: application/json
X-CSRF-TOKEN: ...
```

- **Middleware**: sama; hanya peran siswa; identitas siswa **selalu** dari sesi login — klien tidak mengirim `student_id`
- **Nama route**: `siswa.wajah.simpan`
- **Body**:

```json
{
  "consent": true,
  "photos": ["data:image/jpeg;base64,...", "data:image/jpeg;base64,...", "data:image/jpeg;base64,..."]
}
```

- **Aturan** (FR-003 s.d. FR-006, FR-012, FR-015): `consent` wajib `true`; `photos` wajib tepat 3 data URL JPEG sah (decode strict, binary 100 B–500 KB). Bila sudah ada pendaftaran → **daftar ulang**: hapus 3 file lama, timpa path, `last_reenrolled_at` & `consented_at` = waktu server.
- **Respons 200 (sukses)**:

```json
{
  "success": true,
  "status_label": "Terdaftar",
  "message": "Pendaftaran wajah berhasil disimpan.",
  "tanggal_daftar": "Senin, 09 November 2026 14.05",
  "tanggal_daftar_ulang": "Selasa, 10 November 2026 09.12"
}
```

- **Respons 422 (validasi gagal)** — contoh:

```json
{ "success": false, "message": "Foto wajah wajib lengkap 3 foto. Saat ini baru 2 foto sah." }
```

- **Respons 422 (tanpa persetujuan)**:

```json
{ "success": false, "message": "Anda harus menyetujui pernyataan izin orang tua/wali sebelum menyimpan." }
```

## 4. Badge di Rekap Guru (endpoint yang SUDAH ADA — diperluas)

```http
GET /guru/rekap-harian/data
```

- **Middleware**: `auth` + cek guru/admin (sudah ada, milestone kamera)
- **Perubahan kontrak**: tiap item `students[]` mendapat field baru:

```json
{ "id": 9, "nis": "10240109", "name": "Hendra Wijaya", "face": "TERDAFTAR" }
```

- `face`: `"TERDAFTAR"` bila baris pendaftaran valid (R3), selain itu `"BELUM"`.
- View rekap menampilkan badge "Wajah: Terdaftar" (hijau) / "Wajah: Belum" (abu).

## 5. Yang TIDAK berubah (jaminan FR-013)

- `GET/POST /kiosk/{slug}/tap` — tidak tersentuh
- Tabel & logika `attendances`, status kehadiran, cooldown, jam server kiosk — tidak tersentuh
- `GET /siswa/riwayat-absensi`, `GET /guru/rekap-harian` (halaman) — hanya penambahan field/kolom, tidak ada perubahan perilaku lama
