# Tasks: Pendaftaran Mandiri Foto Wajah (Face Enrollment)

**Input**: Design documents from `specs/003-face-enrollment/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/routes.md`, `quickstart.md`
**Stack**: Laravel 13 + Blade + Alpine.js + Tailwind CSS (Vite) + SQLite
**Batasan keras**: Tanpa pengenalan/pencocokan wajah (FR-011); `KioskController`, logika status/waktu, dan tabel `attendances` TIDAK boleh tersentuh (FR-013).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Dapat dikerjakan paralel (file berbeda, tanpa dependensi)
- **[Story]**: User story dari `spec.md` (`[US1]`, `[US2]`, `[US3]`)
- Setiap task menyertakan path file lengkap
- **Konvensi repo**: COMMIT setelah tiap task selesai; UI & pesan error Bahasa Indonesia

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Pastikan lingkungan dasar siap.

- [x] T001 Verifikasi baseline: jalankan `php artisan migrate:fresh --seed` (DB demo 10 siswa X TKJ 1 terisi) dan pastikan `php artisan test tests/Feature/KioskAttendanceTest.php` hijau sebelum mulai (14 tests)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Skema + model yang dibutuhkan semua user story.

- [x] T002 Buat migration `create_face_enrollments_table` di `database/migrations/` dengan kolom persis sesuai `data-model.md`: `student_id` FK `students.id` cascade + **UNIQUE**, `photo_1_path`/`photo_2_path`/`photo_3_path` `string(120)` **NOT NULL**, `consented_at` timestamp **NOT NULL**, `last_reenrolled_at` timestamp **NULL**, `timestamps`; jalankan `php artisan migrate`
- [x] T003 [P] Buat model `App\Models\FaceEnrollment` di `app/Models/FaceEnrollment.php`: `$fillable` semua kolom, cast `consented_at` & `last_reenrolled_at` = `datetime`, relasi `student(): BelongsTo`
- [x] T004 Tambah relasi `faceEnrollment(): HasOne` di `app/Models/Student.php` (satu siswa maksimal satu pendaftaran, dijamin UNIQUE di DB)

**Checkpoint**: Tabel + relasi Eloquent siap — pengerjaan user story dapat dimulai.

---

## Phase 3: User Story 1 - Siswa Mendaftarkan Wajah Sendiri (Priority: P1) 🎯 MVP

**Goal**: Siswa login → menu "Pendaftaran Wajah" → persetujuan → kamera (gagal-aman) → 3 foto dengan countdown 3 detik + ulang per slot → simpan → status "Terdaftar".

**Independent Test**: Login `siswa@knbi.sch.id` → daftar 3 foto → simpan → baris `face_enrollments` milik Raka Pratama ada di DB dengan 3 file di disk privat (Skenario 1–3 `quickstart.md`).

