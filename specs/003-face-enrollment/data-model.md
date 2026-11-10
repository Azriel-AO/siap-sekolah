# Data Model: Pendaftaran Mandiri Foto Wajah (Face Enrollment)

**Feature**: `003-face-enrollment` | **Date**: 2026-11-10

---

## 1. Tabel: `face_enrollments` (BARU)

Satu pendaftaran wajah = satu baris. Satu siswa maksimal satu baris (unique).
Mewakili kesatuan 3 foto referensi + metadata persetujuan dan waktu (server).

| Kolom | Tipe | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint unsigned` | ❌ | Primary Key |
| `student_id` | `bigint unsigned` | ❌ | FK ke `students.id`, **UNIQUE** (FR-006: satu pendaftaran per siswa) |
| `photo_1_path` | `string(120)` | ❌ | Path relatif file foto ke-1 di disk privat `face-photos/` |
| `photo_2_path` | `string(120)` | ❌ | Path relatif file foto ke-2 |
| `photo_3_path` | `string(120)` | ❌ | Path relatif file foto ke-3 |
| `consented_at` | `timestamp` | ❌ | Waktu server saat siswa menandai pernyataan persetujuan orang tua/wali (FR-012) |
| `last_reenrolled_at` | `timestamp` | ✅ | Waktu server daftar ulang terakhir; `null` = belum pernah daftar ulang (FR-014) |
| `timestamps` | `timestamp` | ✅ | `created_at` = waktu pendaftaran pertama (`enrolled_at`); `updated_at` = perubahan terakhir |

> **Aturan validitas status "Terdaftar"** (dihitung, bukan kolom): baris ada
> **dan** ketiga path terisi **dan** ketiga file ada di disk (R3 research.md).

> **Aturan penamaan file**: `face-photos/{student_id}/face-{student_id}-{slot}-{YmdHis}.jpg`
> (R1 research.md). Slot ∈ {1,2,3}.

### Aturan transisi state (sederhana)

```
[Tidak ada baris]  --POST simpan (3 foto + persetujuan)-->  [Terdaftar]
[Terdaftar]        --POST simpan ulang (daftar ulang)------>  [Terdaftar]
                    (hapus 3 file lama, timpa kolom path,
                     last_reenrolled_at = now, consented_at = now)
```

- Tidak ada state lain (tidak ada "draft" — penyimpanan all-or-nothing, FR-005).
- Tidak ada penghapusan mandiri oleh siswa selain lewat daftar ulang.

---

## 2. Relasi Eloquent

| Model | Relasi | Keterangan |
|---|---|---|
| `FaceEnrollment` | `belongsTo(Student)` | Pemilik pendaftaran |
| `Student` | `hasOne(FaceEnrollment)` | Paling banyak satu (dijaga `unique` di DB) |

> Tabel `students`, `school_classes`, `attendances`, `rfid_cards` **tidak diubah**
> (FR-013: alur kiosk & kehadiran tidak tersentuh).

---

## 3. Modifikasi minimal pada data yang sudah ada

| Berkas | Perubahan |
|---|---|
| `app/Models/Student.php` | Tambah method relasi `faceEnrollment(): HasOne` |
| `app/Http/Controllers/TeacherAttendanceController.php` | Tambah field JSON `face` per siswa (`TERDAFTAR`/`BELUM`, dihitung ala R3) |
| `resources/views/teacher/attendance-today.blade.php` | Tambah kolom badge "Wajah" |
| `resources/views/layouts/navigation.blade.php` | Menu "Pendaftaran Wajah" untuk peran siswa |
| `resources/views/dashboard.blade.php` | Kartu menu "Pendaftaran Wajah" untuk peran siswa |

---

## 4. Aturan validasi (ringkasan untuk implementasi)

| Input | Aturan |
|---|---|
| `photos` (array 3 item) | Wajib tepat 3; tiap item `string` awalan `data:image/jpeg;base64,`; decode strict; binary 100 B–500 KB |
| `consent` | Wajib `true` (checkbox pernyataan); tanpa ini simpan ditolak |
| `student_id` | Tidak diterima dari klien — selalu dari akun siswa yang login |
| Akses | Halaman/status/simpan hanya peran `siswa`; guru/admin → 403; tamu → redirect login |
