<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RekapExportData;
use Carbon\Carbon;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapExportTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        User::factory()->create(['name' => 'Admin KNBI', 'email' => 'admin@knbi.sch.id', 'role' => 'admin']);
        User::factory()->create(['name' => 'Bu Sari', 'email' => 'guru@knbi.sch.id', 'role' => 'guru']);
        User::factory()->create(['name' => 'Raka Siswa', 'email' => 'siswa@knbi.sch.id', 'role' => 'siswa']);
        $this->seed(DemoAttendanceSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@knbi.sch.id')->firstOrFail();
    }

    public function test_admin_bisa_unduh_excel(): void
    {
        $this->seedDemo();

        $response = $this->actingAs($this->admin())->get('/admin/rekap-export/excel');

        $response->assertStatus(200);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $response->getContent(), 'File Excel (.xlsx) harus berupa ZIP.');
    }

    public function test_admin_bisa_unduh_pdf(): void
    {
        $this->seedDemo();

        $response = $this->actingAs($this->admin())->get('/admin/rekap-export/pdf');

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent(), 'File PDF harus berisi header %PDF.');
    }

    public function test_guru_dan_siswa_dilarang_export(): void
    {
        $this->seedDemo();

        $guru = User::where('email', 'guru@knbi.sch.id')->firstOrFail();
        $siswa = User::where('email', 'siswa@knbi.sch.id')->firstOrFail();

        $this->actingAs($guru)->get('/admin/rekap-export/excel')->assertStatus(403);
        $this->actingAs($guru)->get('/admin/rekap-export/pdf')->assertStatus(403);
        $this->actingAs($siswa)->get('/admin/rekap-export/excel')->assertStatus(403);
        $this->actingAs($siswa)->get('/admin/rekap-export/pdf')->assertStatus(403);
    }

    public function test_builder_menghasilkan_header_kolom_dan_baris_siswa(): void
    {
        $this->seedDemo();

        $tanggal = Carbon::now('Asia/Jakarta')->toDateString();
        $data = (new RekapExportData)->build($tanggal, 'X-TKJ-1');

        $this->assertSame(RekapExportData::HEADER, $data['rows'][0]);
        $this->assertSame(
            ['Tanggal', 'NISN', 'Nama', 'Kelas', 'Masuk', 'Pulang', 'Status', 'Metode'],
            $data['rows'][0]
        );

        // Minimal satu siswa demo (Raka Pratama) muncul di hasil export
        $namaSiswa = array_column(array_slice($data['rows'], 1), 2);
        $this->assertContains('Raka Pratama', $namaSiswa);
    }

    public function test_builder_menghormati_filter_tanggal_dan_kelas(): void
    {
        $this->seedDemo();

        $semua = (new RekapExportData)->build('2026-11-09', 'semua');
        $this->assertSame('09/11/2026', $semua['rows'][1][0]);
        $this->assertSame('Semua Kelas', $semua['kelasLabel']);

        $satuKelas = (new RekapExportData)->build('2026-11-09', 'X-TKJ-1');
        $this->assertSame('X TKJ 1', $satuKelas['kelasLabel']);

        // Filter tanggal yang dipakai di halaman rekap juga tidak mengubah struktur
        $this->assertSame(RekapExportData::HEADER, $semua['rows'][0]);
    }

    public function test_filter_tanggal_invalid_di_endpoint_jatuh_ke_hari_ini(): void
    {
        $this->seedDemo();

        $response = $this->actingAs($this->admin())->get('/admin/rekap-export/excel?tanggal=bukan-tanggal');

        $response->assertStatus(200);
    }
}