- [x] T005 [P] [US1] Daftarkan 3 route di `routes/web.php` dalam grup `auth`+`prevent-back-history` yang sudah ada: `GET /siswa/pendaftaran-wajah` → `siswa.wajah`, `GET /siswa/pendaftaran-wajah/status` → `siswa.wajah.status`, `POST /siswa/pendaftaran-wajah` → `siswa.wajah.simpan` (kontrak: `contracts/routes.md` §1–3)
- [x] T006 [US1] Buat `app/Http/Controllers/FaceEnrollmentController.php`: `show()` (tolak non-siswa 403 "Halaman ini hanya untuk siswa.", muat status pendaftaran milik siswa login), `status()` (JSON `terdaftar`, `status_label`, `tanggal_daftar`, `tanggal_daftar_ulang`, `photos` embedded base64 — hanya milik siswa login), `store()` (identitas dari sesi — klien TIDAK mengirim `student_id`; validasi `consent=true` wajib + tepat 3 data URL JPEG awalan `data:image/jpeg;base64,`, decode strict, binary 100 B–500 KB per foto; simpan ke disk `local` path `face-photos/{student_id}/face-{student_id}-{slot}-{YmdHis}.jpg`; waktu dari server; simpan all-or-nothing — validasi gagal = 422 pesan Indonesia, tanpa file nyasar)
- [x] T007 [US1] Buat `resources/views/face-enrollment/show.blade.php` (Alpine.js, pola kiosk `kiosk/show.blade.php`): kartu pernyataan izin orang tua/wali (checkbox wajib, kamera belum menyala sebelum centang), pratinjau `<video autoplay muted playsinline>` gagal-aman (tanpa kamera/izin ditolak → pratinjau disembunyikan + pesan Indonesia cara mengizinkan, tanpa error), tombol "Ambil Foto" dengan hitungan mundur 3→2→1 (`setInterval`) lalu tangkap frame `canvas` → `toDataURL('image/jpeg', 0.7)`, 3 slot foto dengan tombol "Ulangi" per slot (belum disimpan), tombol "Simpan Pendaftaran" (kirim `consent` + 3 foto ke `siswa.wajah.simpan`, tampilkan pesan hasil), matikan kamera (`track.stop()`) saat `beforeunload` dan setelah 3 slot terisi
- [x] T008 [P] [US1] Tautkan menu "Pendaftaran Wajah" khusus peran siswa di `resources/views/layouts/navigation.blade.php` (desktop + mobile) dan kartu menu di `resources/views/dashboard.blade.php` (pola menu "Riwayat Absensi" yang sudah ada)
- [x] T009 [US1] Buat `tests/Feature/FaceEnrollmentTest.php` (pola `KioskAttendanceTest.php`, `RefreshDatabase` + `Storage::fake('local')`): halaman 200 untuk siswa / 403 guru / redirect tamu; simpan 3 foto valid → sukses + baris DB + 3 file ada; simpan 2 foto → 422 pesan Indonesia; tanpa `consent` → 422; foto bukan JPEG sah → ditolak tanpa file tersimpan

**Checkpoint**: MVP Face Enrollment hidup — siswa dapat mendaftar mandiri dan datanya masuk DB.

---

## Phase 4: User Story 2 - Status & Daftar Ulang (Priority: P2)

**Goal**: Siswa terdaftar melihat status + tanggal + pratinjau; bisa daftar ulang (foto lama dihapus, diganti baru, dengan konfirmasi; batal = aman).

**Independent Test**: Siswa yang sudah terdaftar membuka menu → status "Terdaftar" + tanggal → "Daftar Ulang" + Batal (data utuh) → "Daftar Ulang" + Ya → 3 foto baru → simpan → 1 baris DB dengan path baru, file lama hilang dari storage (Skenario 4 `quickstart.md`).

- [x] T010 [US2] Perluas `store()` di `app/Http/Controllers/FaceEnrollmentController.php` untuk daftar ulang: bila baris pendaftaran sudah ada → hapus 3 file lama (`Storage::disk('local')->delete`), timpa ketiga path dengan file baru, set `last_reenrolled_at` = now dan `consented_at` = now (pernyataan tampil lagi); hasil akhir tetap TEPAT SATU baris per siswa (tanpa jendela tanpa-pendaftaran — penghapusan dan penyimpanan dalam satu operasi)
- [x] T011 [US2] Tambahkan tampilan status di `resources/views/face-enrollment/show.blade.php`: bila terdaftar → kartu status "Terdaftar" + tanggal pendaftaran (`translatedFormat('l, d F Y H.i')` dari server) + tanggal daftar ulang terakhir (bila ada) + pratinjau 3 foto embedded miliknya + tombol "Daftar Ulang" dengan dialog konfirmasi; **Batal** = tidak ada perubahan apa pun; setelah "Ya" → tampilkan lagi kartu persetujuan + alur 3 foto baru
- [x] T012 [P] [US2] Tambah kasus di `tests/Feature/FaceEnrollmentTest.php`: POST ulang oleh siswa terdaftar → tetap 1 baris (`assertDatabaseCount`), file lama terhapus dari fake storage & file baru ada, `last_reenrolled_at` terisi; status endpoint mengembalikan `terdaftar: true` + `photos` 3 item milik si empunya

