<?php

namespace Tests\Feature;

use App\Models\FaceEnrollment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        User::factory()->create(['name' => 'Admin KNBI', 'email' => 'admin@knbi.sch.id', 'role' => 'admin']);
        User::factory()->create(['name' => 'Bu Sari', 'email' => 'guru@knbi.sch.id', 'role' => 'guru']);
        User::factory()->create(['name' => 'Raka Siswa', 'email' => 'siswa@knbi.sch.id', 'role' => 'siswa']);
        $this->seed(DemoAttendanceSeeder::class);
    }

    private function siswa(): User
    {
        return User::where('email', 'siswa@knbi.sch.id')->firstOrFail();
    }

    private function guru(): User
    {
        return User::where('email', 'guru@knbi.sch.id')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@knbi.sch.id')->firstOrFail();
    }

    /**
     * Buat data URL JPEG kecil yang sah (SOI FFD8 ... EOI FFD9, >= 100 B).
     */
    private function fakeJpegDataUrl(string $seed = 'a'): string
    {
        $binary = "\xFF\xD8".str_repeat($seed, 150)."\xFF\xD9";

        return 'data:image/jpeg;base64,'.base64_encode($binary);
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

    public function test_daftar_ulang_mengganti_foto_lama_dan_tetap_satu_baris(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $siswa = $this->siswa();

        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->actingAs($siswa)->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('x'),
                $this->fakeJpegDataUrl('y'),
                $this->fakeJpegDataUrl('z'),
            ],
        ])->assertStatus(200);

        $sebelum = FaceEnrollment::firstOrFail();
        $pathLama = [$sebelum->photo_1_path, $sebelum->photo_2_path, $sebelum->photo_3_path];
        $this->assertNull($sebelum->last_reenrolled_at);

        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->actingAs($siswa)->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('p'),
                $this->fakeJpegDataUrl('q'),
                $this->fakeJpegDataUrl('r'),
            ],
        ])->assertStatus(200)->assertJson(['success' => true]);
        Carbon::setTestNow();

        $this->assertDatabaseCount('face_enrollments', 1);

        $sesudah = FaceEnrollment::firstOrFail();
        $pathBaru = [$sesudah->photo_1_path, $sesudah->photo_2_path, $sesudah->photo_3_path];

        foreach ($pathLama as $old) {
            Storage::disk('local')->assertMissing($old);
        }
        foreach ($pathBaru as $new) {
            Storage::disk('local')->assertExists($new);
        }

        $this->assertNotNull($sesudah->last_reenrolled_at, 'Tanggal daftar ulang terakhir harus terisi.');
    }

    public function test_status_endpoint_mengembalikan_terdaftar_beserta_foto_milik_sendiri(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $siswa = $this->siswa();

        $this->actingAs($siswa)->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $this->fakeJpegDataUrl('b'),
                $this->fakeJpegDataUrl('c'),
            ],
        ])->assertStatus(200);

        $response = $this->actingAs($siswa)->getJson('/siswa/pendaftaran-wajah/status');

        $response->assertStatus(200);
        $response->assertJson([
            'terdaftar' => true,
            'status_label' => 'Terdaftar',
        ]);
        $this->assertCount(3, $response->json('photos'));
        foreach ($response->json('photos') as $photo) {
            $this->assertStringStartsWith('data:image/jpeg;base64,', $photo);
        }
    }

    public function test_rekap_guru_mengembalikan_badge_wajah_per_siswa(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        // Daftarkan wajah hanya untuk akun siswa (Raka Pratama, tertaut siswa@knbi.sch.id)
        $this->actingAs($this->siswa())->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $this->fakeJpegDataUrl('b'),
                $this->fakeJpegDataUrl('c'),
            ],
        ])->assertStatus(200);

        $response = $this->actingAs($this->admin())->getJson('/guru/rekap-harian/data');

        $response->assertStatus(200);
        $students = collect($response->json('students'));

        $raka = $students->firstWhere('nis', '10240101');
        $hendra = $students->firstWhere('nis', '10240109');

        $this->assertSame('TERDAFTAR', $raka['face']);
        $this->assertSame('BELUM', $hendra['face']);
    }

    public function test_pendaftaran_tanpa_file_fisik_dianggap_belum(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $this->actingAs($this->siswa())->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $this->fakeJpegDataUrl('b'),
                $this->fakeJpegDataUrl('c'),
            ],
        ])->assertStatus(200);

        // Simulasikan file foto terhapus tidak sengaja dari storage
        $enrollment = FaceEnrollment::firstOrFail();
        Storage::disk('local')->delete($enrollment->photo_2_path);

        $response = $this->actingAs($this->admin())->getJson('/guru/rekap-harian/data');

        $response->assertStatus(200);
        $raka = collect($response->json('students'))->firstWhere('nis', '10240101');
        $this->assertSame('BELUM', $raka['face']);
    }

    public function test_foto_bukan_jpeg_sah_ditolak_tanpa_file_tersimpan(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $bukanJpeg = 'data:image/jpeg;base64,'.base64_encode(str_repeat('x', 200)); // tanpa SOI/EOI JPEG

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
