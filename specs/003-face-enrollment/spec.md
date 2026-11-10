# Feature Specification: Pendaftaran Mandiri Foto Wajah (Face Enrollment)

**Feature Branch**: `003-face-enrollment`

**Created**: 2026-11-10

**Status**: Draft

**Input**: User description: "Pendaftaran Mandiri Foto Wajah (Face Enrollment) — tahap 1 PRD F3. Siswa login akun existing, menu Pendaftaran Wajah, kamera getUserMedia gagal-aman (pola kiosk), 3 foto wajah dengan hitungan mundur 3 detik dan opsi ulang sebelum simpan, simpan di penyimpanan privat, tabel baru terhubung student_id, satu pendaftaran per siswa dengan status + daftar ulang, badge status di rekap guru, TANPA pencocokan/pengenalan wajah, foto tidak publik, jangan ubah alur kiosk."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Siswa Mendaftarkan Wajah Sendiri Secara Mandiri (Priority: P1) 🎯

Siswa yang sudah punya akun masuk ke aplikasi, membuka menu baru **"Pendaftaran Wajah"**, mengizinkan kamera, lalu mengambil **3 foto wajahnya sendiri** satu per satu menggunakan tombol ambil yang berhitung mundur 3 detik (memberi waktu siswa berpose). Sebelum menyimpan, siswa dapat **mengulang** pengambilan foto mana pun yang kurang bagus (kabur, terpotong, gelap). Setelah 3 foto siap, siswa menekan simpan dan seluruh foto tercatat sebagai satu pendaftaran wajah atas nama siswa tersebut di database.

**Why this priority**: Ini inti fitur (US-10 PRD). Tanpa data pendaftaran wajah, seluruh tahap Face ID berikutnya (tahap 2–3) tidak bisa dimulai. Nilai langsung: fondasi kredensial wajah tersedia.

**Independent Test**: Login sebagai siswa → buka menu "Pendaftaran Wajah" → izinkan kamera → ambil 3 foto → simpan → muncul konfirmasi "Terdaftar" dengan tanggal. Dapat diuji penuh tanpa menyentuh fitur lain.

**Acceptance Scenarios**:

1. **Given** siswa sudah login dan belum pernah mendaftar wajah, **When** siswa membuka menu "Pendaftaran Wajah" dan mengizinkan kamera, **Then** pratinjau kamera tampil di halaman dan siswa dapat melihat wajahnya sendiri.
2. **Given** pratinjau kamera aktif, **When** siswa menekan tombol "Ambil Foto", **Then** muncul hitungan mundur 3–2–1 baru foto diambil, dan slot foto pertama dari 3 terisi gambar hasil tangkapan.
3. **Given** siswa sudah mengambil 1–2 foto dan menganggap hasilnya kurang baik, **When** siswa menekan tombol ulang pada slot foto tersebut, **Then** slot kembali kosong dan dapat diambil ulang; foto lama tidak ikut tersimpan.
4. **Given** ketiga slot foto sudah terisi, **When** siswa menekan tombol "Simpan Pendaftaran", **Then** seluruh 3 foto tersimpan sebagai satu pendaftaran yang terhubung dengan data siswa tersebut, dan halaman berubah menampilkan status "Terdaftar" beserta tanggal pendaftaran.
5. **Given** siswa belum mengambil 3 foto penuh, **When** siswa menekan tombol "Simpan Pendaftaran", **Then** penyimpanan ditolak dengan pesan berbahasa Indonesia yang menjelaskan bahwa 3 foto wajib lengkap.

---

### User Story 2 - Status Pendaftaran & Daftar Ulang (Priority: P2)

Siswa yang sudah pernah mendaftar wajah membuka menu "Pendaftaran Wajah" dan melihat **status pendaftarannya** ("Terdaftar", tanggal pendaftaran, dan ringkasan foto). Siswa tetap punya opsi **"Daftar Ulang"**: foto-foto lama dihapus seluruhnya lalu digantikan alur pengambilan 3 foto baru (misalnya karena wajah berubah, pencahayaan lama buruk, atau foto lama tidak berkualitas). Satu siswa hanya memiliki **satu** pendaftaran aktif.

**Why this priority**: Melengkapi US-1 agar data wajah tetap berkualitas dan tidak menumpuk ganda. Penting untuk kesiapan tahap pengenalan, namun bisa menyusul setelah alur pendaftaran dasar berjalan.

