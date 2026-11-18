<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DebugModeAndDeviceStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('email', 'admin@knbi.sch.id')->first()
            ?? User::factory()->create(['email' => 'admin@knbi.sch.id', 'role' => 'admin']);
    }

    public function test_is_debug_mode_mengembalikan_true_atau_false_sesuai_env_dan_config(): void
    {
        // Default pada phpunit.xml adalah DEBUG_MODE=false
        $this->assertFalse((bool) config('app.debug_mode'));
        $this->assertFalse(isDebugMode());

        config(['app.debug' => false, 'app.debug_mode' => true]);
        $this->assertFalse(isDebugMode(), 'isDebugMode wajib false ketika APP_DEBUG bernilai false.');

        config(['app.debug' => true, 'app.debug_mode' => true]);
        $this->assertTrue(isDebugMode());

        config(['app.debug' => true, 'app.debug_mode' => false]);
        $this->assertFalse(isDebugMode());

        $this->assertStringContainsString('DEBUG_MODE=true', file_get_contents(base_path('.env.example')));
    }

    public function test_semua_fitur_debug_hilang_total_saat_debug_mode_false(): void
    {
        $this->seed([DatabaseSeeder::class, DemoAttendanceSeeder::class]);
        config(['app.debug_mode' => false]);

        $student = Student::firstOrFail();

        $pendaftaran = $this->get(route('pendaftaran.index'));
        $pendaftaran->assertOk();
        $pendaftaran->assertDontSee('Lewati Biometrik (Pakai Data Dummy)');

        $kiosk = $this->get(route('kiosk.gerbang'));
        $kiosk->assertOk();
        $kiosk->assertDontSee('Simulasi Absensi');
        $kiosk->assertDontSee('Simulasi Absen');
        $kiosk->assertDontSee(route('kiosk.debug.simulate'), false);

        $statusSistem = $this->actingAs($this->admin())->get(route('admin.status-sistem'));
        $statusSistem->assertOk();
        $statusSistem->assertDontSee('>DEBUG<', false);

        $simulate = $this->postJson(route('kiosk.debug.simulate'), [
            'student_id' => $student->id,
        ]);
        $simulate->assertStatus(404);
    }

    public function test_ui_debug_tampil_saat_debug_mode_true(): void
    {
        $this->seed([DatabaseSeeder::class, DemoAttendanceSeeder::class]);
        config(['app.debug' => true, 'app.debug_mode' => true]);

        $student = Student::orderBy('name')->firstOrFail();

        $pendaftaran = $this->get(route('pendaftaran.index'));
        $pendaftaran->assertOk();
        $pendaftaran->assertSee('Lewati Biometrik (Pakai Data Dummy)');

        $kiosk = $this->get(route('kiosk.gerbang'));
        $kiosk->assertOk();
        $kiosk->assertSee('Simulasi Absensi');
        $kiosk->assertSee('Simulasi Absen');
        $kiosk->assertSee($student->name);

        $statusSistem = $this->actingAs($this->admin())->get(route('admin.status-sistem'));
        $statusSistem->assertOk();
        $statusSistem->assertSee('DEBUG');
    }

    public function test_endpoint_simulasi_absensi_hanya_aktif_saat_debug_mode_true_dan_memakai_proses_absensi(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['app.debug' => true, 'app.debug_mode' => true]);

        $kelas = SchoolClass::firstOrFail();
        $student = Student::create([
            'school_class_id' => $kelas->id,
            'nis' => '99887766',
            'nisn' => '0099887766',
            'name' => 'Siswa Debug Simulasi',
            'gender' => 'L',
        ]);

        $response = $this->postJson(route('kiosk.debug.simulate'), [
            'student_id' => $student->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'type' => 'check_in',
            'method' => 'debug',
            'student_name' => 'Siswa Debug Simulasi',
        ]);

        $attendance = Attendance::where('student_id', $student->id)
            ->where('date', Carbon::now('Asia/Jakarta')->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame('debug', $attendance->kiosk_device);
    }

    public function test_status_sistem_menampilkan_rfid_standby_dan_tombol_tes_tanpa_penalti_24_jam(): void
    {
        $this->seed([DatabaseSeeder::class, DemoAttendanceSeeder::class]);
        Attendance::query()->delete();

        $admin = $this->admin();

        // Walaupun tidak ada tap kartu sama sekali, RFID Reader tetap Standby (bukan "Mati" atau "Rusak")
        $responseKosong = $this->actingAs($admin)->get(route('admin.status-sistem'));
        $responseKosong->assertOk();
        $responseKosong->assertSee('Siap / Standby (Menunggu Tap)');
        $responseKosong->assertSee('Tes RFID');
        $responseKosong->assertSee('Terhubung / Berfungsi');
        $responseKosong->assertSee('Standby (Belum ada tap yang terdeteksi)');
        $responseKosong->assertSee('Semua Layanan Operasional');
        $responseKosong->assertSee('100.0%');
        $responseKosong->assertSee('enumerateDevices');
        $responseKosong->assertDontSee('RFID Reader: Belum terdeteksi');
        $responseKosong->assertDontSee('Belum ada tap kartu dalam 24 jam terakhir.');
    }

    public function test_granular_debug_mode_bypass_satu_device_tidak_menimpa_data_asli_device_lain(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        config(['app.debug' => true, 'app.debug_mode' => true]);

        $kelas = SchoolClass::firstOrFail();
        $realPhoto1 = generateDummyJpegDataUrl('REAL-CAMERA-PHOTO-1');

        // User memakai kartu RFID asli ('REAL-CARD-777') dan 1 foto kamera asli,
        // lalu mengaktifkan bypass_fingerprint dan bypass_camera (hanya untuk 2 slot foto sisanya).
        $response = $this->postJson(route('pendaftaran.simpan'), [
            'name' => 'Siswa Granular Debug',
            'gender' => 'L',
            'school_class_id' => $kelas->id,
            'nisn' => '0077889900',
            'setuju' => true,
            'card_uid' => 'REAL-CARD-777',
            'fingerprint_id' => '',
            'photos' => [$realPhoto1, null, null],
            'bypass_camera' => true,
            'bypass_fingerprint' => true,
            'bypass_rfid' => false,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $student = Student::where('nisn', '0077889900')->with(['rfidCard', 'faceEnrollment'])->firstOrFail();

        // Kartu RFID asli TIDAK tertimpa oleh dummy
        $this->assertSame('REAL-CARD-777', $student->rfidCard->card_uid);

        // Fingerprint terisi oleh bypass karena kosong
        $this->assertNotNull($student->fingerprint_id);
        $this->assertStringStartsWith('DBG-FP-', $student->fingerprint_id);

        // Foto slot 1 tetap memakai binary foto asli (REAL-CAMERA-PHOTO-1), tidak ditimpa dummy
        $savedPhoto1Binary = Storage::disk('local')->get($student->faceEnrollment->photo_1_path);
        $this->assertStringContainsString('REAL-CAMERA-PHOTO-1', (string) $savedPhoto1Binary);
    }
}
