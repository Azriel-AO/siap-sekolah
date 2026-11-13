<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\RekapExportData;
use Carbon\Carbon;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapPeriodeTest extends TestCase
{
    use RefreshDatabase;

    protected function seedDemo(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
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

    private function hadir(Student $student, string $tanggal, string $status, string $jam = '06:50:00'): Attendance
    {
        return Attendance::create([
            'student_id' => $student->id,
            'school_class_id' => $student->school_class_id,
            'date' => $tanggal,
            'check_in_at' => $jam,
            'status' => $status,
            'last_tap_at' => Carbon::parse($tanggal.' '.$jam, 'Asia/Jakarta'),
            'kiosk_device' => 'gerbang-kartu',
        ]);
    }

    public function test_rekap_minggu_menghitung_lintas_tanggal_dalam_rentang(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00'); // Selasa
        $this->seedDemo();

        // Hendra hadir pada Senin 2026-11-09 (dalam rentang minggu) — belum absen hari ini
        $hendra = Student::where('nis', '10240109')->firstOrFail();
        $this->hadir($hendra, '2026-11-09', 'HADIR', '06:45:00');

        $response = $this->actingAs($this->admin())
            ->getJson('/guru/rekap-harian/data?periode=minggu&kelas=X-TKJ-1');

        $response->assertStatus(200);
        $response->assertJson(['periode' => 'minggu']);

        $row = collect($response->json('students'))->firstWhere('name', 'Hendra Wijaya');
        $this->assertSame(1, $row['hadir']);      // hadir 11-09 (hari ini 11-10 belum tap)
        $this->assertSame(0, $row['terlambat']);
        $this->assertSame(0, $row['izin']);
        $this->assertSame(0, $row['sakit']);
        $this->assertSame(0, $row['alpa']);       // hari sekolah lewat (9 Nov saja) − hadir(1) = 0
        $this->assertSame(20, $row['persentase']); // 1/5 (pembagi: 5 hari sekolah − 0 izin − 0 sakit)

        Carbon::setTestNow();
    }

    public function test_rekap_bulan_mengabaikan_attendance_bulan_lain(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->seedDemo();

        $hendra = Student::where('nis', '10240109')->firstOrFail();
        $this->hadir($hendra, '2026-10-31', 'HADIR', '06:45:00'); // bulan SEBELUMNYA — harus diabaikan
        $this->hadir($hendra, '2026-11-09', 'HADIR', '06:45:00');

        $response = $this->actingAs($this->admin())
            ->getJson('/guru/rekap-harian/data?periode=bulan&kelas=X-TKJ-1');

        $response->assertStatus(200);

        $row = collect($response->json('students'))->firstWhere('name', 'Hendra Wijaya');
        // Hanya 2026-11-09 yang dihitung; attendance 31 Oktober tidak masuk rentang November
        $this->assertSame(1, $row['hadir']);

        Carbon::setTestNow();
    }

    public function test_export_kelas_standalone_tidak_menyertakan_siswa_kelas_lain(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->seedDemo();

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
            'name' => 'Siswa RPL Unik XYZ',
            'gender' => 'L',
        ]);
        $this->hadir($siswaLain, '2026-11-09', 'HADIR', '06:45:00');

        foreach (['hari', 'minggu'] as $periode) {
            $data = (new RekapExportData)->build('2026-11-10', 'X-TKJ-1', $periode);
            $nama = array_column(array_slice($data['rows'], 1), 1);

            $this->assertNotContains('Siswa RPL Unik XYZ', $nama, "Export periode {$periode} bocor kelas lain.");
        }

        Carbon::setTestNow();
    }

    public function test_export_kolom_ringkasan_untuk_periode_minggu(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->seedDemo();

        $hendra = Student::where('nis', '10240109')->firstOrFail();
        $this->hadir($hendra, '2026-11-09', 'HADIR', '06:45:00');

        $data = (new RekapExportData)->build('2026-11-10', 'X-TKJ-1', 'minggu');

        $this->assertSame(RekapExportData::HEADER_RINGKASAN, $data['rows'][0]);

        // Hendra: Hadir 1 (11-09), Terlambat 0, Izin 0, Sakit 0, Alpa 0, Persentase 20%
        $row = collect($data['rows'])->firstWhere(1, 'Hendra Wijaya');
        $this->assertSame(1, $row[3]);
        $this->assertSame(0, $row[4]);
        $this->assertSame(0, $row[5]);
        $this->assertSame(0, $row[6]);
        $this->assertSame(0, $row[7]);
        $this->assertSame('20%', $row[8]);

        Carbon::setTestNow();
    }

    public function test_semua_endpoint_rekap_hanya_admin(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->seedDemo();

        $endpoints = [
            ['GET', '/guru/rekap-harian'],
            ['GET', '/guru/rekap-harian/data'],
            ['GET', '/guru/rekap-harian/data?periode=minggu'],
            ['GET', '/guru/rekap-harian/data?periode=bulan'],
            ['GET', '/admin/rekapitulasi'],
            ['GET', '/admin/rekap-grafik'],
            ['GET', '/admin/rekap-grafik?periode=minggu'],
            ['GET', '/admin/rekap-export/excel'],
            ['GET', '/admin/rekap-export/excel?periode=minggu&tanggal=2026-11-10&kelas=X-TKJ-1'],
            ['GET', '/admin/rekap-export/pdf'],
            ['GET', '/admin/rekap-export/pdf?periode=minggu&tanggal=2026-11-10&kelas=X-TKJ-1'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $this->actingAs($this->admin())->get($uri)->assertStatus(200, "Admin ditolak di {$uri}");
            $this->actingAs($this->guru())->get($uri)->assertStatus(403, "Guru diizinkan di {$uri}");
            $this->actingAs($this->siswa())->get($uri)->assertStatus(403, "Siswa diizinkan di {$uri}");
        }

        Carbon::setTestNow();
    }

    public function test_endpoint_polling_struktur_json_dan_live(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->seedDemo();

        $response = $this->actingAs($this->admin())
            ->getJson('/guru/rekap-harian/data?periode=minggu&kelas=X-TKJ-1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'class_name',
            'periode',
            'periode_label',
            'rentang_label',
            'date_formatted',
            'server_time',
            'summary' => ['total', 'hadir', 'terlambat', 'alpa', 'rata_persen'],
            'students' => [
                '*' => ['id', 'nis', 'nisn', 'name', 'gender', 'hadir', 'terlambat', 'izin', 'sakit', 'alpa', 'persentase', 'face'],
            ],
        ]);
        $this->assertSame('minggu', $response->json('periode'));

        Carbon::setTestNow();
    }

    public function test_grafik_dengan_periode_menghitung_rentang(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00'); // Selasa
        $this->seedDemo();

        // Tambah 1 HADIR pada Senin 2026-11-09 → total hadir minggu ini = 5+1 = 6
        $raka = Student::where('nis', '10240101')->firstOrFail();
        $this->hadir($raka, '2026-11-09', 'HADIR', '06:45:00');

        $response = $this->actingAs($this->admin())->getJson('/admin/rekap-grafik?periode=minggu');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tanggal',
            'periode',
            'periode_label',
            'rentang_label',
            'hari_sekolah',
            'labels',
            'hadir',
            'terlambat',
            'izin',
            'sakit',
            'alpa',
        ]);

        $this->assertSame('minggu', $response->json('periode'));
        $this->assertSame(5, $response->json('hari_sekolah'));
        // 5 (seed hari ini, Selasa 11-10) + 1 (raka Senin 11-09) = 6 hadir; 3 terlambat;
        // alpa = Hendra+Intan (belum tap sama sekali di rentang) × 1 hari sekolah lewat = 2
        $this->assertSame([6], $response->json('hadir'));
        $this->assertSame([3], $response->json('terlambat'));
        $this->assertSame([0], $response->json('izin'));
        $this->assertSame([0], $response->json('sakit'));
        $this->assertSame([2], $response->json('alpa'));

        Carbon::setTestNow();
    }

    public function test_nama_file_export_mengikuti_periode(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->seedDemo();

        $excel = $this->actingAs($this->admin())
            ->get('/admin/rekap-export/excel?periode=minggu&tanggal=2026-11-10&kelas=X-TKJ-1');
        $this->assertStringContainsString('rekap-minggu-2026-11-09-sd-2026-11-15-X-TKJ-1.xlsx', $excel->headers->get('Content-Disposition'));

        $pdf = $this->actingAs($this->admin())
            ->get('/admin/rekap-export/pdf?periode=hari&tanggal=2026-11-10&kelas=X-TKJ-1');
        $this->assertStringContainsString('rekap-hari-2026-11-10-X-TKJ-1.pdf', $pdf->headers->get('Content-Disposition'));

        Carbon::setTestNow();
    }
}