**Independent Test**: Login sebagai siswa yang sudah terdaftar → buka menu → lihat status + tanggal → klik "Daftar Ulang" → foto lama hilang, alur ambil 3 foto baru berjalan → simpan → status terbarui dengan tanggal baru.

**Acceptance Scenarios**:

1. **Given** siswa sudah memiliki pendaftaran wajah aktif, **When** siswa membuka menu "Pendaftaran Wajah", **Then** tampil status "Terdaftar", tanggal pendaftaran, dan pratinjau foto-foto yang tersimpan.
2. **Given** siswa berada pada halaman status "Terdaftar", **When** siswa menekan tombol "Daftar Ulang" dan mengonfirmasi keputusannya, **Then** seluruh foto lama dihapus dari penyimpanan dan halaman kembali ke alur pengambilan 3 foto baru.
3. **Given** siswa menekan "Daftar Ulang" namun membatalkan pada dialog konfirmasi, **When** halaman kembali normal, **Then** pendaftaran lama tetap utuh — tidak ada foto yang hilang.
4. **Given** pendaftaran ulang selesai disimpan, **When** siswa melihat halaman status, **Then** tanggal pendaftaran yang tampil adalah tanggal terbaru dan hanya foto-foto baru yang tersimpan.

---

### User Story 3 - Guru/Admin Memantau Status Pendaftaran Wajah (Priority: P2)

Guru wali kelas (dan admin) melihat **badge status "Wajah: Terdaftar/Belum"** untuk setiap siswa di tabel rekap harian, sehingga guru tahu siswa mana yang sudah siap untuk Face ID tahap berikutnya dan mana yang perlu diingatkan.

**Why this priority**: Nilai pengawasan langsung bagi guru (pengingat siswa belum mendaftar), memanfaatkan halaman rekap yang sudah ada. Bukan penghalang US-1, tapi dibutuhkan sebelum demo ke sekolah.

**Independent Test**: Login sebagai guru → buka Rekap Harian → setiap baris siswa menampilkan badge "Wajah: Terdaftar" atau "Wajah: Belum" yang sesuai dengan kondisi nyata data pendaftaran.

**Acceptance Scenarios**:

1. **Given** ada siswa yang sudah mendaftar wajah dan siswa yang belum, **When** guru membuka Rekap Harian, **Then** baris siswa terdaftar menampilkan badge "Wajah: Terdaftar" dan baris siswa yang belum menampilkan badge "Wajah: Belum".
2. **Given** siswa baru saja menyelesaikan pendaftaran wajah, **When** rekap guru diperbarui otomatis (polling berjalan), **Then** badge siswa tersebut berubah menjadi "Wajah: Terdaftar" tanpa reload halaman.
3. **Given** guru/admin membuka rekap, **When** guru mencoba melihat isi foto pendaftaran wajah seorang siswa, **Then** foto hanya dapat dilihat melalui halaman yang wajib login sebagai guru/admin dan tidak pernah dapat diakses oleh siswa maupun pengunjung publik.

---

### Edge Cases

