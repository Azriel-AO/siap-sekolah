<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FaceEnrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\FaceTemplateService;
use Carbon\Carbon;
use Database\Seeders\DemoAttendanceSeeder;
use Database\Seeders\SchoolClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Task48FaceAndClassMergeTest extends TestCase
{
    use RefreshDatabase;

    private function fakeJpegDataUrl(string $seed = 'a'): string
    {
        $binary = "\xFF\xD8".str_repeat($seed, 150)."\xFF\xD9";

        return 'data:image/jpeg;base64,'.base64_encode($binary);
    }

    /**
     * @return list<float>
     */
    private function fakeVector(float $base = 0.25): array
    {
        $vec = [];
        for ($i = 0; $i < 1434; $i++) {
            $vec[] = round($base + (($i % 7) - 3) * 0.01, 6);
        }

        return $vec;
    }

    public function test_pendaftaran_menyimpan_face_template_asli_ke_storage_dan_dikembalikan_oleh_kiosk(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        Storage::fake('local');
        Cache::flush();

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $vector = $this->fakeVector(0.31);

        $response = $this->postJson('/pendaftaran', [
            'name' => 'Siswa Procrustes Asli',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0099887766',
            'setuju' => true,
            'card_uid' => 'CARD-T48-001',
            'fingerprint_id' => 'MOCK-FP-T48-001',
            'photos' => [
                $this->fakeJpegDataUrl('p'),
                $this->fakeJpegDataUrl('q'),
                $this->fakeJpegDataUrl('r'),
            ],
            'face_template' => $vector,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $student = Student::where('nisn', '0099887766')->firstOrFail();
        Storage::disk('local')->assertExists("face-photos/{$student->id}/template.json");
        Storage::disk('local')->assertExists("face-photos/{$student->id}/template_meta.json");

        $meta = json_decode((string) Storage::disk('local')->get("face-photos/{$student->id}/template_meta.json"), true);
        $this->assertSame(FaceTemplateService::TEMPLATE_VERSION, $meta['version'] ?? null);

        $admin = User::factory()->create(['role' => 'admin']);
        $templatesRes = $this->actingAs($admin)->getJson('/kiosk/face-templates');
        $templatesRes->assertOk();

        $matchedEntry = collect($templatesRes->json())->firstWhere('student_id', $student->id);
        $this->assertNotNull($matchedEntry);
        $this->assertEquals($vector, $matchedEntry['template']);
        $this->assertArrayNotHasKey('needs_extraction', $matchedEntry);
    }

    public function test_face_enrollment_simpan_face_template_dan_hapus_template_usang_saat_daftar_ulang(): void
    {
        User::factory()->create(['name' => 'Admin KNBI', 'email' => 'admin@knbi.sch.id', 'role' => 'admin']);
        User::factory()->create(['name' => 'Raka Siswa', 'email' => 'siswa@knbi.sch.id', 'role' => 'siswa']);
        $this->seed(DemoAttendanceSeeder::class);

        Storage::fake('local');
        Cache::flush();

        $siswaUser = User::where('email', 'siswa@knbi.sch.id')->firstOrFail();
        $student = Student::where('user_id', $siswaUser->id)->first() ?? Student::firstOrFail();
        $vector = $this->fakeVector(0.42);

        $firstRes = $this->actingAs($siswaUser)->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('1'),
                $this->fakeJpegDataUrl('2'),
                $this->fakeJpegDataUrl('3'),
            ],
            'face_template' => $vector,
        ]);

        $firstRes->assertOk()->assertJson(['success' => true]);
        Storage::disk('local')->assertExists("face-photos/{$student->id}/template.json");
        Storage::disk('local')->assertExists("face-photos/{$student->id}/template_meta.json");

        // Daftar ulang tanpa face_template -> template usang wajib dibersihkan agar kiosk hidrasi ulang dari 3 foto baru
        $reenrollRes = $this->actingAs($siswaUser)->postJson('/siswa/pendaftaran-wajah', [
            'consent' => true,
            'photos' => [
                $this->fakeJpegDataUrl('4'),
                $this->fakeJpegDataUrl('5'),
                $this->fakeJpegDataUrl('6'),
            ],
        ]);

        $reenrollRes->assertOk()->assertJson(['success' => true]);
        Storage::disk('local')->assertMissing("face-photos/{$student->id}/template.json");
        Storage::disk('local')->assertMissing("face-photos/{$student->id}/template_meta.json");

        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();
        $kioskTemplates = $this->actingAs($admin)->getJson('/kiosk/face-templates')->assertOk()->json();
        $entry = collect($kioskTemplates)->firstWhere('student_id', $student->id);
        $this->assertNotNull($entry);
        $this->assertNull($entry['template']);
        $this->assertTrue($entry['needs_extraction'] ?? false);
        $this->assertCount(3, $entry['face_photos'] ?? []);
    }

    public function test_kiosk_face_templates_meminta_ekstraksi_foto_lama_dan_sync_melindungi_dari_overwrite(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('local');
        Cache::flush();

        $student = Student::where('nis', '10240109')->firstOrFail();
        $jpgBinary = "\xFF\xD8".str_repeat('z', 200)."\xFF\xD9";
        $paths = [
            "face-photos/{$student->id}/face-1.jpg",
            "face-photos/{$student->id}/face-2.jpg",
            "face-photos/{$student->id}/face-3.jpg",
        ];
        foreach ($paths as $path) {
            Storage::disk('local')->put($path, $jpgBinary);
        }

        // Simulasikan template.json palsu lama tanpa template_meta.json
        Storage::disk('local')->put("face-photos/{$student->id}/template.json", json_encode($this->fakeVector(0.99)));

        FaceEnrollment::create([
            'student_id' => $student->id,
            'photo_1_path' => $paths[0],
            'photo_2_path' => $paths[1],
            'photo_3_path' => $paths[2],
            'consented_at' => now(),
        ]);

        $res = $this->actingAs($admin)->getJson('/kiosk/face-templates')->assertOk();
        $entry = collect($res->json())->firstWhere('student_id', $student->id);
        $this->assertNotNull($entry);
        $this->assertNull($entry['template']);
        $this->assertTrue($entry['needs_extraction'] ?? false);
        $this->assertCount(3, $entry['face_photos'] ?? []);

        // Tolak vektor nol atau panjang salah
        $this->actingAs($admin)->postJson('/kiosk/face-templates', [
            'student_id' => $student->id,
            'status' => 'ok',
            'template' => array_fill(0, 1434, 0.0),
        ])->assertStatus(422);

        $realVec = $this->fakeVector(0.18);
        $this->actingAs($admin)->postJson('/kiosk/face-templates', [
            'student_id' => $student->id,
            'status' => 'ok',
            'template' => $realVec,
        ])->assertOk()->assertJson(['ok' => true, 'status' => 'ok']);

        // Permintaan kedua tidak boleh menimpa template v2 yang sudah tersimpan
        $tamperVec = $this->fakeVector(0.88);
        $this->actingAs($admin)->postJson('/kiosk/face-templates', [
            'student_id' => $student->id,
            'status' => 'ok',
            'template' => $tamperVec,
        ])->assertOk()->assertJson(['ok' => true, 'already_synced' => true]);

        $afterSync = $this->actingAs($admin)->getJson('/kiosk/face-templates')->assertOk();
        $syncedEntry = collect($afterSync->json())->firstWhere('student_id', $student->id);
        $this->assertEquals($realVec, $syncedEntry['template']);
    }

    public function test_migrasi_ps_ke_psy_menggabungkan_duplikat_dan_rename_tanpa_kehilangan_data(): void
    {
        // Kasus 1: Kelas typo "11 PS 1" berdampingan dengan "11 PSY 1" -> semua siswa & absensi dipindahkan lalu "11 PS 1" dihapus
        $ps11 = SchoolClass::create([
            'name' => '11 PS 1',
            'slug' => '11-PS-1',
            'department' => 'Perbankan Syariah',
            'wali_kelas' => 'Bu Dina, S.E.',
            'entry_time' => '07:00:00',
            'exit_time' => '15:30:00',
        ]);
        $psy11 = SchoolClass::create([
            'name' => '11 PSY 1',
            'slug' => '11-PSY-1',
            'department' => 'Perbankan Syariah',
            'wali_kelas' => null,
            'entry_time' => '07:00:00',
            'exit_time' => '15:30:00',
        ]);

        // Kasus 2: Kelas typo "10 PS 1" tanpa target "10 PSY 1" -> di-rename in-place ke "10 PSY 1"
        $ps10 = SchoolClass::create([
            'name' => '10 PS 1',
            'slug' => '10-PS-1',
            'department' => 'Perbankan Syariah',
            'entry_time' => '07:00:00',
            'exit_time' => '15:30:00',
        ]);

        $stuA = Student::create([
            'school_class_id' => $ps11->id,
            'nis' => '11001',
            'nisn' => '0011001101',
            'name' => 'Siswa PS 11 A',
            'gender' => 'L',
        ]);
        $stuB = Student::create([
            'school_class_id' => $psy11->id,
            'nis' => '11002',
            'nisn' => '0011001102',
            'name' => 'Siswa PSY 11 B',
            'gender' => 'P',
        ]);
        $stu10 = Student::create([
            'school_class_id' => $ps10->id,
            'nis' => '10001',
            'nisn' => '0010001101',
            'name' => 'Siswa PS 10',
            'gender' => 'P',
        ]);

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $att11 = Attendance::create([
            'student_id' => $stuA->id,
            'school_class_id' => $ps11->id,
            'date' => $today,
            'check_in_at' => '06:55:00',
            'status' => 'HADIR',
        ]);
        $att10 = Attendance::create([
            'student_id' => $stu10->id,
            'school_class_id' => $ps10->id,
            'date' => $today,
            'check_in_at' => '07:10:00',
            'status' => 'TERLAMBAT',
        ]);

        $migration = require database_path('migrations/2026_11_17_073347_merge_duplicate_ps_classes_to_psy.php');
        $migration->up();

        $this->assertDatabaseMissing('school_classes', ['id' => $ps11->id]);
        $this->assertDatabaseHas('school_classes', [
            'id' => $psy11->id,
            'name' => '11 PSY 1',
            'slug' => '11-PSY-1',
            'wali_kelas' => 'Bu Dina, S.E.',
        ]);
        $this->assertDatabaseHas('school_classes', [
            'id' => $ps10->id,
            'name' => '10 PSY 1',
            'slug' => '10-PSY-1',
        ]);

        $this->assertSame($psy11->id, (int) $stuA->fresh()->school_class_id);
        $this->assertSame($psy11->id, (int) $stuB->fresh()->school_class_id);
        $this->assertSame($ps10->id, (int) $stu10->fresh()->school_class_id);

        $this->assertSame($psy11->id, (int) $att11->fresh()->school_class_id);
        $this->assertSame($ps10->id, (int) $att10->fresh()->school_class_id);
        $this->assertSame(3, Student::count());
        $this->assertSame(2, Attendance::count());

        // Pastikan config & seeder hanya memakai PSY dan tidak mengandung kode jurusan PS
        $seederClasses = SchoolClassSeeder::daftarKelasSmkKnbi();
        $this->assertCount(39, $seederClasses);
        $psMatches = collect($seederClasses)->filter(
            fn (array $item): bool => preg_match('/\bPS\b/i', $item['name']) === 1
        );
        $this->assertCount(0, $psMatches);
    }
}
