<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\FaceTemplateService;
use App\Services\KioskAttendanceService;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityBatch1Test extends TestCase
{
    use RefreshDatabase;

    private function validPngDataUrl(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data) & 0xFFFFFFFF);
        };

        $ihdr = pack('N2C5', 2, 2, 8, 2, 0, 0, 0);
        $idat = (string) gzcompress("\x00\xFF\x00\x00\x00\xFF\x00\x00\xFF\x00\xFF\xFF\x00\x00");

        return 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1A\n".$chunk('IHDR', $ihdr).$chunk('IDAT', $idat).$chunk('IEND', ''));
    }

    /**
     * @return list<float>
     */
    private function basisVector(int $activeFrom, int $activeCount, float $value = 1.0): array
    {
        $vec = array_fill(0, FaceTemplateService::VECTOR_LENGTH, 0.0);
        for ($i = $activeFrom; $i < min($activeFrom + $activeCount, FaceTemplateService::VECTOR_LENGTH); $i++) {
            $vec[$i] = $value;
        }

        return $vec;
    }

    public function test_face_checkin_menghitung_cosine_similarity_di_server_dan_mengabaikan_similarity_client(): void
    {
        Storage::fake('local');
        $this->seed(DemoAttendanceSeeder::class);

        $student = Student::where('nis', '10240109')->firstOrFail();
        $faceTemplates = app(FaceTemplateService::class);
        $stored = $faceTemplates->generateDeterministicVector($student->id);
        $faceTemplates->storeForStudent($student->id, $stored);

        // 1. Klien mengirim similarity palsu tanpa vector wajib ditolak 422
        $this->postJson('/kiosk/face-checkin', [
            'student_id' => $student->id,
            'similarity' => 1.0,
        ])->assertUnprocessable()->assertJsonValidationErrors(['vector']);

        // 2. Vector tidak cocok secara matematis (< 0.75) ditolak meskipun klien ikut menyelipkan similarity=0.99
        $orthogonalVector = $this->basisVector(0, 100, 1.0);
        // Set stored dengan basis terpisah agar cosine similarity tepat 0.0
        $storedBasis = $this->basisVector(500, 100, 1.0);
        $faceTemplates->storeForStudent($student->id, $storedBasis);

        $this->postJson('/kiosk/face-checkin', [
            'student_id' => $student->id,
            'similarity' => 0.99,
            'vector' => $orthogonalVector,
        ])->assertStatus(422)->assertJson([
            'success' => false,
            'type' => 'face_mismatch',
        ]);

        // 3. Vector dengan cosine similarity ~0.80 (>= 0.75) diterima dan similarity dihitung oleh server
        $matchingBasis = $this->basisVector(500, 64, 1.0); // sqrt(64/100) = 0.8
        $ok = $this->postJson('/kiosk/face-checkin', [
            'student_id' => $student->id,
            'similarity' => 0.1,
            'vector' => $matchingBasis,
        ]);

        $ok->assertOk()->assertJson([
            'success' => true,
            'type' => 'check_in',
            'method' => 'Wajah',
            'similarity' => 0.8,
        ]);
    }

    public function test_face_verify_menolak_token_null_atau_kosong_secara_langsung(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        $student = Student::where('nis', '10240109')->firstOrFail();

        $this->postJson('/kiosk/face-verify', [
            'student_id' => $student->id,
            'match_score' => 0.99,
        ])->assertUnprocessable()->assertJsonValidationErrors(['verify_token']);

        $this->postJson('/kiosk/face-verify', [
            'student_id' => $student->id,
            'match_score' => 0.99,
            'verify_token' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors(['verify_token']);

        $this->postJson('/kiosk/face-verify', [
            'student_id' => $student->id,
            'match_score' => 0.99,
            'verify_token' => 'token-palsu-tidak-ada-di-cache',
        ])->assertStatus(422)->assertJson(['type' => 'replay_detected']);

        // Defense-in-depth langsung di level Service untuk null/empty string
        $service = app(KioskAttendanceService::class);
        $nullResult = $service->processFaceVerify($student->id, 0.99, null, null);
        $this->assertSame(422, $nullResult->getStatusCode());
        $this->assertSame('replay_detected', $nullResult->getData(true)['type']);

        $emptyResult = $service->processFaceVerify($student->id, 0.99, null, '   ');
        $this->assertSame(422, $emptyResult->getStatusCode());
        $this->assertSame('replay_detected', $emptyResult->getData(true)['type']);
    }

    public function test_sync_user_account_memakai_password_acak_dan_mencegah_tabrakan_akun_non_siswa(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        $admin = User::factory()->create([
            'name' => 'Admin Utama',
            'email' => 'admin@knbi.sch.id',
            'password' => Hash::make('rahasia-admin'),
            'role' => 'admin',
        ]);

        // Tabrakan dengan akun admin -> wajib melempar RuntimeException & tidak menimpa akun admin
        $collidingStudent = Student::create([
            'school_class_id' => $kelas->id,
            'nis' => 'admin',
            'nisn' => '0011223399',
            'name' => 'Penyerang Admin',
            'gender' => 'L',
        ]);

        try {
            $collidingStudent->syncUserAccount();
            $this->fail('Seharusnya syncUserAccount() melempar Exception saat email dipakai admin/guru.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Email sudah digunakan oleh akun non-siswa', $e->getMessage());
        }

        $adminFresh = $admin->fresh();
        $this->assertSame('admin', $adminFresh->role);
        $this->assertTrue(Hash::check('rahasia-admin', $adminFresh->password));

        // Siswa normal mendapat password acak aman (bukan literal 'password')
        $normalStudent = Student::create([
            'school_class_id' => $kelas->id,
            'nis' => '10269991',
            'nisn' => '0026999100',
            'name' => 'Siswa Password Acak',
            'gender' => 'P',
        ]);
        $createdUser = $normalStudent->syncUserAccount();

        $this->assertSame('siswa', $createdUser->role);
        $this->assertNotEmpty($createdUser->password);
        $this->assertFalse(Hash::check('password', $createdUser->password));
    }

    public function test_validasi_nis_admin_student_menolak_karakter_non_alfanumerik(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        foreach (['1024@evil', '1024 99', '1024-01', '1024<script>', str_repeat('A', 21)] as $badNis) {
            $res = $this->actingAs($admin)->post(route('admin.siswa.store'), [
                'name' => 'Siswa Uji NIS',
                'nis' => $badNis,
                'gender' => 'L',
                'school_class_id' => $kelas->id,
            ]);
            $res->assertSessionHasErrors('nis');
        }

        $validRes = $this->actingAs($admin)->post(route('admin.siswa.store'), [
            'name' => 'Siswa Alfanumerik Valid',
            'nis' => 'TKJ2026A01',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
        ]);
        $validRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['nis' => 'TKJ2026A01']);
    }

    public function test_fingerprint_id_unik_di_skema_database_dan_validasi_pendaftaran(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        // Nilai NULL ganda tetap diperbolehkan oleh SQL UNIQUE constraint
        Student::create([
            'school_class_id' => $kelas->id,
            'nis' => 'FPNULL1',
            'name' => 'Tanpa Jari 1',
            'gender' => 'L',
            'fingerprint_id' => null,
        ]);
        Student::create([
            'school_class_id' => $kelas->id,
            'nis' => 'FPNULL2',
            'name' => 'Tanpa Jari 2',
            'gender' => 'P',
            'fingerprint_id' => null,
        ]);

        // Nilai fingerprint_id yang sama persis wajib ditolak di level DB maupun PendaftaranController
        Student::where('nis', '10240109')->update(['fingerprint_id' => 'UNIK-FP-001']);

        $res = $this->postJson('/pendaftaran', [
            'name' => 'Duplikat Jari',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0088112233',
            'setuju' => true,
            'card_uid' => 'CARD-FPUNIK-01',
            'fingerprint_id' => 'UNIK-FP-001',
            'photos' => [
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
            ],
        ]);

        $res->assertUnprocessable()->assertJsonValidationErrors(['fingerprint_id']);

        $this->expectException(QueryException::class);
        Student::create([
            'school_class_id' => $kelas->id,
            'nis' => 'FPDUPL',
            'name' => 'Paksa Duplikat DB',
            'gender' => 'L',
            'fingerprint_id' => 'UNIK-FP-001',
        ]);
    }

    public function test_record_attendance_hanya_menangani_unique_constraint_sebagai_cooldown_dan_melempar_error_db_lain(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $student = Student::where('nis', '10240109')->firstOrFail();
        $service = app(KioskAttendanceService::class);
        $validPhoto = 'data:image/jpeg;base64,'.base64_encode("\xFF\xD8".str_repeat('a', 150)."\xFF\xD9");

        // Simulasikan error QueryException akibat UNIQUE constraint -> harus ditangani sebagai 429 cooldown & hapus foto yatim
        Attendance::creating(function (): void {
            throw new QueryException(
                'sqlite',
                'insert into attendances',
                [],
                new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: attendances.student_id, attendances.date')
            );
        });

        try {
            $cooldown = $service->recordAttendance($student, $validPhoto, 'gerbang-kartu', false);
            $this->assertSame(429, $cooldown->getStatusCode());
            $this->assertSame('cooldown', $cooldown->getData(true)['type']);
            $this->assertCount(0, Storage::disk('local')->allFiles('attendance-photos'));
        } finally {
            Attendance::flushEventListeners();
        }

        // Error DB selain UNIQUE constraint WAJIB dilempar kembali dan TIDAK disembunyikan sebagai cooldown
        Attendance::creating(function (): void {
            throw new QueryException(
                'sqlite',
                'insert into attendances',
                [],
                new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: attendances.school_class_id')
            );
        });

        try {
            $this->expectException(QueryException::class);
            $service->recordAttendance($student, $validPhoto, 'gerbang-kartu', false);
        } finally {
            Attendance::flushEventListeners();
        }
    }
}
