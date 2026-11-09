<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
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
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_halaman_kiosk_kelas_dapat_dibuka(): void
    {
        $this->seedDemo();

        $response = $this->get('/kiosk/X-TKJ-1');

        $response->assertStatus(200);
        $response->assertSee('X TKJ 1');
    }

    public function test_kiosk_slug_tidak_dikenal_mengembalikan_404(): void
    {
        $this->seedDemo();

        $this->get('/kiosk/KELAS-PALAKU')->assertStatus(404);
    }

    public function test_tap_kartu_tidak_dikenali_ditolak(): void
    {
        $this->seedDemo();

        $response = $this->postJson('/kiosk/X-TKJ-1/tap', [
            'card_uid' => 'CARD-TIDAK-ADA',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'type' => 'not_found',
        ]);
    }

    public function test_tap_pertama_mencatat_absen_masuk_dengan_jam_server(): void
    {
        $this->seedDemo();

        // Siswa ke-9 (Hendra) belum absen hari ini
        $response = $this->postJson('/kiosk/X-TKJ-1/tap', [
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
        $this->postJson('/kiosk/X-TKJ-1/tap', ['card_uid' => 'CARD-TKJ-010']);

        // Tap kedua langsung (kurang dari 60 detik) ditolak R3
        $response = $this->postJson('/kiosk/X-TKJ-1/tap', ['card_uid' => 'CARD-TKJ-010']);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'type' => 'cooldown',
        ]);
    }

    public function test_tap_kedua_setelah_cooldown_mencatat_absen_pulang(): void
    {
        $this->seedDemo();

        $this->postJson('/kiosk/X-TKJ-1/tap', ['card_uid' => 'CARD-TKJ-009']);

        // Mundurkan waktu tap terakhir 2 menit agar lolos cooldown
        $attendance = Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))->first();
        $attendance->update([
            'last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(2),
        ]);

        $response = $this->postJson('/kiosk/X-TKJ-1/tap', ['card_uid' => 'CARD-TKJ-009']);

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

        $this->postJson('/kiosk/X-TKJ-1/tap', ['card_uid' => 'CARD-TKJ-009']);
        Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))
            ->update(['last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(2)]);
        $this->postJson('/kiosk/X-TKJ-1/tap', ['card_uid' => 'CARD-TKJ-009']);

        Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))
            ->update(['last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(2)]);
        $response = $this->postJson('/kiosk/X-TKJ-1/tap', ['card_uid' => 'CARD-TKJ-009']);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'type' => 'already_completed',
        ]);
    }

    public function test_kartu_siswa_kelas_lain_ditolak_di_kiosk(): void
    {
        $this->seedDemo();

        // Kelas lain + siswa lain + kartu lain
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

        // Tap kartu kelas RPL di kiosk kelas TKJ → anomali ditolak (R2 / Konstitusi VII)
        $response = $this->postJson('/kiosk/X-TKJ-1/tap', [
            'card_uid' => 'CARD-RPL-001',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'type' => 'wrong_class',
        ]);

        // Tidak boleh ada record kehadiran yang tercipta
        $this->assertDatabaseMissing('attendances', ['student_id' => $siswaLain->id]);
    }

    public function test_guru_dapat_membuka_rekap_harian_dan_data_polling(): void
    {
        $this->seedDemo();

        $guru = User::where('email', 'guru@knbi.sch.id')->first();

        $this->actingAs($guru)
            ->get('/guru/rekap-harian')
            ->assertStatus(200)
            ->assertSee('Rekap Kehadiran Harian');

        $response = $this->actingAs($guru)
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

    public function test_siswa_hanya_melihat_riwayat_miliknya(): void
    {
        $this->seedDemo();

        $siswa = User::where('email', 'siswa@knbi.sch.id')->first();

        $response = $this->actingAs($siswa)
            ->get('/siswa/riwayat-absensi');

        $response->assertStatus(200);
        // Raka Pratama adalah pemilik akun siswa — namanya harus muncul,
        // sedangkan nama siswa lain TIDAK boleh tampil (privasi data)
        $response->assertSee('Raka Pratama');
        $response->assertDontSee('Adinda Rahma');
        $response->assertDontSee('Hendra Wijaya');
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
        $this->postJson('/kiosk/X-TKJ-1/tap', [
            'card_uid' => 'CARD-TKJ-009',
            'photo' => $fotoPalsu,
        ])->assertStatus(200);

        $attendance = Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))->first();
        $this->assertNotNull($attendance->photo_in_path, 'Foto bukti masuk harus tersimpan.');
        Storage::disk('local')->assertExists($attendance->photo_in_path);

        // Lewati cooldown lalu tap pulang dengan foto
        $attendance->update(['last_tap_at' => Carbon::now('Asia/Jakarta')->subMinutes(2)]);

        $this->postJson('/kiosk/X-TKJ-1/tap', [
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
        $response = $this->postJson('/kiosk/X-TKJ-1/tap', [
            'card_uid' => 'CARD-TKJ-009',
            'photo' => 'bukan-gambar-valid',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $attendance = Attendance::whereHas('student', fn ($q) => $q->where('nis', '10240109'))->first();
        $this->assertNull($attendance->photo_in_path, 'Foto tidak valid tidak boleh tersimpan.');
    }
}
