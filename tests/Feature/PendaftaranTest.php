<?php

namespace Tests\Feature;

use App\Models\FaceEnrollment;
use App\Models\RfidCard;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PendaftaranTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function fakeJpegDataUrl(string $seed = 'a'): string
    {
        $binary = "\xFF\xD8" . str_repeat($seed, 150) . "\xFF\xD9";

        return 'data:image/jpeg;base64,' . base64_encode($binary);
    }

    private function dataValid(array $override = []): array
    {
        $kelas = \App\Models\SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        return array_merge([
            'name' => 'Siswa Baru Uji Coba',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0099001122',
            'setuju' => true,
            'card_uid' => 'CARD-BARU-001',
            'fingerprint_id' => 'MOCK-FP-BARU-001',
            'photos' => [
                $this->fakeJpegDataUrl('a'),
                $this->fakeJpegDataUrl('b'),
                $this->fakeJpegDataUrl('c'),
            ],
        ], $override);
    }

    public function test_halaman_pendaftaran_selalu_terbuka_untuk_tamu_tanpa_toggle(): void
    {
        $this->seedDemo();

        // Tidak ada toggle admin — pendaftaran selalu terbuka
        $response = $this->get('/pendaftaran');

        $response->assertStatus(200);
        $response->assertSee('Data Diri');
        $response->assertSee('X TKJ 1');
        $response->assertSee('Selesaikan Pendaftaran');
        $response->assertDontSee('Pendaftaran Ditutup');
    }

    public function test_pendaftaran_valid_tersimpan_lengkap_dalam_satu_transaksi(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        $response = $this->postJson('/pendaftaran', $this->dataValid());

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $student = Student::where('nisn', '0099001122')->first();
        $this->assertNotNull($student);
        $this->assertSame('L', $student->gender);
        $this->assertNull($student->user_id, 'Siswa baru tidak boleh punya akun login.');
        $this->assertSame('MOCK-FP-BARU-001', $student->fingerprint_id);

        $kartu = RfidCard::where('card_uid', 'CARD-BARU-001')->first();
        $this->assertNotNull($kartu);
        $this->assertSame($student->id, $kartu->student_id);

        $enrollment = FaceEnrollment::where('student_id', $student->id)->first();
        $this->assertNotNull($enrollment);
        Storage::disk('local')->assertExists($enrollment->photo_1_path);
        Storage::disk('local')->assertExists($enrollment->photo_2_path);
        Storage::disk('local')->assertExists($enrollment->photo_3_path);
    }

    public function test_nisn_duplikat_ditolak_dengan_pesan_indonesia(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        Student::where('nis', '10240101')->update(['nisn' => '0099001122']);

        $response = $this->postJson('/pendaftaran', $this->dataValid());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nisn']);
        $this->assertDatabaseMissing('rfid_cards', ['card_uid' => 'CARD-BARU-001']);
    }

    public function test_kartu_yang_dipakai_siswa_lain_ditolak(): void
    {
        $this->seedDemo();
        Storage::fake('local');

        // CARD-TKJ-001 adalah kartu milik Raka Pratama (seed demo)
        $response = $this->postJson('/pendaftaran', $this->dataValid(['card_uid' => 'CARD-TKJ-001']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['card_uid']);
        $this->assertStringContainsString('sudah dipakai', $response->json('errors.card_uid.0'));

        // Data tidak boleh setengah tersimpan
        $this->assertDatabaseMissing('students', ['nisn' => '0099001122']);
        $this->assertDatabaseMissing('face_enrollments', []);
    }

    public function test_tanpa_persetujuan_biometrik_ditolak(): void
    {
        $this->seedDemo();

        $response = $this->postJson('/pendaftaran', $this->dataValid(['setuju' => false]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['setuju']);
        $this->assertDatabaseMissing('students', ['nisn' => '0099001122']);
    }

    public function test_foto_kurang_dari_3_ditolak(): void
    {
        $this->seedDemo();

        $response = $this->postJson('/pendaftaran', $this->dataValid([
            'photos' => [$this->fakeJpegDataUrl('a'), $this->fakeJpegDataUrl('b')],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['photos']);
        $this->assertDatabaseMissing('students', ['nisn' => '0099001122']);
    }

    public function test_halaman_berhasil_menampilkan_nama_dan_kelas(): void
    {
        $this->seedDemo();

        $response = $this->withSession(['nama' => 'Intan Permata', 'kelas' => 'X TKJ 1'])
            ->get('/pendaftaran/berhasil');

        $response->assertStatus(200);
        $response->assertSee('Intan Permata');
        $response->assertSee('X TKJ 1');
        $response->assertSee('Kartu aktif');
    }
}
