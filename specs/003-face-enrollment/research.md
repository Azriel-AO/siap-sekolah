# Research: Pendaftaran Mandiri Foto Wajah (Face Enrollment)

**Feature**: `003-face-enrollment` | **Date**: 2026-11-10

Semua keputusan di bawah menyelesaikan pilihan desain sebelum Phase 1. Tidak ada
NEEDS CLARIFICATION tersisa dari Technical Context (stack sudah dikunci Konstitusi IX).

---

## R1. Cara penyimpanan file foto & struktur folder

**Decision**: Simpan foto sebagai file JPEG di **disk `local`** (privat, root
`storage/app/private/`), folder `face-photos/{student_id}/`, nama file
`face-{student_id}-{slot}-{tanggal}{jam}.jpg`. Path relatif disimpan di kolom tabel.

**Rationale**: Mengikuti persis pola teruji `simpanFotoBukti()` di `KioskController`
(base64 → decode → `Storage::disk('local')->put()`). Disk `local` tidak bisa diakses
via URL publik (Konstitusi VIII, FR-007). Folder per siswa memudahkan penghapusan
massal saat daftar ulang.

**Alternatives considered**:
- Disk `public` + URL `/storage/...` — ditolak: foto wajah anak di bawah umur tidak
  boleh ekspos publik (Konstitusi VIII).
- Tabel database menyimpan binary BLOB — ditolak: membesarkan SQLite tanpa manfaat;
  pola file + path sudah ada dan diuji.
- Disk `s3` — ditolak: tidak diperlukan untuk demo lokal, menambah dependensi.

## R2. Representasi data: 3 kolom vs tabel anak foto

**Decision**: Satu baris `face_enrollments` per siswa dengan **3 kolom path**
(`photo_1_path`, `photo_2_path`, `photo_3_path`) + `student_id` yang **unique**.

**Rationale**: Spec FR-006 menyatakan 3 foto adalah satu kesatuan yang selalu
simpan/hapus bersama — tidak ada kasus mengambil satu foto terpisah. 3 kolom = 1
baris = query badge rekap guru tetap 1 eager-load sederhana. Konstitusi X:
sederhana dulu.

**Alternatives considered**:
- Tabel anak `face_enrollment_photos` (1 baris per foto) — ditolak: relasi, query,
  dan penghapusan jadi lebih rumit tanpa kebutuhan nyata saat ini; jumlah foto di
  masa depan (3–5 pose) cukup diakomodasi dengan kolom tambahan atau migrasi kecil.
- JSON kolom berisi daftar path — ditolak: Eloquent cast JSON mengaburkan validasi
  per slot; konstitusi menganjurkan pola paling umum/banyak tutorialnya.

## R3. Cara menghitung status badge "Terdaftar/Belum"

**Decision**: Badge dihitung saat membangun data rekap guru: terdaftar = ada baris
`face_enrollments` untuk siswa tersebut **dan** ketiga kolom path terisi **dan**
ketiga file ada di disk. Field JSON rekap mendapat `face: 'TERDAFTAR'|'BELUM'`.

**Rationale**: Edge case spec "foto terhapus dari penyimpanan" menuntut status
tidak menyesatkan. Cek `Storage::exists()` untuk 3 file × 10 siswa = 30 stat call
lokal — biaya tidak signifikan. Eager-load satu query menghindari N+1.

**Alternatives considered**:
- Kolom `status` string di tabel — ditolak: bisa meleset dari kondisi file nyata;
  dua sumber kebenaran untuk satu fakta.
- Hanya cek ada/tidaknya baris — ditolak: menyembunyikan kasus file hilang.

## R4. Cara menyajikan foto tanpa endpoint publik

**Decision**: Tidak membuat endpoint foto sama sekali. Foto dikirim sebagai
**data URL base64** yang tertanam dalam respons halaman/JSON yang dilindungi:
- Pratinjau milik siswa → tertanam di halaman status miliknya sendiri (wajib
  login sebagai siswa pemilik).
- Rekap guru/admin → tertanam di endpoint `GET /guru/rekap-harian/data` yang
  sudah dibatasi guru/admin (pola `fotoDataUrl()` yang sudah ada).