- **Kamera tidak tersedia, rusak, atau izin ditolak**: halaman pendaftaran tampil normal dengan pesan berbahasa Indonesia berisi apa yang terjadi dan apa yang harus dilakukan (misalnya cara mengizinkan kamera di browser); tombol ambil foto nonaktif. Tidak ada error teknis, tidak ada halaman rusak, sesi login tetap aman.
- **Kamera mati di tengah proses** (kabel lepas / tab ditutup): pratinjau berhenti dengan pesan yang jelas; foto yang sudah diambil tetap di slot sampai halaman dimuat ulang; tidak ada penyimpanan setengah jadi.
- **Data gambar tidak sah** (bukan format gambar yang sah, kosong, atau melebihi ukuran wajar): slot foto ditolak dengan pesan "Gambar tidak sah, ambil ulang"; tidak ada file yang tersimpan.
- **Siswa belum menyelesaikan 3 foto lalu menutup browser**: tidak ada pendaftaran yang tercatat; siswa kembali ke keadaan "Belum" di lain waktu.
- **Percobaan menyimpan lebih dari satu pendaftaran untuk siswa yang sama** (misalnya dua tab dibuka bersamaan): sistem hanya mempertahankan satu pendaftaran per siswa; upaya ganda menghasilkan satu pendaftaran yang konsisten.
- **Siswa mencoba mengakses foto pendaftaran milik siswa lain atau miliknya sendiri langsung dari alamat URL**: akses ditolak sepenuhnya — foto pendaftaran hanya disajikan melalui halaman wajib login guru/admin.
- **Guru/admin mencoba membuka menu pendaftaran milik siswa**: menu "Pendaftaran Wajah" tidak tersedia untuk peran guru/admin; rekap hanya menampilkan badge status.
- **Foto terhapus dari penyimpanan secara tidak sengaja** (kasus jarang): status pendaftaran yang fotonya tidak lengkap diperlakukan tidak valid sehingga guru melihat kondisi perlu daftar ulang, bukan status menyesatkan "Terdaftar penuh".

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem MUST menyediakan menu **"Pendaftaran Wajah"** yang hanya tersedia bagi pengguna dengan peran siswa dan hanya aktif setelah pengguna login.
- **FR-002**: Sistem MUST menampilkan alur pendaftaran **kamera langsung** di halaman tersebut; kegagalan kamera apa pun (perangkat tidak ada, izin ditolak, peramban tidak mendukung) MUST gagal-aman: pratinjau disembunyikan, pesan berbahasa Indonesia ditampilkan, dan tampilan halaman tetap utuh tanpa error.
- **FR-003**: Sistem MUST memungkinkan siswa mengambil **tepat 3 foto wajah** melalui tombol ambil yang diawali hitungan mundur 3 detik sebelum setiap tangkapan.
- **FR-004**: Sistem MUST mengizinkan pengulangan pengambilan per slot foto selama pendaftaran **belum disimpan**; foto yang diulang menggantikan isi slot yang sama.
- **FR-005**: Sistem MUST menolak penyimpanan bila jumlah foto belum 3, dengan pesan berbahasa Indonesia yang menyebut kekurangannya.
- **FR-006**: Sistem MUST menyimpan setiap pendaftaran sebagai **satu kesatuan data yang terhubung dengan data siswa** (identitas siswa yang login), terdiri atas 3 foto dan tanggal pendaftaran; satu siswa MUST hanya memiliki satu pendaftaran aktif.
- **FR-007**: Sistem MUST menyimpan foto pendaftaran di **penyimpanan privat** yang tidak dapat diakses oleh alamat publik mana pun; foto hanya boleh disajikan melalui halaman/endpoint yang wajib login sebagai guru atau admin.
- **FR-008**: Sistem MUST menampilkan status pendaftaran bagi siswa yang sudah terdaftar: status "Terdaftar", tanggal pendaftaran, dan pratinjau foto miliknya sendiri.
- **FR-009**: Sistem MUST menyediakan opsi **"Daftar Ulang"** yang menghapus seluruh foto pendaftaran lama sebelum memulai alur pengambilan 3 foto baru, diawali dialog konfirmasi agar tidak terjadi kehilangan data tak sengaja.
- **FR-010**: Sistem MUST menampilkan badge **"Wajah: Terdaftar"** atau **"Wajah: Belum"** per siswa pada tabel rekap harian yang dilihat guru/admin, dan badge tersebut ikut terbarui saat rekap diperbarui otomatis.
- **FR-011**: Sistem MUST **tidak melakukan pencocokan, pengenalan, pembandingan, maupun analisis wajah** dalam fitur ini — hanya mengumpulkan dan menampilkan foto referensi. Pengecualian apa pun dianggap pelanggaran lingkup.
- **FR-012**: Sistem MUST menampilkan **pernyataan persetujuan** sebelum alur pendaftaran dimulai, yang menyatakan bahwa siswa telah mendapat izin orang tua/wali dan foto wajah akan dipakai sebagai data referensi absensi; alur pendaftaran hanya berjalan setelah siswa menyetujuinya (mengikuti PRD: perekaman wajah hanya boleh setelah persetujuan orang tua tercatat).
- **FR-013**: Sistem MUST memastikan perubahan fitur ini **tidak mengubah alur tap kiosk, logika status kehadiran, maupun pencatatan waktu** yang sudah berfungsi; fitur ini berdiri sendiri dan kegagalan pendaftaran wajah tidak boleh memengaruhi absensi kartu.
- **FR-014**: Sistem MUST mencatat tanggal pendaftaran dan tanggal pendaftaran ulang terakhir berdasarkan **waktu server**.
- **FR-015**: Sistem MUST menolak data gambar yang tidak sah (bukan format foto yang sah, kosong, atau melebihi batas ukuran wajar) tanpa menyimpan file apa pun.