**Checkpoint**: Siklus daftar-awal → lihat status → daftar ulang berfungsi utuh.

---

## Phase 5: User Story 3 - Badge "Wajah" di Rekap Guru (Priority: P2)

**Goal**: Guru/admin melihat badge "Wajah: Terdaftar/Belum" per siswa di rekap harian, ikut terbarui polling 4 detik.

**Independent Test**: Login guru → Rekap Harian → kolom "Wajah": Raka Pratama "Terdaftar" (setelah enroll), siswa lain "Belum" (Skenario 5 `quickstart.md`).

- [x] T013 [US3] Tambah field `face` di `app/Http/Controllers/TeacherAttendanceController.php` (`getAttendanceData`): eager-load `faceEnrollment` (hindari N+1); nilai `"TERDAFTAR"` bila baris ada **dan** ketiga path terisi **dan** ketiga file ada di disk (`Storage::disk('local')->exists`), selain itu `"BELUM"` (status dihitung, bukan kolom — R3 `research.md`)
- [x] T014 [US3] Tambah kolom "Wajah" di `resources/views/teacher/attendance-today.blade.php`: badge hijau "Terdaftar" / abu-abu "Belum" per baris (pola badge status kehadiran yang sudah ada), dirender Alpine dari field `s.face` sehingga ikut terbarui polling tanpa reload
- [x] T015 [P] [US3] Tambah kasus di `tests/Feature/FaceEnrollmentTest.php`: endpoint `GET /guru/rekap-harian/data` (login guru) mengembalikan `face: "TERDAFTAR"` untuk siswa terdaftar dan `"BELUM"` untuk siswa lain; baris pendaftaran tanpa file fisik dianggap `"BELUM"` (kasus file hilang)

**Checkpoint**: Guru dapat memantau kesiapan Face ID seluruh kelas dari rekap.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Dokumentasi + verifikasi akhir lintas story.

- [x] T016 Perbarui `README.md`: bagian "Panduan Uji Pendaftaran Wajah" ramah pemula — prasyarat kamera, cara mengizinkan kamera per browser, alur 3 foto + countdown, daftar ulang, badge guru, privasi foto, dan catatan "bukan pengenalan wajah" (rujuk `quickstart.md`)
- [x] T017 Validasi akhir: jalankan seluruh skenario `specs/003-face-enrollment/quickstart.md` (1–8) secara manual + `php artisan test` (KioskAttendanceTest 14 + FaceEnrollmentTest semua hijau) + pastikan `git diff` menunjukkan `app/Http/Controllers/KioskController.php` dan tabel `attendances` TIDAK berubah (regresi FR-013)

---

## Dependencies & Execution Order

```
Phase 1: Setup (T001)
   ↓
Phase 2: Foundation (T002 → T003 [P] → T004) [BLOKIR semua US]
   ↓
Phase 3: US1 Pendaftaran Mandiri (T005 [P] → T006 → T007 → T008 [P] → T009)  ← MVP
   ↓
Phase 4: US2 Status & Daftar Ulang (T010 → T011 → T012 [P])
   ↓
Phase 5: US3 Badge Guru (T013 → T014 → T015 [P])
   ↓
Phase 6: Polish (T016 → T017)
```

### User Story Dependencies
- **US1**: hanya butuh Phase 2 — MVP mandiri.
- **US2**: membangun di atas `store()`/view US1 (file sama, kerjakan setelah US1).
- **US3**: hanya butuh Phase 2 (tabel + relasi); tidak menyentuh file US1/US2 → dapat dikerjakan paralel oleh orang lain setelah Phase 2, tetapi urutan rekomendasi setelah US1 agar badge dapat diuji dengan data nyata.

