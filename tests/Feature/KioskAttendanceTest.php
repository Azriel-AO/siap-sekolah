<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KioskAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Siapkan data demo (3 akun pengguna, kelas X TKJ 1, 10 siswa, kartu, absensi parsial).
     */
    protected function seedDemo(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    public function test_halaman_kiosk_gerbang_dapat_dibuka(): void
    {
        $this->seedDemo();

        $response = $this->get('/kiosk');

        $response->assertStatus(200);
        $response->assertSee('Absen Gerbang');
        $response->assertDontSee('login');
        $response->assertDontSee('dashboard');
    }

    public function test_route_kiosk_lama_redirect_301_ke_gerbang(): void
    {
        $this->seedDemo();

        $response = $this->get('/kiosk/X-TKJ-1');

        $response->assertStatus(301);
        $response->assertRedirect('/kiosk');
    }

    public function test_tap_kartu_tidak_dikenali_ditolak(): void
    {
        $this->seedDemo();

        $response = $this->postJson('/kiosk/tap', [
            'card_uid' => 'CARD-TIDAK-ADA',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'type' => 'not_found',
            'message' => 'Kartu tidak dikenal. Silakan daftar dulu.',
        ]);
        $response->assertSee('/pendaftaran');
    }

    public function test_tap_pertama_mencatat_absen_masuk_dengan_jam_server(): void
    {
        $this->seedDemo();

        // Siswa ke-9 (Hendra) belum absen hari ini
        $response = $this->postJson('/kiosk/tap', [
            'card_uid' => 'CARD-TKJ-009',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'type' => 'check_in',
            'nis' => '10240109',
        ]);

        // Record kehadiran dibuat dengan tanggal hari ini dari server
        $attendance = Attendance::where('date', Carbon::now('Asia/Jakarta')->toDateString())
            ->whereHas('student', fn ($q) => $q->where('nis', '10240109'))
            ->first();

        $this->assertNotNull($attendance, 'Record kehadiran hari ini harus tercatat.');
        $this->assertContains($attendance->status, ['HADIR', 'TERLAMBAT']);
        $this->assertSame(
            $response->json('time'),
            $attendance->check_in_at,
            'Jam masuk harus berasal dari jam server (Konstitusi II / R7).'
        );
    }

    public function test_tap_ganda_dalam_60_detik_ditolak_cooldown(): void
    {
        $this->seedDemo();

        // Tap pertama diterima
        $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-010']);

        // Tap kedua langsung (kurang dari 60 detik) ditolak R3
        $response = $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-010']);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'type' => 'cooldown',
        ]);
    }

    public function test_tap_kedua_setelah_cooldown_mencatat_absen_pulang(): void
    {
        $this->seedDemo();

        $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-009']);

        // Mundurkan waktu tap terakhir 2 menit agar lolos cooldown
        $attendance = Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))->first();
        $attendance->update([
            'last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(2),
        ]);

        $response = $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-009']);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'type' => 'check_out',
        ]);

        $attendance->refresh();
        $this->assertNotNull($attendance->check_out_at, 'Jam pulang harus tercatat.');
    }

    public function test_tap_ketiga_setelah_masuk_dan_pulang_ditolak(): void
    {
        $this->seedDemo();

        $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-009']);
        Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))
            ->update(['last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(2)]);
        $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-009']);

        Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))
            ->update(['last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(2)]);
        $response = $this->postJson('/kiosk/tap', ['card_uid' => 'CARD-TKJ-009']);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'type' => 'already_completed',
        ]);
    }

    public function test_kartu_siswa_kelas_lain_diterima_di_gerbang(): void
    {
        $this->seedDemo();

        // Kiosk Gerbang Tunggal TIDAK terikat kelas — kartu siswa kelas
        // mana pun boleh tap (perubahan dari mode kiosk per kelas).
        $kelasLain = SchoolClass::create([
            'name' => 'X RPL 1',
            'slug' => 'X-RPL-1',
            'department' => 'Rekayasa Perangkat Lunak',
            'entry_time' => '07:00:00',
            'exit_time' => '15:30:00',
        ]);

        $siswaLain = Student::create([
            'school_class_id' => $kelasLain->id,
            'nis' => '10249999',
            'name' => 'Siswa Kelas Lain',
            'gender' => 'L',
        ]);

        RfidCard::create([
            'student_id' => $siswaLain->id,
            'card_uid' => 'CARD-RPL-001',
            'is_active' => true,
        ]);

        $response = $this->postJson('/kiosk/tap', [
            'card_uid' => 'CARD-RPL-001',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'type' => 'check_in',
        ]);

        $this->assertDatabaseHas('attendances', ['student_id' => $siswaLain->id]);
    }

    public function test_admin_dapat_membuka_rekap_harian_dan_data_polling(): void
    {
        $this->seedDemo();

        $admin = User::where('email', 'admin@knbi.sch.id')->first();

        $this->actingAs($admin)
            ->get('/guru/rekap-harian')
            ->assertStatus(200)
            ->assertSee('Rekap Kehadiran');

        $response = $this->actingAs($admin)
            ->getJson('/guru/rekap-harian/data');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'summary' => ['total', 'hadir', 'terlambat', 'belum_absen'],
            'students' => [['id', 'nis', 'name', 'status']],
        ]);

        $this->assertSame(10, $response->json('summary.total'));
        $this->assertSame(2, $response->json('summary.belum_absen'));
    }

    public function test_guru_dan_siswa_akses_rekap_mendapat_403(): void
    {
        $this->seedDemo();

        // Guru dan Siswa TIDAK boleh melihat rekap (khusus admin)
        $guru = User::where('email', 'guru@knbi.sch.id')->first();
        $this->actingAs($guru)->get('/guru/rekap-harian')->assertStatus(403);
        $this->actingAs($guru)->getJson('/guru/rekap-harian/data')->assertStatus(403);

        $siswa = User::where('email', 'siswa@knbi.sch.id')->first();
        $this->actingAs($siswa)->get('/guru/rekap-harian')->assertStatus(403);
        $this->actingAs($siswa)->getJson('/guru/rekap-harian/data')->assertStatus(403);
    }

    public function test_riwayat_absensi_admin_200_dan_guru_siswa_403(): void
    {
        $this->seedDemo();

        // Admin BOLEH melihat riwayat
        $admin = User::where('email', 'admin@knbi.sch.id')->first();
        $this->actingAs($admin)
            ->get('/siswa/riwayat-absensi')
            ->assertStatus(200);

        // Guru & Siswa DITOLAK (403)
        $guru = User::where('email', 'guru@knbi.sch.id')->first();
        $this->actingAs($guru)
            ->get('/siswa/riwayat-absensi')
            ->assertStatus(403);

        $siswa = User::where('email', 'siswa@knbi.sch.id')->first();
        $this->actingAs($siswa)
            ->get('/siswa/riwayat-absensi')
            ->assertStatus(403);
    }

    public function test_tamu_diarahkan_ke_login_dari_halaman_guru_dan_siswa(): void
    {
        $this->seedDemo();

        $this->get('/guru/rekap-harian')->assertRedirect('/login');
        $this->get('/siswa/riwayat-absensi')->assertRedirect('/login');
    }

    public function test_tap_dengan_foto_menyimpan_bukti_foto_masuk_dan_pulang(): void
    {
        Storage::fake('local');
        $this->seedDemo();

        // JPEG minimal tiruan (header + isi + footer) sebagai foto bukti
        $fotoPalsu = 'data:image/jpeg;base64,'.base64_encode(
            chr(0xFF).chr(0xD8).chr(0xFF).chr(0xE0).str_repeat('x', 300).chr(0xFF).chr(0xD9)
        );

        // Tap masuk dengan foto
        $this->postJson('/kiosk/tap', [
            'card_uid' => 'CARD-TKJ-009',
            'photo' => $fotoPalsu,
        ])->assertStatus(200);

        $attendance = Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))->first();
        $this->assertNotNull($attendance->photo_in_path, 'Foto bukti masuk harus tersimpan.');
        Storage::disk('local')->assertExists($attendance->photo_in_path);

        // Lewati cooldown lalu tap pulang dengan foto
        $attendance->update(['last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(2)]);

        $this->postJson('/kiosk/tap', [
            'card_uid' => 'CARD-TKJ-009',
            'photo' => $fotoPalsu,
        ])->assertStatus(200);

        $attendance->refresh();
        $this->assertNotNull($attendance->photo_out_path, 'Foto bukti pulang harus tersimpan.');
        Storage::disk('local')->assertExists($attendance->photo_out_path);
    }

    public function test_tap_dengan_foto_rusak_tetap_berhasil_tanpa_foto(): void
    {
        $this->seedDemo();

        // Foto bukan data URL JPEG yang sah → tap tetap berhasil, tanpa foto
        $response = $this->postJson('/kiosk/tap', [
            'card_uid' => 'CARD-TKJ-009',
            'photo' => 'bukan-gambar-valid',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $attendance = Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))->first();
        $this->assertNull($attendance->photo_in_path, 'Foto tidak valid tidak boleh tersimpan.');
    }

    public function test_fingerprint_mock_mengidentifikasi_siswa_dan_mencatat_absen(): void
    {
        $this->seedDemo();

        // Daftarkan sidik jari mock ke Hendra (NIS 10240109)
        Student::where('nis', '10240109')->update(['fingerprint_id' => 'MOCK-FINGER-001']);

        // Mock driver: identify mengembalikan fingerprint_id siswa terdaftar
        $response = $this->postJson('/kiosk/fingerprint/identificar', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'type' => 'check_in',
            'student_name' => 'Hendra Wijaya',
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => Student::where('nis', '10240109')->value('id'),
            'kiosk_device' => 'gerbang-fingerprint',
        ]);
    }

    public function test_fingerprint_tidak_terdaftar_dijawab_ramah_tanpa_crash(): void
    {
        $this->seedDemo();

        // Tidak ada siswa yang punya fingerprint_id -> mock tidak mengenali siapa pun
        $response = $this->postJson('/kiosk/fingerprint/identificar', []);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'type' => 'fingerprint_unavailable',
        ]);
        $this->assertStringContainsString('Sensor sidik jari', $response->json('message'));
    }
    public function test_kiosk_200_dan_link_daftar_dulu_tetap_ada(): void
    {
        $this->seedDemo();

        $response = $this->get('/kiosk');

        $response->assertStatus(200);
        $response->assertSee('Absen Gerbang');
        $response->assertSee('Daftar dulu');
    }

    public function test_mock_identify_dengan_auto_1_selalu_tidak_dikenali(): void
    {
        $this->seedDemo();

        Student::where('nis', '10240109')->update(['fingerprint_id' => 'MOCK-FINGER-001']);

        // auto=1 (loop otomatis kiosk): mock TIDAK BOLEH mengembalikan siswa
        $response = $this->postJson('/kiosk/fingerprint/identificar', ['auto' => 1]);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'type' => 'fingerprint_unavailable',
        ]);
        $this->assertArrayNotHasKey('student_name', $response->json());
    }

    public function test_mock_identify_tanpa_auto_tetap_mengenali_siswa(): void
    {
        $this->seedDemo();

        Student::where('nis', '10240109')->update(['fingerprint_id' => 'MOCK-FINGER-001']);

        $response = $this->postJson('/kiosk/fingerprint/identificar', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'student_name' => 'Hendra Wijaya',
        ]);
    }

    public function test_bridge_identify_dengan_auto_1_tetap_sukses(): void
    {
        $this->seedDemo();

        \Illuminate\Support\Facades\Http::fake(fn ($request) => match (true) {
            str_contains($request->url(), '/identificar') => \Illuminate\Support\Facades\Http::response(['ok' => true, 'huellaId' => 'BRIDGE-HUELLA-1'], 200),
            default => \Illuminate\Support\Facades\Http::response(['message' => 'not found'], 404),
        });

        Student::where('nis', '10240109')->update(['fingerprint_id' => 'BRIDGE-HUELLA-1']);

        config(['fingerprint.driver' => 'bridge']);
        try {
            $response = $this->postJson('/kiosk/fingerprint/identificar', ['auto' => 1]);
        } finally {
            config(['fingerprint.driver' => 'mock']);
        }

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'type' => 'check_in',
            'student_name' => 'Hendra Wijaya',
        ]);
    }
}
