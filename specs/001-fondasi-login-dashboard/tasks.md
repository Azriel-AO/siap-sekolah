# Tasks: Fondasi Login & Dashboard (Milestone 0)

**Input**: Design documents from `specs/001-fondasi-login-dashboard/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/routes.md`, `quickstart.md`
**Constitution**: SIAP KNBI Constitution v2.0.0 (Laravel 13 + Blade + Tailwind CSS + SQLite)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (`[US1]`, `[US2]`, `[US3]`)
- Exact file paths included in all descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Inisialisasi project Laravel 13, setup SQLite database, dan kompilasi Tailwind CSS via Vite.

- [X] T001 Initialize Laravel 13 project scaffolding with Breeze (Blade stack) in repository root
- [X] T002 Configure environment variables in `.env` and `.env.example` (`APP_NAME="SIAP — SMK Karya Nugraha"`, `DB_CONNECTION=sqlite`, `SESSION_DRIVER=file`) and ensure `database/database.sqlite` exists
- [X] T003 Install npm packages and build frontend assets using Vite in `package.json`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Struktur database dan model pengguna yang menjadi prasyarat untuk seluruh user story.

**⚠️ CRITICAL**: Task implementasi user story tidak dapat dimulai sebelum Phase 2 selesai.

- [X] T004 Create database migration in `database/migrations/xxxx_add_role_to_users_table.php` adding column `role` with constraints verbatim: `varchar(10)`, `nullable: false`, `default: 'siswa'`, allowed values: `'admin'`, `'guru'`, `'siswa'`
- [X] T005 [P] Update User Eloquent model in `app/Models/User.php` adding `'role'` to `$fillable` array
- [X] T006 [P] Create user seeder in `database/seeders/UserSeeder.php` with 3 seed accounts using `Hash::make('password')`: Admin (`name: 'Admin KNBI'`, `email: 'admin@knbi.sch.id'`, `role: 'admin'`), Guru (`name: 'Bu Sari'`, `email: 'guru@knbi.sch.id'`, `role: 'guru'`), Siswa (`name: 'Raka Siswa'`, `email: 'siswa@knbi.sch.id'`, `role: 'siswa'`)
- [X] T007 Register `UserSeeder::class` in `database/seeders/DatabaseSeeder.php` and execute migrations and seed via `php artisan migrate:fresh --seed`

**Checkpoint**: Skema database dan akun seed siap — implementasi User Story dapat dimulai.

---

## Phase 3: User Story 1 - Login Berhasil Sesuai Peran (Priority: P1) 🎯 MVP

**Goal**: Pengguna (admin, guru, siswa) dapat login dengan email + kata sandi dan diarahkan ke dashboard dengan header nama aplikasi, nama pengguna, label peran yang tepat, menu placeholder, serta tombol logout.

**Independent Test**: Buka browser ke `http://localhost:8000/login`, masukkan kredensial seed untuk masing-masing peran (admin, guru, siswa), verifikasi bahwa setiap akun berhasil masuk ke `/dashboard` dan menampilkan nama serta label peran yang tepat ("Admin", "Guru", "Siswa").

### Implementation for User Story 1

- [X] T008 [US1] Create DashboardController in `app/Http/Controllers/DashboardController.php` with `index` method passing authenticated user data (`auth()->user()`) to dashboard view
- [X] T009 [P] [US1] Create dashboard view in `resources/views/dashboard.blade.php` displaying header "SIAP — SMK Karya Nugraha", current user name, role label ("Admin" / "Guru" / "Siswa"), 4 placeholder navigation items ("Data Siswa", "Jadwal Kelas", "Kiosk Absen", "Laporan" showing "Segera hadir" on click), and a "Keluar" button
- [X] T010 [P] [US1] Customize login view in `resources/views/auth/login.blade.php` with Indonesian labels ("Email", "Kata Sandi", "Masuk"), clear branding, and autofocus
- [X] T011 [US1] Configure routes in `routes/web.php` for root redirect (`GET /` -> `/dashboard`) and dashboard route (`GET /dashboard`) protected by `auth` middleware

**Checkpoint**: User Story 1 selesai. Ketiga peran pengguna dapat login dan melihat dashboard dengan identitas masing-masing (MVP tercapai).

---

## Phase 4: User Story 2 - Login Gagal dengan Pesan Jelas (Priority: P2)

**Goal**: Pengguna yang memasukkan kredensial salah atau form tidak valid menerima pesan kesalahan berbahasa Indonesia yang jelas dan ramah pengguna, bukan kode error teknis.

**Independent Test**: Masukkan kata sandi salah di form login dan verifikasi muncul pesan "Email atau kata sandi salah. Silakan coba lagi.". Kosongkan kolom dan verifikasi pesan "Kolom ini wajib diisi.". Masukkan format email salah dan verifikasi pesan "Format email tidak valid.".

### Implementation for User Story 2