### Parallel Opportunities
- T003 (model baru) paralel dengan T005 (routes) — file berbeda.
- T008 (navigation + dashboard) paralel dengan T006/T007.
- T012, T015 (tambahan test) paralel terhadap task view/controller story lain di file berbeda.

---

## Implementation Strategy

### MVP First (User Story 1 saja)
1. Selesaikan Phase 1–2 → 3. Stop & validasi US1 (Skenario 1–3 quickstart).
2. Demo mungkin: siswa mendaftar wajah, data masuk DB.

### Incremental Delivery
1. US1 → uji → commit per task.
2. US2 (status + daftar ulang) → uji.
3. US3 (badge guru) → uji.
4. Polish: README + validasi penuh + cek regresi kiosk.

---

## Notes
- Semua pesan UI/error Bahasa Indonesia sederhana (Konstitusi IX & XII).
- Waktu (`consented_at`, `last_reenrolled_at`, `created_at`) SELALU dari server (Konstitusi II).
- Foto hanya embedded di halaman ber-login — TIDAK membuat endpoint foto publik (R4 `research.md`).
- DILARANG menambah logika pengenalan/cocok wajah atau menyentuh alur kiosk (FR-011, FR-013).
- Setelah semua task selesai: centang task di file ini (pola spec 002).

---

## Catatan Verifikasi T017 (2026-11-10, jujur & apa adanya)

- **Uji otomatis**: `KioskAttendanceTest` 14 hijau; `FaceEnrollmentTest` 11 hijau (25/25). Perintah `php artisan test` (seluruh suite) menampilkan 18 kegagalan AKAN TETAPI semuanya dari tes bawaan Breeze yang memang tidak relevan dan sudah rusak sebelum fitur ini (rute `verification.*`, `profile.*`, `register`, `forgot-password` dinonaktifkan/dialihkan di `routes/auth.php`). Tidak ada kegagalan dari fitur ini.
- **`migrate:fresh --seed` (langkah persiapan quickstart) TIDAK dijalankan** — sesuai instruksi sesi: migrasi `face_enrollments` sudah dibuat & dijalankan (batch 2), DB demo tetap utuh. Baseline diverifikasi dengan tes (14/14 hijau) dan `migrate:status`.
- **Skenario quickstart 1 & 2 (alur kamera bahagia + ulang foto)**: TIDAK bisa diklik penuh di browser headless tanpa webcam; diverifikasi lewat tes otomatis (simpan 3 foto valid, all-or-nothing, tanpa file nyasar, daftar ulang 1 baris) dan pemeriksaan UI via browser (halaman render, tombol & slot ada).
- **Skenario 6 (gagal-aman tanpa kamera)**: diverifikasi manual di browser headless — centang pernyataan → kamera gagal → pesan Indonesia "Kamera tidak dapat dipakai" tampil, halaman utuh, console bersih.
- **Skenario 4 (daftar ulang)**: diverifikasi via tes (`test_daftar_ulang_mengganti_foto_lama_dan_tetap_satu_baris`, file lama terhapus, `last_reenrolled_at` terisi) + UI dialog Batal/Ya diperiksa.
- **Skenario 5 (badge guru)**: kolom `WAJAH` tampil di rekap (diverifikasi via browser, semua "Belum" karena DB demo belum ada pendaftaran nyata); kasus TERDAFTAR/BELUM/file-hilang diverifikasi via tes.
- **Skenario 7 (privasi)**: diverifikasi via browser — siswa → rekap = 403; guru → pendaftaran-wajah = 403; tamu → redirect `/login`. Foto hanya base64 embedded di halaman ber-login.
- **Skenario 8 (regresi kiosk)**: `KioskController.php` & tabel/logika `attendances` TIDAK disentuh oleh 15 commit T002–T016 (diverifikasi `git log --stat`/`git diff`); halaman kiosk dibuka via browser dan berfungsi normal.
- Demo DB sengaja TIDAK diisi pendaftaran wajah palsu; untuk melihat badge "Terdaftar" di rekap demo sungguhan, jalankan alur Skenario 20–23 di README dengan kamera.
