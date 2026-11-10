<?php

namespace Tests\Feature;

use App\Models\FaceEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function siswa(): User
    {
        return User::where('email', 'siswa@knbi.sch.id')->firstOrFail();
    }

    private function guru(): User
    {
        return User::where('email', 'guru@knbi.sch.id')->firstOrFail();
    }

    /**
     * Buat data URL JPEG kecil yang sah (SOI FFD8 ... EOI FFD9, >= 100 B).
     */
    private function fakeJpegDataUrl(string $seed = 'a'): string
    {
        $binary = "\xFF\xD8" . str_repeat($seed, 150) . "\xFF\xD9";

        return 'data:image/jpeg;base64,' . base64_encode($binary);
    }

    public function test_halaman_pendaftaran_wajah_terbuka_untuk_siswa(): void
    {
        $this->seedDemo();

        $response = $this->actingAs($this->siswa())->get('/siswa/pendaftaran-wajah');

        $response->assertStatus(200);
        $response->assertSee('Pendaftaran Wajah');
    }

    public function test_halaman_pendaftaran_wajah_ditolak_untuk_guru(): void
    {
        $this->seedDemo();

        $response = $this->actingAs($this->guru())->get('/siswa/pendaftaran-wajah');

        $response->assertStatus(403);
    }

    public function test_halaman_pendaftaran_wajah_mengalihkan_tamu_ke_login(): void
    {
        $this->seedDemo();

        $response = $this->get('/siswa/pendaftaran-wajah');

        $response->assertRedirect('/login');
    }

    public function test_simpan_3_foto_valid_berhasil_dan_tersimpan_di_privat(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $response = $this->actingAs($this->siswa())->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $this->fakeJpegDataUrl('b'),
                $this->fakeJpegDataUrl('c'),
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $student = Student::where('user_id', $this->siswa()->id)->firstOrFail();

        $enrollment = FaceEnrollment::where('student_id', $student->id)->first();
        $this->assertNotNull($enrollment, 'Baris pendaftaran wajah harus tersimpan di database.');

        foreach ([$enrollment->photo_1_path, $enrollment->photo_2_path, $enrollment->photo_3_path] as $path) {
            $this->assertStringStartsWith('face-photos/', $path);
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_simpan_2_foto_ditolak_dengan_pesan_indonesia(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $response = $this->actingAs($this->siswa())->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $this->fakeJpegDataUrl('b'),
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('3 foto', $response->json('message'));
        $this->assertDatabaseCount('face_enrollments', 0);
    }

    public function test_simpan_tanpa_persetujuan_ditolak(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $response = $this->actingAs($this->siswa())->postJson('/siswa/pendaftaran-wajah', [
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $this->fakeJpegDataUrl('b'),
                $this->fakeJpegDataUrl('c'),
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('izin orang tua/wali', $response->json('message'));
        $this->assertDatabaseCount('face_enrollments', 0);
    }

    public function test_foto_bukan_jpeg_sah_ditolak_tanpa_file_tersimpan(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $bukanJpeg = 'data:image/jpeg;base64,' . base64_encode(str_repeat('x', 200)); // tanpa SOI/EOI JPEG

        $response = $this->actingAs($this->siswa())->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $bukanJpeg,
                $this->fakeJpegDataUrl('c'),
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseCount('face_enrollments', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('face-photos'), 'Tidak boleh ada file nyasar meski validasi gagal.');
    }
}
