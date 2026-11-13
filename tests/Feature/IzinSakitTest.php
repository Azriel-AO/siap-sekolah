<?php

namespace Tests\Feature;

use App\Models\IzinSakit;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\RekapExportData;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IzinSakitTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@knbi.sch.id')->first()
            ?? User::factory()->create(['email' => 'admin@knbi.sch.id', 'role' => 'admin']);
    }

    private function guru(): User
    {
        return User::where('email', 'guru@knbi.sch.id')->first()
            ?? User::factory()->create(['email' => 'guru@knbi.sch.id', 'role' => 'guru']);
    }

    private function siswa(): User
    {
        return User::where('email', 'siswa@knbi.sch.id')->first()
            ?? User::factory()->create(['email' => 'siswa@knbi.sch.id', 'role' => 'siswa']);
    }

    private function hariIni(): string
    {
        return Carbon::now('Asia/Jakarta')->toDateString();
    }

    public function test_halaman_catat_izin_sakit_admin_buka_dan_lainnya_ditolak(): void
    {
        $this->seedDemo();

        $this->actingAs($this->admin())->get('/admin/kehadiran-manual')->assertStatus(200);
        $this->actingAs($this->guru())->get('/admin/kehadiran-manual')->assertStatus(403);
        $this->actingAs($this->siswa())->get('/admin/kehadiran-manual')->assertStatus(403);
    }

    public function test_catat_izin_tersimpan_dan_muncul_di_rekap(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00'); // Selasa
        $this->seedDemo();

        $hendra = Student::where('nis', '10240109')->firstOrFail();

        $response = $this->actingAs($this->admin())->post('/admin/kehadiran-manual', [
            'student_id' => $hendra->id,
            'tanggal' => '2026-11-10',
            'aksi' => 'izin',
            'keterangan' => 'Ada acara keluarga',
            'kelas' => 'X-TKJ-1',
        ]);

        $response->assertRedirect(route('admin.kehadiran.manual', ['tanggal' => '2026-11-10', 'kelas' => 'X-TKJ-1']));
        $this->assertDatabaseHas('izin_sakit', [
            'student_id' => $hendra->id,
            'tanggal' => '2026-11-10',
            'status' => 'izin',
        ]);

        // Rekap minggu: Hendra izin=1, sakit=0, alpa = 1 hari sekolah lewat (9 Nov) − 1 izin = 0
        $response = $this->actingAs($this->admin())
            ->getJson('/guru/rekap-harian/data?periode=minggu&kelas=X-TKJ-1');

        $row = collect($response->json('students'))->firstWhere('name', 'Hendra Wijaya');
        $this->assertSame(1, $row['izin']);
        $this->assertSame(0, $row['sakit']);
        $this->assertSame(0, $row['alpa']);

        // Tombol Hapus catatan benar-benar menghapus
        $this->actingAs($this->admin())->post('/admin/kehadiran-manual', [
            'student_id' => $hendra->id,
            'tanggal' => '2026-11-10',
            'aksi' => 'hapus',
            'kelas' => 'X-TKJ-1',
        ]);

        $this->assertDatabaseMissing('izin_sakit', ['student_id' => $hendra->id, 'tanggal' => '2026-11-10']);

        Carbon::setTestNow();
    }

    public function test_catat_sakit_menimpa_izin_hari_yang_sama(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->seedDemo();

        $hendra = Student::where('nis', '10240109')->firstOrFail();

        // Catat izin dulu, lalu timpa dengan sakit pada tanggal yang sama
        $this->actingAs($this->admin())->post('/admin/kehadiran-manual', [
            'student_id' => $hendra->id,
            'tanggal' => '2026-11-10',
            'aksi' => 'izin',
            'kelas' => 'X-TKJ-1',
        ]);
        $this->actingAs($this->admin())->post('/admin/kehadiran-manual', [
            'student_id' => $hendra->id,
            'tanggal' => '2026-11-10',
            'aksi' => 'sakit',
            'keterangan' => 'Demam',
            'kelas' => 'X-TKJ-1',
        ]);

        $this->assertDatabaseHas('izin_sakit', ['student_id' => $hendra->id, 'tanggal' => '2026-11-10', 'status' => 'sakit']);
        $this->assertDatabaseCount('izin_sakit', 1);

        Carbon::setTestNow();
    }

    public function test_tanggal_masa_depan_ditolak(): void
    {
        $this->seedDemo();

        $hendra = Student::where('nis', '10240109')->firstOrFail();

        $response = $this->actingAs($this->admin())->post('/admin/kehadiran-manual', [
            'student_id' => $hendra->id,
            'tanggal' => '2099-12-31',
            'aksi' => 'izin',
        ]);

        $response->assertSessionHasErrors('tanggal');
        $this->assertDatabaseCount('izin_sakit', 0);
    }

    public function test_alpa_terderive_hanya_hari_sekolah_yang_sudah_lewat(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00'); // Rabu
        $this->seedDemo();

        // Catat izin Senin 09-11 → alpa HANYA menghitung hari lewat yang tidak tap & tidak izin/sakit
        $hendra = Student::where('nis', '10240109')->firstOrFail();
        IzinSakit::create([
            'student_id' => $hendra->id,
            'tanggal' => '2026-11-09',
            'status' => 'izin',
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson('/guru/rekap-harian/data?periode=minggu&kelas=X-TKJ-1');

        $row = collect($response->json('students'))->firstWhere('name', 'Hendra Wijaya');
        // Hari sekolah lewat sebelum hari ini (11-11): 9 & 10 Nov (2 hari). Hendra izin 9 Nov → alpa tersisa untuk 10 Nov = 1
        $this->assertSame(1, $row['izin']);
        $this->assertSame(1, $row['alpa']);

        Carbon::setTestNow();
    }

    public function test_persentase_pembagi_nol_tampil_sebagai_strip(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00'); // Selasa
        $this->seedDemo();

        // Hendra izin SEMUA hari sekolah rentang → pembagi = 5 − 5 = 0 → persentase null (—)
        $hendra = Student::where('nis', '10240109')->firstOrFail();
        foreach (['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'] as $tgl) {
            IzinSakit::create([
                'student_id' => $hendra->id,
                'tanggal' => $tgl,
                'status' => 'izin',
            ]);
        }

        $data = (new RekapExportData)->build('2026-11-10', 'X-TKJ-1', 'minggu');
        $row = collect($data['rows'])->firstWhere(1, 'Hendra Wijaya');

        $this->assertSame('—', $row[8]);
        $this->assertSame(5, $row[5]); // semua hari = izin

        Carbon::setTestNow();
    }

    public function test_form_kelas_menyimpan_wali_kelas(): void
    {
        $this->seedDemo();

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        $response = $this->actingAs($this->admin())->put('/admin/data-kelas/'.$kelas->id, [
            'name' => $kelas->name,
            'slug' => $kelas->slug,
            'department' => $kelas->department,
            'wali_kelas' => 'Wahyu Nugroho, S.Kom',
            'entry_time' => '07:00',
            'exit_time' => '15:30',
        ]);

        $response->assertRedirect(route('admin.kelas.index'));
        $this->assertSame('Wahyu Nugroho, S.Kom', $kelas->refresh()->wali_kelas);
    }

    public function test_form_siswa_menyimpan_induk(): void
    {
        $this->seedDemo();

        $student = Student::where('nis', '10240101')->firstOrFail();

        $response = $this->actingAs($this->admin())->put('/admin/data-siswa/'.$student->id, [
            'name' => $student->name,
            'nis' => $student->nis,
            'induk' => '0075384110',
            'gender' => $student->gender,
            'school_class_id' => $student->school_class_id,
            'card_uid' => $student->rfidCard?->card_uid,
        ]);

        $response->assertRedirect();
        $this->assertSame('0075384110', $student->refresh()->induk);
    }
}