**Rationale**: Tanpa URL foto, tidak ada yang bisa "langsung dibuka dari alamat"
(edge case spec) — keamanan struktural, bukan sekadar middleware. Pola sudah
dipakai dan diuji di milestone kamera. Catatan rekonsiliasi spec: FR-007 ("hanya
via halaman wajib login guru/admin") berlaku untuk penyajian lintas-pemilik;
FR-008 secara eksplisit mengizinkan pratinjau milik siswa di halaman miliknya —
keduanya terpenuhi karena semua penyajian bersifat embedded + cek kepemilikan.

**Alternatives considered**:
- Route foto terproteksi `GET /face-photos/{id}` + cek role — ditolak: tetap
  mengekspos URL yang bisa diprediksi/dibagikan; embedded data URL lebih sederhana
  dan aman untuk skala demo.
- Stream file via controller response — ditolak: kebutuhan sama, kompleksitas sama,
  embedded lebih konsisten dengan kode rekap yang sudah ada.

## R5. Validasi & batasan data gambar dari klien

**Decision**: Terima format **data URL JPEG base64** (persis pola kiosk).
Validasi server: regex awalan `data:image/jpeg;base64,`, decode `base64_decode(..., true)`,
ukuran binary 100 B–500 KB per foto, maksimal 3 foto per request. Data tidak sah →
foto slot itu ditolak; seluruh request simpan ditolak bila kurang dari 3 foto valid
(FR-005, FR-015). Semua pesan error berbahasa Indonesia.

**Rationale**: Mengikuti pola teruji; batas 500 KB mencegah request raksasa;
`base64_decode` strict mencegah file acak. Penyimpanan 3 foto × 500 KB = maksimal
±1,5 MB per siswa — wajar untuk demo.

**Alternatives considered**:
- Upload multipart `FormData` — ditolak: beda pola dengan kiosk, tidak ada
  manfaat untuk skala demo; data URL sudah cukup dan sudah ada helper-nya.
- Cek dimensi/validasi gambar dalam (getimagesize) — bisa ditambah di implementasi,
  bukan keputusan desain; decode strict + batas ukuran sudah menutup kasus umum.

## R6. Route, middleware, dan penamaan

**Decision**:
- `GET /siswa/pendaftaran-wajah` → halaman (view) — grup `auth` + `prevent-back-history`,
  cek peran siswa di controller (403 untuk guru/admin).
- `GET /siswa/pendaftaran-wajah/status` → JSON status + tanggal + pratinjau embedded
  milik siswa login (untuk refresh halaman tanpa reload penuh).
- `POST /siswa/pendaftaran-wajah` → simpan 3 foto (jika sudah ada pendaftaran =
  daftar ulang: hapus file lama, perbarui baris; satu baris per siswa).
- Badge guru: field baru di endpoint rekap yang sudah ada (tidak ada route guru baru).
- Konvensi penamaan mengikuti Konstitusi: model `FaceEnrollment`, tabel
  `face_enrollments` (snake_case jamak), controller `FaceEnrollmentController`,
  view kebab-case `face-enrollment/show.blade.php`.

**Rationale**: Awalan `/siswa/...` konsisten dengan `/siswa/riwayat-absensi`;
daftar ulang digabung ke POST simpan (idempoten: selalu menghasilkan tepat satu
pendaftaran per siswa) agar tidak ada keadaan "foto lama sudah terhapus tapi baru
belum disimpan" bila siswa berhenti di tengah (edge case spec).

**Alternatives considered**:
- `DELETE /siswa/pendaftaran-wajah` terpisah untuk daftar ulang — ditolak: menciptakan
  jendela tanpa pendaftaran jika siswa berhenti setelah delete; berlawanan dengan
  FR-006 (satu pendaftaran aktif).
- Route terpisah `/face-enrollment` tanpa prefix peran — ditolak: pola proyek
  memisahkan area siswa/guru via prefix.

## R7. Interaksi kamera & countdown di sisi klien

**Decision**: Replikasi pola kiosk (`kiosk/show.blade.php`): Alpine.js mengelola
`getUserMedia` gagal-aman (try/catch → sembunyikan pratinjau + pesan Indonesia),
`<video autoplay muted playsinline>`, tangkap frame via `<canvas>` → `toDataURL('image/jpeg', 0.7)`.
Countdown: tombol "Ambil Foto" → hitung 3→2→1 (setInterval 1 dtk) → tangkap frame
pada detik ke-0 → isi slot berikutnya. Kamera dimatikan (`track.stop()`) saat
halaman ditutup (`beforeunload`) dan setelah 3 slot terisi — lampu kamera tidak
menyala sia-sia.

**Rationale**: Pola sudah teruji dan user eksplisit meminta "pola sama dengan
kiosk". Kualitas 0.7 menjaga ukuran file di bawah batas 500 KB pada webcam umum.

**Alternatives considered**:
- `ImageCapture API` (grabFrame) — ditolak: dukungan browser tidak merata (Safari),
  canvas frame sudah cukup.
- MediaRecorder / video — ditolak: kebutuhan hanya 3 foto statis.

## R8. Persetujuan orang tua (FR-012)

**Decision**: Sebelum alur kamera aktif, tampil kartu pernyataan (checkbox wajib):
"Saya menyatakan telah mendapat izin orang tua/wali dan bersedia foto wajah saya
dipakai sebagai data referensi absensi". Simpan `consented_at` (waktu server) pada
baris pendaftaran. Pernyataan tampil ulang di setiap daftar ulang.

**Rationale**: PRD Bab 6.2/9 mewajibkan persetujuan orang tua tercatat sebelum
perekaman wajah; pencatatan formal oleh admin (enrollment terpadu) belum dibangun,
jadi pengaman minimum tahap 1 adalah pernyataan eksplisit + timestamp —
dipilih agar fitur tidak menyalahi PRD sambil menunggu modul admin.

**Alternatives considered**:
- Menahan fitur sampai modul persetujuan admin jadi — ditolak: menghambat tahap 1
  yang ditunggu guru; pernyataan + timestamp memenuhi esensi aturan.
- Persetujuan sekali saja di database terpisah — ditolak: menambah tabel baru di
  luar lingkup; `consented_at` di baris pendaftaran cukup dan ikut ter-refresh saat
  daftar ulang.