### Key Entities *(include if feature involves data)*

- **PendaftaranWajah (Face Enrollment)**: satu kesatuan pendaftaran milik tepat satu siswa; berisi 3 referensi foto wajah, status pendaftaran (aktif/belum), tanggal pendaftaran, dan tanggal pendaftaran ulang terakhir; terhubung dengan data siswa pemiliknya dan tidak dimiliki oleh siapa pun selain siswa tersebut.
- **Siswa (Student)**: pemilik pendaftaran wajah; sudah ada dari fitur sebelumnya; relasinya: satu siswa punya paling banyak satu pendaftaran wajah.
- **FotoReferensiWajah**: berkas gambar wajah hasil tangkapan kamera yang tersimpan privat; selalu bagian dari satu pendaftaran; ikut terhapus saat pendaftaran diulang.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Siswa dengan kamera yang baik dapat menyelesaikan pendaftaran lengkap (3 foto + simpan) dalam waktu kurang dari 3 menit pada percobaan pertama.
- **SC-002**: Pada 100% kondisi tanpa kamera atau izin kamera ditolak, halaman pendaftaran tetap tampil utuh tanpa error teknis dan alur absensi kiosk tetap berfungsi normal.
- **SC-003**: Setiap pendaftaran yang berhasil tersimpan berisi tepat 3 foto referensi dan tampil di database terhubung dengan siswa yang benar — tidak ada pendaftaran ganda per siswa.
- **SC-004**: 100% percobaan akses foto pendaftaran dari akun siswa, akun tanpa login, atau alamat langsung terhadap penyimpanan harus ditolak; hanya guru/admin yang dapat melihatnya.
- **SC-005**: Guru dapat menentukan siapa saja siswa yang belum mendaftar wajah hanya dengan melihat badge di rekap harian, tanpa membuka data satu per satu (pembaruan badge tampil dalam satu siklus pembaruan otomatis rekap, ≤ 5 detik).
- **SC-006**: Seluruh skenario uji manual (termasuk daftar ulang dan gagal-aman tanpa kamera) dapat dijalankan oleh orang non-teknis hanya dengan mengikuti panduan berbahasa Indonesia di README.

## Assumptions

- **Akar masalah & sumber kebenaran**: Fitur ini adalah **tahap 1** dari US-10/F3 PRD — hanya pengumpulan foto referensi tanpa pengenalan. Konversi foto menjadi embedding (sesuai PRD "perekaman wajah 3–5 pose disimpan sebagai embedding") dan pengenalan wajah di kiosk adalah **tahap berikutnya**; karena itu spec ini sengaja menyimpan foto referensi terlebih dahulu dan menandai bahwa foto-foto tersebut harus dapat dihapus/diganti saat tahap embedding terealisasi.
- **Persetujuan orang tua**: PRD mensyaratkan perekaman wajah hanya boleh dijalankan setelah persetujuan orang tua tercatat. Karena pencatatan formal oleh admin (enrollment terpadu, Bab 6.2 PRD) belum dibangun, tahap ini memakai **pernyataan persetujuan yang ditandatangani elektronik oleh siswa** di layar (FR-012) sebagai pengaman minimum; pencatatan formal menjadi bagian tahap berikutnya.
- **Jumlah foto**: PRD menyebut 3–5 pose; kebutuhan guru menyebut 3 foto, sehingga standar fitur ini adalah **tepat 3**.
- **Lingkungan kamera**: Siswa menggunakan laptop/PC kampus atau perangkat sekolah dengan webcam; kualitas gambar mengikuti kemampuan kamera bawaan tanpa penyaringan kualitas otomatis di tahap ini.
- **Privasi**: Foto wajah adalah data sensitif anak di bawah umur (Konstitusi VIII) — penyimpanan privat, akses terbatas guru/admin, dan larangan pencocokan wajah (FR-007, FR-011) berlaku tanpa pengecualian.
- **Di luar lingkup tahap ini**: pengenalan wajah di kiosk, persetujuan orang tua formal, kuota percobaan pengenalan (F3), Face ID sebagai jalur absen, kamera di kiosk untuk daftar wajah, dan ekspor foto.
