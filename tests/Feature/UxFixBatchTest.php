<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\KioskAttendanceService;
use App\Services\StudentRegistrationService;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UxFixBatchTest extends TestCase
{
    use RefreshDatabase;

    private function validPngDataUrl(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data) & 0xFFFFFFFF);
        };

        $ihdr = pack('N2C5', 2, 2, 8, 2, 0, 0, 0);
        $idat = (string) gzcompress("\x00\xFF\x00\x00\x00\xFF\x00\x00\xFF\x00\xFF\xFF\x00\x00");
        $text = "Comment\x00".str_repeat('P', 120);

        return 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1A\n".$chunk('IHDR', $ihdr).$chunk('tEXt', $text).$chunk('IDAT', $idat).$chunk('IEND', ''));
    }

    public function test_pendaftaran_mengembalikan_json_422_saat_terjadi_throwable_dengan_accept_json(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        $this->mock(StudentRegistrationService::class, function ($mock): void {
            $mock->shouldReceive('register')
                ->twice()
                ->andThrow(new \RuntimeException('Simulasi error tak terduga'));
        });

        $payload = [
            'name' => 'Siswa Error JSON',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0099554433',
            'setuju' => true,
            'card_uid' => 'CARD-ERR-JSON-1',
            'photos' => [
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
            ],
        ];

        $jsonRes = $this->postJson(route('pendaftaran.simpan'), $payload);
        $jsonRes->assertStatus(422)
            ->assertJsonValidationErrors(['gagal']);
        $this->assertStringContainsString(
            'Gagal menyimpan pendaftaran.',
            (string) $jsonRes->json('errors.gagal.0')
        );

        // Permintaan form web biasa (non-JSON) tetap redirect kembali dengan session error
        $webRes = $this->post(route('pendaftaran.simpan'), $payload);
        $webRes->assertRedirect();
        $webRes->assertSessionHasErrors(['gagal']);
    }

    public function test_admin_student_store_dan_update_mencegah_500_saat_nis_bentrok_dengan_akun_non_siswa(): void
    {
        $this->seed(DemoAttendanceSeeder::class);

        $admin = User::factory()->create([
            'email' => 'admin@knbi.sch.id',
            'password' => Hash::make('admin-secret'),
            'role' => 'admin',
        ]);
        $guru = User::factory()->create([
            'email' => 'guru@knbi.sch.id',
            'password' => Hash::make('guru-secret'),
            'role' => 'guru',
        ]);

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        // Store dengan NIS="admin" harus gagal rapi sebagai validation error (bukan HTTP 500)
        $storeRes = $this->actingAs($admin)->post(route('admin.siswa.store'), [
            'name' => 'Penyerang Store',
            'nis' => 'admin',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
        ]);
        $storeRes->assertRedirect();
        $storeRes->assertSessionHasErrors(['nis']);
        $this->assertDatabaseMissing('students', ['nis' => 'admin']);
        $this->assertSame('admin', $admin->fresh()->role);

        // Update ke NIS="guru" juga harus gagal rapi sebagai validation error (bukan HTTP 500)
        $existingStudent = Student::where('nis', '10240101')->firstOrFail();
        $updateRes = $this->actingAs($admin)->put(route('admin.siswa.update', $existingStudent), [
            'name' => $existingStudent->name,
            'nis' => 'guru',
            'gender' => $existingStudent->gender,
            'school_class_id' => $kelas->id,
        ]);
        $updateRes->assertRedirect();
        $updateRes->assertSessionHasErrors(['nis']);
        $this->assertSame('10240101', $existingStudent->fresh()->nis);
        $this->assertSame('guru', $guru->fresh()->role);
    }

    public function test_kiosk_tap_throttle_60_per_menit_dan_pesan_429_bahasa_indonesia(): void
    {
        $this->seed(DemoAttendanceSeeder::class);

        for ($i = 1; $i <= 60; $i++) {
            $this->postJson(route('kiosk.tap'), [
                'card_uid' => 'ANTRIAN-PAGI-'.$i,
            ])->assertNotFound();
        }

        $this->postJson(route('kiosk.tap'), [
            'card_uid' => 'ANTRIAN-PAGI-61',
        ])->assertTooManyRequests();

        $kioskView = $this->get(route('kiosk.gerbang'));
        $kioskView->assertOk();
        $kioskView->assertSee('isThrottled', false);
        $kioskView->assertSee('hasilThrottle', false);
        $kioskView->assertSee('Sistem Sibuk', false);
        $kioskView->assertSee('Sistem sedang sibuk melayani antrean. Tunggu sebentar, lalu tap ulang kartu Anda.', false);
    }

    public function test_cache_forget_gagal_tidak_menghapus_foto_pendaftaran_yang_sudah_commit(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        Cache::shouldReceive('forget')
            ->andThrow(new \RuntimeException('Simulasi kegagalan cache'));

        $service = app(StudentRegistrationService::class);
        $student = $service->register([
            'name' => 'Siswa Cache Aman',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0099443322',
            'setuju' => true,
            'card_uid' => 'CARD-CACHE-AMAN-1',
            'photos' => [
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
                $this->validPngDataUrl(),
            ],
        ]);

        $enrollment = $student->faceEnrollment()->firstOrFail();
        Storage::disk('local')->assertExists($enrollment->photo_1_path);
        Storage::disk('local')->assertExists($enrollment->photo_2_path);
        Storage::disk('local')->assertExists($enrollment->photo_3_path);
    }

    public function test_kegagalan_transaksi_di_luar_create_tetap_membersihkan_foto_bukti_kiosk(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $student = Student::where('nis', '10240109')->firstOrFail();
        $validPhoto = 'data:image/jpeg;base64,'.base64_encode("\xFF\xD8".str_repeat('a', 150)."\xFF\xD9");

        Attendance::created(function (): void {
            throw new \RuntimeException('Simulasi kegagalan sesudah insert sebelum commit');
        });

        try {
            $service = app(KioskAttendanceService::class);
            try {
                $service->recordAttendance($student, $validPhoto, 'gerbang-kartu', false);
                $this->fail('Seharusnya RuntimeException dilempar keluar dari transaksi.');
            } catch (\RuntimeException $e) {
                $this->assertSame('Simulasi kegagalan sesudah insert sebelum commit', $e->getMessage());
            }

            $this->assertCount(0, Storage::disk('local')->allFiles('attendance-photos'));
        } finally {
            Attendance::flushEventListeners();
        }
    }
}
