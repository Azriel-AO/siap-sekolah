<?php

namespace Tests\Feature;

use App\Models\FaceEnrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Task49OptimizationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function validJpegBinary(): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            .str_repeat("\x11", 120)
            ."\xFF\xD9";
    }

    private function validPngBinary(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };

        $sig = "\x89PNG\r\n\x1a\n";
        $ihdr = $chunk('IHDR', pack('NNCCCCC', 16, 16, 8, 2, 0, 0, 0));
        $rawRow = "\x00".str_repeat("\xFF\x00\x00", 16);
        $idat = $chunk('IDAT', gzcompress(str_repeat($rawRow, 16)));
        $text = $chunk('tEXt', "Comment\x00".str_repeat('p', 64));
        $iend = $chunk('IEND', '');

        return $sig.$ihdr.$idat.$text.$iend;
    }

    /**
     * @return array<string, mixed>
     */
    private function pendaftaranPayload(array $override = []): array
    {
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $pngDataUrl = 'data:image/png;base64,'.base64_encode($this->validPngBinary());

        return array_merge([
            'name' => 'Siswa Uji Keamanan',
            'gender' => 'P',
            'school_class_id' => $kelas->id,
            'nisn' => '0099887766',
            'setuju' => true,
            'card_uid' => 'CARD-SEC-001',
            'fingerprint_id' => 'FP-SEC-001',
            'photos' => [$pngDataUrl, $pngDataUrl, $pngDataUrl],
        ], $override);
    }

    public function test_tabel_inti_memiliki_index_performa(): void
    {
        $this->assertTrue(Schema::hasIndex('students', ['nisn']));
        $this->assertTrue(Schema::hasIndex('students', ['school_class_id']));
        $this->assertTrue(Schema::hasIndex('attendances', ['student_id']));
        $this->assertTrue(Schema::hasIndex('attendances', ['date']));
        $this->assertTrue(Schema::hasIndex('attendances', ['school_class_id']));
        $this->assertTrue(Schema::hasIndex('users', ['email']));
        $this->assertTrue(Schema::hasIndex('users', ['student_id']));
    }

    public function test_rate_limiter_login_membatasi_percobaan_ke_6_dengan_429(): void
    {
        User::factory()->create(['email' => 'admin@knbi.sch.id', 'role' => 'admin']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => 'admin@knbi.sch.id',
                'password' => 'wrong-password',
            ]);
            $response->assertRedirect();
        }

        $blocked = $this->post('/login', [
            'email' => 'admin@knbi.sch.id',
            'password' => 'wrong-password',
        ]);

        $blocked->assertTooManyRequests();
    }

    public function test_rate_limiter_kiosk_tap_membatasi_permintaan_ke_31_dengan_429(): void
    {
        $this->seed(DemoAttendanceSeeder::class);

        for ($i = 0; $i < 30; $i++) {
            $response = $this->postJson('/kiosk/tap', [
                'card_uid' => 'UNKNOWN-'.$i,
            ]);
            $response->assertNotFound();
        }

        $blocked = $this->postJson('/kiosk/tap', [
            'card_uid' => 'UNKNOWN-31',
        ]);

        $blocked->assertTooManyRequests();
    }

    public function test_rate_limiter_kiosk_face_checkin_membatasi_permintaan_ke_11_dengan_429(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        $student = Student::firstOrFail();

        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/kiosk/face-checkin', [
                'student_id' => $student->id,
                'vector' => array_fill(0, 1434, 0.01),
            ]);
            $response->assertUnprocessable();
        }

        $blocked = $this->postJson('/kiosk/face-checkin', [
            'student_id' => $student->id,
            'vector' => array_fill(0, 1434, 0.01),
        ]);

        $blocked->assertTooManyRequests();
    }

    public function test_pendaftaran_menerima_foto_png_valid_dan_menyimpan_ekstensi_png(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $response = $this->postJson('/pendaftaran', $this->pendaftaranPayload());

        $response->assertOk();
        $student = Student::where('nisn', '0099887766')->firstOrFail();
        $enrollment = FaceEnrollment::where('student_id', $student->id)->firstOrFail();
        $this->assertStringEndsWith('.png', $enrollment->photo_1_path);
        Storage::disk('local')->assertExists($enrollment->photo_1_path);
        Storage::disk('local')->assertExists($enrollment->photo_2_path);
        Storage::disk('local')->assertExists($enrollment->photo_3_path);
    }

    public function test_pendaftaran_menerima_upload_multipart_file_jpg_dan_png_valid(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $jpgFile = UploadedFile::fake()->createWithContent('wajah1.jpg', $this->validJpegBinary());
        $pngFile = UploadedFile::fake()->createWithContent('wajah2.png', $this->validPngBinary());
        $jpgFile3 = UploadedFile::fake()->createWithContent('wajah3.jpg', $this->validJpegBinary());

        $response = $this->post('/pendaftaran', $this->pendaftaranPayload([
            'nisn' => '0099887755',
            'card_uid' => 'CARD-SEC-002',
            'fingerprint_id' => 'FP-SEC-002',
            'photos' => [$jpgFile, $pngFile, $jpgFile3],
        ]), ['Accept' => 'application/json']);

        $response->assertOk();
        $student = Student::where('nisn', '0099887755')->firstOrFail();
        $enrollment = FaceEnrollment::where('student_id', $student->id)->firstOrFail();
        $this->assertStringEndsWith('.jpg', $enrollment->photo_1_path);
        $this->assertStringEndsWith('.png', $enrollment->photo_2_path);
        Storage::disk('local')->assertExists($enrollment->photo_2_path);
    }

    public function test_pendaftaran_menolak_payload_mime_palsu_text_svg_dan_mislabeled_png(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $validPngDataUrl = 'data:image/png;base64,'.base64_encode($this->validPngBinary());
        $fakeTextAsJpeg = 'data:image/jpeg;base64,'.base64_encode(str_repeat('not-an-image-', 20));
        $fakeSvgAsPng = 'data:image/png;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $pngMislabeledAsJpeg = 'data:image/jpeg;base64,'.base64_encode($this->validPngBinary());

        foreach ([$fakeTextAsJpeg, $fakeSvgAsPng, $pngMislabeledAsJpeg] as $idx => $badPhoto) {
            $response = $this->postJson('/pendaftaran', $this->pendaftaranPayload([
                'nisn' => '009988771'.$idx,
                'card_uid' => 'CARD-BAD-'.$idx,
                'photos' => [$badPhoto, $validPngDataUrl, $validPngDataUrl],
            ]));

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['photos.0']);
        }

        $this->assertDatabaseMissing('students', ['nisn' => '0099887710']);
        $this->assertSame(0, FaceEnrollment::count());
    }

    public function test_pendaftaran_dan_face_enrollment_menolak_foto_melebihi_2mb(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');

        $oversizedJpeg = 'data:image/jpeg;base64,'.base64_encode(
            "\xFF\xD8\xFF\xE0\x00\x10JFIF".str_repeat('A', (2 * 1024 * 1024) + 512)."\xFF\xD9"
        );
        $validPngDataUrl = 'data:image/png;base64,'.base64_encode($this->validPngBinary());

        $regResponse = $this->postJson('/pendaftaran', $this->pendaftaranPayload([
            'nisn' => '0099887799',
            'card_uid' => 'CARD-BIG-001',
            'photos' => [$oversizedJpeg, $validPngDataUrl, $validPngDataUrl],
        ]));
        $regResponse->assertUnprocessable();
        $regResponse->assertJsonValidationErrors(['photos.0']);

        $siswaUser = User::factory()->create(['role' => 'siswa']);
        $student = Student::firstOrFail();
        $student->update(['user_id' => $siswaUser->id]);
        $siswaUser->update(['student_id' => $student->id]);
        $enrollResponse = $this->actingAs($siswaUser)->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [$oversizedJpeg, $validPngDataUrl, $validPngDataUrl],
        ]);
        $enrollResponse->assertUnprocessable();
        $enrollResponse->assertJson(['success' => false]);
        $this->assertSame(0, FaceEnrollment::count());
    }

    public function test_view_utama_dan_rekap_memuat_preload_lazy_loading_dan_transisi_ui(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $login = $this->get('/login');
        $login->assertOk();
        $login->assertSee('rel="preload"', false);
        $login->assertSee('as="image"', false);

        $dashboard = $this->actingAs($admin)->get('/dashboard');
        $dashboard->assertOk();
        $dashboard->assertSee('rel="preload"', false);

        $students = $this->actingAs($admin)->get('/admin/data-siswa');
        $students->assertOk();
        $students->assertSee('x-transition:enter', false);

        $rekap = $this->actingAs($admin)->get('/guru/rekap-harian');
        $rekap->assertOk();
        $rekap->assertSee('loading="lazy"', false);
        $rekap->assertSee('x-transition:enter', false);

        $pendaftaran = $this->get('/pendaftaran');
        $pendaftaran->assertOk();
        $pendaftaran->assertSee('loading="lazy"', false);
        $pendaftaran->assertSee('rel="preload"', false);

        $kiosk = $this->get('/kiosk');
        $kiosk->assertOk();
        $kiosk->assertSee('loading="lazy"', false);
        $kiosk->assertSee('rel="preload"', false);
    }
}
