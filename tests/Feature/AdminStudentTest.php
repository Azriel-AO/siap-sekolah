<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FaceEnrollment;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminStudentTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        User::factory()->create(['name' => 'Admin KNBI', 'email' => 'admin@knbi.sch.id', 'role' => 'admin']);
        User::factory()->create(['name' => 'Bu Sari', 'email' => 'guru@knbi.sch.id', 'role' => 'guru']);
        User::factory()->create(['name' => 'Raka Siswa', 'email' => 'siswa@knbi.sch.id', 'role' => 'siswa']);
        $this->seed(DemoAttendanceSeeder::class);
    }

    public function test_hapus_siswa_tanpa_absensi_bersih(): void
    {
        $this->seedDemo();
        $admin = User::where('email', 'admin@knbi.sch.id')->first();
        $class = SchoolClass::where('slug', 'X-TKJ-1')->first();

        // Buat siswa baru tanpa absensi tapi punya kartu dan akun user
        $user = User::create([
            'name' => 'Siswa Bersih',
            'email' => '99999999@knbi.sch.id',
            'password' => bcrypt('password'),
            'role' => 'siswa',
        ]);

        $student = Student::create([
            'school_class_id' => $class->id,
            'user_id' => $user->id,
            'nis' => '99999999',
            'nisn' => '0099999999',
            'name' => 'Siswa Bersih',
            'gender' => 'L',
        ]);

        $card = RfidCard::create([
            'student_id' => $student->id,
            'card_uid' => 'CARD-BERSIH-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->delete("/admin/data-siswa/{$student->id}");

        $response->assertRedirect('/admin/data-siswa?kelas=X-TKJ-1');
        $response->assertSessionHas('status', 'Data siswa "Siswa Bersih" berhasil dihapus.');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('rfid_cards', ['id' => $card->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_hapus_siswa_dengan_absensi_ikut_hilang_tanpa_error_fk(): void
    {
        $this->seedDemo();
        $admin = User::where('email', 'admin@knbi.sch.id')->first();
        $class = SchoolClass::where('slug', 'X-TKJ-1')->first();

        $student = Student::create([
            'school_class_id' => $class->id,
            'nis' => '88888888',
            'name' => 'Siswa Berabsensi',
            'gender' => 'L',
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'date' => '2026-11-10',
            'check_in_at' => '06:55:00',
            'status' => 'HADIR',
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'date' => '2026-11-11',
            'check_in_at' => '07:05:00',
            'status' => 'TERLAMBAT',
        ]);

        $this->assertDatabaseHas('attendances', ['student_id' => $student->id]);

        $response = $this->actingAs($admin)
            ->delete("/admin/data-siswa/{$student->id}");

        $response->assertRedirect('/admin/data-siswa?kelas=X-TKJ-1');
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
    }

    public function test_guru_dan_siswa_di_route_hapus_mendapat_403(): void
    {
        $this->seedDemo();
        $guru = User::where('email', 'guru@knbi.sch.id')->first();
        $siswa = User::where('email', 'siswa@knbi.sch.id')->first();
        $student = Student::first();

        // Guru -> 403
        $responseGuru = $this->actingAs($guru)
            ->delete("/admin/data-siswa/{$student->id}");
        $responseGuru->assertStatus(403);
        $this->assertDatabaseHas('students', ['id' => $student->id]);

        // Siswa -> 403
        $responseSiswa = $this->actingAs($siswa)
            ->delete("/admin/data-siswa/{$student->id}");
        $responseSiswa->assertStatus(403);
        $this->assertDatabaseHas('students', ['id' => $student->id]);

        // Tamu tanpa login -> redirect ke login
        auth()->logout();
        $responseGuest = $this->delete("/admin/data-siswa/{$student->id}");
        $responseGuest->assertRedirect('/login');
    }

    public function test_folder_foto_wajah_benar_benar_hilang_dari_storage(): void
    {
        Storage::fake('local');
        $this->seedDemo();
        $admin = User::where('email', 'admin@knbi.sch.id')->first();
        $class = SchoolClass::where('slug', 'X-TKJ-1')->first();

        $student = Student::create([
            'school_class_id' => $class->id,
            'nis' => '77777777',
            'name' => 'Siswa Berfoto',
            'gender' => 'P',
        ]);

        $path1 = "face-photos/{$student->id}/face-{$student->id}-1.jpg";
        $path2 = "face-photos/{$student->id}/face-{$student->id}-2.jpg";
        $path3 = "face-photos/{$student->id}/face-{$student->id}-3.jpg";

        Storage::disk('local')->put($path1, 'fake-jpeg-1');
        Storage::disk('local')->put($path2, 'fake-jpeg-2');
        Storage::disk('local')->put($path3, 'fake-jpeg-3');

        FaceEnrollment::create([
            'student_id' => $student->id,
            'photo_1_path' => $path1,
            'photo_2_path' => $path2,
            'photo_3_path' => $path3,
            'consented_at' => now(),
        ]);

        $this->assertTrue(Storage::disk('local')->exists($path1));
        $this->assertTrue(Storage::disk('local')->exists($path2));
        $this->assertTrue(Storage::disk('local')->exists($path3));

        $response = $this->actingAs($admin)
            ->delete("/admin/data-siswa/{$student->id}");

        $response->assertRedirect('/admin/data-siswa?kelas=X-TKJ-1');
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('face_enrollments', ['student_id' => $student->id]);

        // Folder benar-benar hilang dari storage
        $this->assertFalse(Storage::disk('local')->exists($path1));
        $this->assertFalse(Storage::disk('local')->exists($path2));
        $this->assertFalse(Storage::disk('local')->exists($path3));
        $this->assertEmpty(Storage::disk('local')->allFiles("face-photos/{$student->id}"));
    }

    public function test_nisn_yang_terhapus_bisa_dipakai_lagi_pendaftaran_baru(): void
    {
        $this->seedDemo();
        Setting::set('mode_pendaftaran_gerbang', '1');

        $admin = User::where('email', 'admin@knbi.sch.id')->first();
        $class = SchoolClass::where('slug', 'X-TKJ-1')->first();

        $nisn = '0088112233';
        $student = Student::create([
            'school_class_id' => $class->id,
            'nis' => '66666666',
            'nisn' => $nisn,
            'name' => 'Siswa Dihapus',
            'gender' => 'L',
        ]);

        // Hapus siswa oleh admin
        $this->actingAs($admin)
            ->delete("/admin/data-siswa/{$student->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('students', ['nisn' => $nisn]);

        // Daftarkan siswa baru memakai NISN yang sama di terminal /pendaftaran
        $fakeJpeg = 'data:image/jpeg;base64,'.base64_encode("\xFF\xD8".str_repeat('a', 150)."\xFF\xD9");

        $response = $this->postJson('/pendaftaran', [
            'name' => 'Siswa Pendaftar Baru',
            'gender' => 'L',
            'school_class_id' => $class->id,
            'nisn' => $nisn,
            'setuju' => true,
            'card_uid' => 'CARD-BARU-001',
            'fingerprint_id' => 'MOCK-NEW-FP',
            'photos' => [$fakeJpeg, $fakeJpeg, $fakeJpeg],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Siswa baru dengan NISN tersebut berhasil dibuat
        $siswaBaru = Student::where('nisn', $nisn)->first();
        $this->assertNotNull($siswaBaru);
        $this->assertSame('Siswa Pendaftar Baru', $siswaBaru->name);
    }

    public function test_accessor_card_label_dan_tampilan_kartu_cantik_di_ui_serta_kiosk(): void
    {
        $this->seedDemo();
        $admin = User::where('email', 'admin@knbi.sch.id')->firstOrFail();

        $kelas = SchoolClass::create([
            'name' => '10 TKJ 1',
            'slug' => '10-TKJ-1',
            'grade' => 10,
            'department' => 'Teknik Komputer dan Jaringan',
            'entry_time' => '07:00:00',
            'exit_time' => '15:00:00',
        ]);

        $s1 = Student::create(['school_class_id' => $kelas->id, 'nis' => '109901', 'name' => 'Siswa Satu', 'gender' => 'L']);
        $s2 = Student::create(['school_class_id' => $kelas->id, 'nis' => '109902', 'name' => 'Siswa Dua', 'gender' => 'P']);
        $s3 = Student::create(['school_class_id' => $kelas->id, 'nis' => '109903', 'name' => 'Siswa Tiga', 'gender' => 'L']);

        // Belum punya kartu -> card_label null
        $this->assertNull($s3->card_label);

        RfidCard::create(['student_id' => $s1->id, 'card_uid' => '0009747961', 'is_active' => true]);
        RfidCard::create(['student_id' => $s3->id, 'card_uid' => '0009747963', 'is_active' => true]);

        $this->assertSame('10-TKJ-1-001', $s1->fresh()->card_label);
        $this->assertNull($s2->fresh()->card_label);
        $this->assertSame('10-TKJ-1-003', $s3->fresh()->card_label);

        // UID fisik mentah di database tidak berubah
        $this->assertDatabaseHas('rfid_cards', [
            'student_id' => $s3->id,
            'card_uid' => '0009747963',
        ]);

        // Halaman Data Siswa menampilkan label cantik dan menyimpan UID mentah di tooltip
        $page = $this->actingAs($admin)->get('/admin/data-siswa?kelas=10-TKJ-1');
        $page->assertOk();
        $page->assertSee('10-TKJ-1-001');
        $page->assertSee('10-TKJ-1-003');
        $page->assertSee('title="UID: 0009747963"', false);

        // Tap kartu di Kiosk mengembalikan card_label dan pesan "Halo, {nama} ({card_label})"
        $tap = $this->postJson('/kiosk/tap', ['card_uid' => '0009747963']);
        $tap->assertOk();
        $tap->assertJson([
            'success' => true,
            'card_label' => '10-TKJ-1-003',
            'message' => 'Halo, Siswa Tiga (10-TKJ-1-003)',
        ]);
    }
}
