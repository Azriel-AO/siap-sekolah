<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClassTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('email', 'admin@knbi.sch.id')->first()
            ?? User::factory()->create(['email' => 'admin@knbi.sch.id', 'role' => 'admin']);
    }

    public function test_artisan_db_seed_mengisi_39_kelas_smk_knbi_dan_idempotent_saat_dijalankan_2x(): void
    {
        $this->artisan('db:seed')->assertExitCode(0);

        $expectedNames = [
            // Kelas 10 (13)
            '10 TKR 1', '10 TKR 2', '10 TKR 3', '10 TKR 4', '10 TKR 5',
            '10 TBKR 1',
            '10 TSM 1', '10 TSM 2', '10 TSM 3',
            '10 TKJ 1', '10 TKJ 2', '10 TKJ 3',
            '10 PSY 1',
            // Kelas 11 (14)
            '11 TKR 1', '11 TKR 2', '11 TKR 3', '11 TKR 4', '11 TKR 5',
            '11 TBKR 1',
            '11 TSM 1', '11 TSM 2', '11 TSM 3',
            '11 TKJ 1', '11 TKJ 2', '11 TKJ 3',
            '11 TTT 1',
            '11 PSY 1',
            // Kelas 12 (12)
            '12 TKR 1', '12 TKR 2', '12 TKR 3', '12 TKR 4', '12 TKR 5',
            '12 TBKR 1',
            '12 TSM 1', '12 TSM 2',
            '12 TKJ 1', '12 TKJ 2',
            '12 TTT 1',
            '12 PSY 1',
        ];

        $this->assertCount(39, $expectedNames);
        $this->assertSame(39, SchoolClass::count());

        foreach ($expectedNames as $name) {
            $this->assertDatabaseHas('school_classes', ['name' => $name]);
        }

        // Jalankan db:seed kedua kali -> tidak boleh ada duplikasi
        $this->artisan('db:seed')->assertExitCode(0);
        $this->assertSame(39, SchoolClass::count());
    }

    public function test_admin_dapat_tambah_edit_dan_hapus_kelas_serta_validasi_kelas_berisi_siswa(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->admin();

        // /admin/kelas redirect ke /admin/data-kelas
        $this->actingAs($admin)->get('/admin/kelas')->assertRedirect(route('admin.kelas.index'));

        // Halaman daftar kelas & form tambah tampil
        $this->actingAs($admin)->get(route('admin.kelas.index'))->assertOk()->assertSee('10 TKR 1');
        $this->actingAs($admin)->get(route('admin.kelas.create'))->assertOk();

        // Tambah kelas baru
        $createRes = $this->actingAs($admin)->post(route('admin.kelas.store'), [
            'name' => '10 RPL 1',
            'department' => 'Rekayasa Perangkat Lunak',
            'entry_time' => '07:00',
            'exit_time' => '15:30',
        ]);
        $createRes->assertRedirect(route('admin.kelas.index'));
        $this->assertDatabaseHas('school_classes', [
            'name' => '10 RPL 1',
            'slug' => '10-RPL-1',
        ]);

        // Edit nama kelas: "10 TKR 1" -> "10 TKR 1 Pagi"
        $kelasTkr = SchoolClass::where('name', '10 TKR 1')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.kelas.edit', $kelasTkr))->assertOk();

        $updateRes = $this->actingAs($admin)->put(route('admin.kelas.update', $kelasTkr), [
            'name' => '10 TKR 1 Pagi',
            'slug' => $kelasTkr->slug,
            'department' => $kelasTkr->department,
            'entry_time' => '07:00',
            'exit_time' => '15:30',
        ]);
        $updateRes->assertRedirect(route('admin.kelas.index'));
        $this->assertDatabaseHas('school_classes', [
            'id' => $kelasTkr->id,
            'name' => '10 TKR 1 Pagi',
        ]);

        // Hapus kelas yang masih memiliki siswa -> DITOLAK dengan pesan "Kelas masih memiliki siswa"
        Student::create([
            'school_class_id' => $kelasTkr->id,
            'nis' => '10260001',
            'nisn' => '0010260001',
            'name' => 'Siswa Aktif TKR',
            'gender' => 'L',
        ]);

        $deleteBlocked = $this->actingAs($admin)->delete(route('admin.kelas.destroy', $kelasTkr));
        $deleteBlocked->assertRedirect(route('admin.kelas.index'));
        $deleteBlocked->assertSessionHas('error', fn (string $msg): bool => str_contains($msg, 'Kelas masih memiliki siswa'));
        $this->assertDatabaseHas('school_classes', ['id' => $kelasTkr->id]);

        // Hapus kelas kosong -> BERHASIL
        $kelasBaru = SchoolClass::where('name', '10 RPL 1')->firstOrFail();
        $deleteOk = $this->actingAs($admin)->delete(route('admin.kelas.destroy', $kelasBaru));
        $deleteOk->assertRedirect(route('admin.kelas.index'));
        $deleteOk->assertSessionHas('status');
        $this->assertDatabaseMissing('school_classes', ['id' => $kelasBaru->id]);
    }

    public function test_dropdown_pendaftaran_rekapitulasi_dan_daftar_hadir_mengambil_kelas_dari_database(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->admin();

        SchoolClass::create([
            'name' => '10 DKV DINAMIS',
            'slug' => '10-DKV-DINAMIS',
            'department' => 'Desain Komunikasi Visual',
            'entry_time' => '07:00:00',
            'exit_time' => '15:30:00',
        ]);

        $this->get(route('pendaftaran.index'))
            ->assertOk()
            ->assertSee('10 DKV DINAMIS');

        $this->actingAs($admin)
            ->get(route('admin.rekapitulasi'))
            ->assertOk()
            ->assertSee('10 DKV DINAMIS');

        $this->actingAs($admin)
            ->get(route('admin.daftar-hadir'))
            ->assertOk()
            ->assertSee('10 DKV DINAMIS');
    }
}