- [X] T012 [US2] Customize authentication validation messages in `app/Http/Requests/Auth/LoginRequest.php` or `lang/id/auth.php` ensuring failed login returns Indonesian message: "Email atau kata sandi salah. Silakan coba lagi."
- [X] T013 [US2] Add required field and email format validation messages in `app/Http/Requests/Auth/LoginRequest.php` and display error hints under input fields in `resources/views/auth/login.blade.php` ("Kolom ini wajib diisi." dan "Format email tidak valid.")

**Checkpoint**: User Story 1 dan 2 selesai. Login sukses dan penanganan error validasi berfungsi ramah pengguna.

---

## Phase 5: User Story 3 - Logout Berfungsi (Priority: P2)

**Goal**: Pengguna yang telah login dapat logout dengan aman, sesi dihapus dari server, dan rute dashboard terlindungi dari akses tanpa autentikasi maupun navigasi tombol kembali browser.

**Independent Test**: Klik tombol "Keluar" di dashboard, pastikan diarahkan ke `/login`. Buka jendela Incognito dan akses langsung `/dashboard`, pastikan dialihkan ke `/login`. Setelah logout, tekan tombol "Back" di browser dan pastikan sesi tidak aktif kembali.

### Implementation for User Story 3

- [X] T014 [US3] Implement logout form with `@csrf` directive in `resources/views/dashboard.blade.php` submitting POST to `/logout`
- [X] T015 [US3] Ensure `AuthenticatedSessionController@destroy` in `app/Http/Controllers/Auth/AuthenticatedSessionController.php` invalidates session, regenerates CSRF token, and redirects to `/login`
- [X] T016 [US3] Verify route protection in `routes/web.php` and add cache control headers/middleware to prevent accessing cached dashboard views after logout

**Checkpoint**: Seluruh 3 User Story selesai dan bekerja secara independen maupun terintegrasi.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Dokumentasi ramah pemula dan pengujian manual menyeluruh sebelum rilis Milestone 0.

- [X] T017 Create comprehensive, beginner-friendly `README.md` at repository root explaining prerequisites (PHP 8.3+, Composer, Node.js), step-by-step setup commands, credentials table for the 3 seed users, and manual testing instructions
- [X] T018 Execute all 10 manual validation scenarios in `specs/001-fondasi-login-dashboard/quickstart.md` and verify final checklist completion

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1: Setup (T001-T003)
      ↓
Phase 2: Foundational (T004-T007) [BLOCKS US1, US2, US3]
      ↓
Phase 3: User Story 1 (T008-T011) [P1 - MVP]
      ↓
Phase 4: User Story 2 (T012-T013) [P2]
      ↓
Phase 5: User Story 3 (T014-T016) [P2]
      ↓
Phase 6: Polish & Verification (T017-T018)
```

- **Setup (Phase 1)**: Tidak ada dependensi — langsung dijalankan.
- **Foundational (Phase 2)**: Bergantung pada Phase 1. Memblokir seluruh User Story.
- **User Story 1 (Phase 3)**: Bergantung pada Phase 2. Menghasilkan MVP.
- **User Story 2 (Phase 4)**: Bergantung pada Phase 3 (menyesuaikan pesan login di form yang sudah ada).
- **User Story 3 (Phase 5)**: Bergantung pada Phase 3 (mengaitkan tombol logout di dashboard yang sudah ada).
- **Polish (Phase 6)**: Bergantung pada seluruh User Story selesai.

### Parallel Opportunities

- **Phase 2**: `T005` (User model) dan `T006` (UserSeeder) dapat dikerjakan paralel setelah migrasi `T004`.
- **Phase 3**: `T009` (dashboard view) dan `T010` (login view) dapat dikerjakan paralel.
- **Phase 6**: `T017` (README.md) dapat disiapkan saat validasi akhir `T018` berlangsung.

---

## Parallel Example: User Story 1

```bash
# Views untuk User Story 1 dapat dikerjakan secara paralel:
Task: "T009 [P] [US1] Create dashboard view in resources/views/dashboard.blade.php"
Task: "T010 [P] [US1] Customize login view in resources/views/auth/login.blade.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Selesaikan Phase 1 (Setup: Laravel + Breeze + Vite + SQLite).
2. Selesaikan Phase 2 (Foundational: migrasi kolom `role`, model `User`, `UserSeeder`).
3. Selesaikan Phase 3 (User Story 1: `DashboardController`, `dashboard.blade.php`, `login.blade.php`, routes).
4. **Validasi MVP**: Login dengan 3 akun seed (Admin, Guru, Siswa) dan pastikan dashboard menampilkan nama + label peran masing-masing.

### Incremental Delivery

1. Setup + Foundation selesai -> Pondasi aplikasi siap.
2. Tambahkan User Story 1 -> Demo MVP berhasil (Login sukses sesuai peran).
3. Tambahkan User Story 2 -> Penanganan error kredensial dan form ramah pemula.
4. Tambahkan User Story 3 -> Logout aman dan proteksi sesi lengkap.
5. Selesaikan Polish -> README untuk orang awam dan validasi skenario 1-10 lengkap.
