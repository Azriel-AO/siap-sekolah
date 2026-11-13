<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\IzinSakit;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\DaftarHadirBulanan;
use Carbon\Carbon;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaftarHadirTest extends TestCase
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

    public function test_halaman_daftar_hadir_admin_buka_dan_lainnya_ditolak(): void
    {
        $this->seedDemo();

        $this->actingAs($this->admin())->get('/admin/daftar-hadir')->assertStatus(200);
        $this->actingAs($this->guru())->get('/admin/daftar-hadir')->assertStatus(403);
        $this->actingAs($this->siswa())->get('/admin/daftar-hadir')->assertStatus(403);

        $this->actingAs($this->admin())->get('/admin/daftar-hadir/excel')->assertStatus(200);
        $this->actingAs($this->guru())->get('/admin/daftar-hadir/excel')->assertStatus(403);
        $this->actingAs($this->siswa())->get('/admin/daftar-hadir/excel')->assertStatus(403);
    }

    public function test_excel_terunduh_format_xlsx_dan_nama_file(): void
    {
        $this->seedDemo();

        $response = $this->actingAs($this->admin())
            ->get('/admin/daftar-hadir/excel?bulan=11&tahun=2026&kelas=X-TKJ-1');

        $response->assertStatus(200);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringStartsWith('PK', $response->getContent());
        $this->assertStringContainsString('DAFTAR-HADIR-November-2026-X-TKJ-1.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_simbol_sel_sesuai_matrix(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00'); // Rabu
        $this->seedDemo();

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();

        // NISN & induk dengan leading zero
        Student::where('nis', '10240101')->update(['nisn' => '0075384110', 'induk' => '0001']); // Raka
        $raka = Student::where('nis', '10240101')->firstOrFail();
        $adinda = Student::where('nis', '10240102')->firstOrFail();
        $hendra = Student::where('nis', '10240109')->firstOrFail();
        $intan = Student::where('nis', '10240110')->firstOrFail();

        // Kehadiran nyata: Raka Senin 9 Nov HADIR, Adinda izin, Hendra tidak hadir sama sekali
        Attendance::create([
            'student_id' => $raka->id,
            'school_class_id' => $kelas->id,
            'date' => '2026-11-09',
            'check_in_at' => '06:45:00',
            'status' => 'HADIR',
            'last_tap_at' => Carbon::parse('2026-11-09 06:45:00', 'Asia/Jakarta'),
            'kiosk_device' => 'gerbang-kartu',
        ]);
        IzinSakit::create([
            'student_id' => $adinda->id,
            'tanggal' => '2026-11-09',
            'status' => 'izin',
            'keterangan' => 'Acara keluarga',
        ]);

        $data = (new DaftarHadirBulanan)->buildRows($kelas, 11, 2026, DaftarHadirBulanan::defaultPengaturan());
        $rows = $data['rows'];

        $findRow = function (string $name) use ($rows) {
            foreach ($rows as $row) {
                if (($row[3] ?? null) === $name) {
                    return $row;
                }
            }

            return null;
        };

        // Kolom hari: index 0=NO, 1=NISN, 2=INDUK, 3=NAMA, 4=hari 1 ... 4+d-1 = hari d
        $kolomHari = fn (int $hari) => 4 + $hari - 1;

        $rowRaka = $findRow('Raka Pratama');
        $this->assertSame('√', $rowRaka[$kolomHari(9)], 'Raka absen masuk tanggal 9 → √');
        $this->assertSame('0075384110', $rowRaka[1], 'NISN leading zero harus tetap utuh (string)');
        $this->assertSame('0001', $rowRaka[2], 'INDUK tetap utuh (string)');

        $rowAdinda = $findRow('Adinda Rahma');
        $this->assertSame('I', $rowAdinda[$kolomHari(9)], 'Adinda izin tanggal 9 → I');

        $rowHendra = $findRow('Hendra Wijaya');
        $this->assertSame('A', $rowHendra[$kolomHari(9)], 'Hendra hari sekolah lampau tanpa data → A');

        // Minggu 2026-11-15 tidak hari sekolah → kosong
        $this->assertSame('', $rowRaka[$kolomHari(15)], 'Minggu kosong (bukan hari sekolah)');
        $this->assertSame('', $rowHendra[$kolomHari(15)], 'Minggu kosong (bukan hari sekolah)');

        // Hari ini (11) belum tentu lewat → kosong bagi yang belum tap (kecuali Raka yg tap? tidak — seed hanya memunculkan Raka di hari Senin)
        Carbon::setTestNow();
    }

    public function test_header_blok_mengambil_dari_settings_dan_kelas(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00');
        $this->seedDemo();

        Setting::set('laporan_nama_sekolah', 'SMK UJI COBA BOYOLALI');
        Setting::set('laporan_tahun_pelajaran', '2025/2026');
        Setting::set('laporan_semester', 'Genap');
        Setting::set('laporan_kota', 'Surakarta');
        Setting::set('laporan_jabatan_ttd', 'Guru Mata Pelajaran');
        Setting::set('laporan_nama_ttd', 'Budi Santoso, S.Pd');

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $kelas->update(['wali_kelas' => 'Wahyu Nugroho, S.Kom']);

        $data = (new DaftarHadirBulanan)->buildRows($kelas, 1, 2026, DaftarHadirBulanan::pengaturan());
        $rows = $data['rows'];

        $this->assertSame('DAFTAR HADIR SISWA SEMESTER GENAP', $rows[0][DaftarHadirBulanan::KOLOM_KOP_TENGAH]);
        $this->assertSame('SMK UJI COBA BOYOLALI', $rows[1][DaftarHadirBulanan::KOLOM_KOP_TENGAH]);
        $this->assertSame('TAHUN PELAJARAN 2025/2026', $rows[2][DaftarHadirBulanan::KOLOM_KOP_TENGAH]);
        $this->assertSame('', $rows[0][0]);
        $this->assertSame('', $rows[1][0]);
        $this->assertSame('', $rows[2][0]);
        $this->assertSame('BULAN  :', $rows[3][17]);
        $this->assertSame('Januari', $rows[3][24]);
        $this->assertSame('KELAS  :', $rows[4][17]);
        $this->assertSame('X TKJ 1', $rows[4][24]);
        $this->assertSame('WALI KELAS :', $rows[5][17]);
        $this->assertSame('Wahyu Nugroho, S.Kom', $rows[5][24]);
        $this->assertSame('NO', $rows[7][0]);
        $this->assertSame('NISN', $rows[7][1]);
        $this->assertSame('INDUK', $rows[7][2]);
        $this->assertSame('NAMA', $rows[7][3]);
        $this->assertSame('TANGGAL', $rows[7][4]);
        $this->assertSame(1, $rows[8][4], 'Angka hari pertama kolom 4 (untuk Januari 31 hari)');
        $this->assertSame('Surakarta, Januari 2026', $rows[count($rows) - 4][24]);
        $this->assertSame('Guru Mata Pelajaran,', $rows[count($rows) - 3][24]);
        $this->assertSame('Budi Santoso, S.Pd', $rows[count($rows) - 1][24]);

        Carbon::setTestNow();
    }

    public function test_multi_kelas_menghasilkan_sheet_per_kelas(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00');
        $this->seedDemo();

        // Kelas kedua + 1 siswa
        $kelasLain = SchoolClass::create([
            'name' => 'X RPL 1',
            'slug' => 'X-RPL-1',
            'department' => 'Rekayasa Perangkat Lunak',
            'wali_kelas' => 'Sari, S.Kom',
            'entry_time' => '07:00:00',
            'exit_time' => '15:30:00',
        ]);
        Student::create([
            'school_class_id' => $kelasLain->id,
            'nis' => '10249999',
            'nisn' => '0099887766',
            'induk' => '0002',
            'name' => 'Siswa RPL XYZ',
            'gender' => 'L',
        ]);

        $response = $this->actingAs($this->admin())
            ->get('/admin/daftar-hadir/excel?bulan=11&tahun=2026&kelas=semua');

        $response->assertStatus(200);
        $this->assertStringStartsWith('PK', $response->getContent());

        // Baca workbook via ZipArchive → pastikan 2 sheet & nama cocok
        $tmp = tempnam(sys_get_temp_dir(), 'dh_test_').'.xlsx';
        file_put_contents($tmp, $response->getContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $sheetCount = substr_count($workbookXml, '<sheet ');
        $this->assertGreaterThanOrEqual(2, $sheetCount);
        $this->assertStringContainsString('X TKJ 1', $workbookXml);
        $this->assertStringContainsString('X RPL 1', $workbookXml);
        $zip->close();
        unlink($tmp);

        Carbon::setTestNow();
    }

    public function test_pengaturan_laporan_tersimpan_lewat_form(): void
    {
        $this->seedDemo();

        $response = $this->actingAs($this->admin())->post('/admin/pengaturan-laporan', [
            'laporan_nama_sekolah' => 'SMK HASIL UBAH',
            'laporan_tahun_pelajaran' => '2027/2028',
            'laporan_semester' => 'Genap',
            'laporan_kota' => 'Klaten',
            'laporan_jabatan_ttd' => 'Guru Kelas',
            'laporan_nama_ttd' => 'Citra, M.Pd',
        ]);

        $response->assertRedirect(route('admin.pengaturan.laporan'));
        $this->assertSame('SMK HASIL UBAH', Setting::get('laporan_nama_sekolah'));
        $this->assertSame('Genap', Setting::get('laporan_semester'));
    }

    public function test_nilai_bulan_terisi_nama_indonesia_dan_wali_kosong_jadi_strip(): void
    {
        $this->seedDemo();
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $kelas->update(['wali_kelas' => null]);

        $namaBulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        foreach ($namaBulan as $angka => $nama) {
            $rows = (new DaftarHadirBulanan)->buildRows($kelas, $angka, 2026, DaftarHadirBulanan::defaultPengaturan())['rows'];
            $this->assertSame($nama, $rows[3][DaftarHadirBulanan::KOLOM_NILAI_KANAN]);
        }

        $rows = (new DaftarHadirBulanan)->buildRows($kelas, 11, 2026, DaftarHadirBulanan::defaultPengaturan())['rows'];
        $this->assertSame('-', $rows[5][24], 'Wali kelas null tampil "-" di kolom nilai');
    }

    public function test_footer_empat_baris_di_kolom_kanan(): void
    {
        $this->seedDemo();
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $rows = (new DaftarHadirBulanan)->buildRows($kelas, 11, 2026, DaftarHadirBulanan::defaultPengaturan())['rows'];
        $footer = array_slice($rows, -4);

        $this->assertSame('Boyolali, November 2026', $footer[0][24]);
        $this->assertSame('Guru mapel,', $footer[1][24]);
        $this->assertSame('', $footer[2][24]);
        $this->assertSame('Indriyanto, S.Kom', $footer[3][24]);
        foreach ($footer as $baris) {
            $this->assertCount(35, $baris);
            $this->assertSame('', $baris[0]);
        }
    }

    public function test_kolom_tanggal_selalu_31_dan_di_luar_bulan_kosong(): void
    {
        Carbon::setTestNow('2026-12-31 10:00:00');
        $this->seedDemo();
        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        Student::where('nis', '10240101')->update(['nisn' => '0012345678', 'induk' => '0007']);

        foreach ([[2, 2026], [2, 2028], [4, 2026], [1, 2026]] as [$bulan, $tahun]) {
            $rows = (new DaftarHadirBulanan)->buildRows($kelas, $bulan, $tahun, DaftarHadirBulanan::defaultPengaturan())['rows'];

            $this->assertSame(range(1, 31), array_slice($rows[8], 4), 'Header tanggal selalu 1..31');
            $hariBulan = Carbon::create($tahun, $bulan, 1)->daysInMonth;

            foreach ($rows as $row) {
                if (($row[3] ?? null) === 'Raka Pratama') {
                    $this->assertCount(35, $row);
                    $this->assertSame('0012345678', $row[1]);
                    for ($d = $hariBulan + 1; $d <= 31; $d++) {
                        $this->assertSame('', $row[4 + $d - 1], "Tanggal $d di luar bulan $bulan harus kosong");
                    }
                }
            }
        }

        Carbon::setTestNow();
    }

    public function test_excel_memuat_kop_center_kolom_tengah_border_matrix_dan_warna_simbol(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00');
        $this->seedDemo();

        $kelas = SchoolClass::where('slug', 'X-TKJ-1')->firstOrFail();
        $raka = Student::where('nis', '10240101')->firstOrFail();
        $adinda = Student::where('nis', '10240102')->firstOrFail();
        $bima = Student::where('nis', '10240103')->firstOrFail();

        Attendance::create([
            'student_id' => $raka->id,
            'school_class_id' => $kelas->id,
            'date' => '2026-11-09',
            'check_in_at' => '06:45:00',
            'status' => 'HADIR',
            'last_tap_at' => Carbon::parse('2026-11-09 06:45:00', 'Asia/Jakarta'),
            'kiosk_device' => 'gerbang-kartu',
        ]);
        IzinSakit::create([
            'student_id' => $adinda->id,
            'tanggal' => '2026-11-09',
            'status' => 'izin',
            'keterangan' => 'Izin keluarga',
        ]);
        IzinSakit::create([
            'student_id' => $bima->id,
            'tanggal' => '2026-11-09',
            'status' => 'sakit',
            'keterangan' => 'Demam',
        ]);

        $response = $this->actingAs($this->admin())
            ->get('/admin/daftar-hadir/excel?bulan=11&tahun=2026&kelas=X-TKJ-1');
        $response->assertStatus(200);

        $tmp = tempnam(sys_get_temp_dir(), 'dh_style_').'.xlsx';
        file_put_contents($tmp, $response->getContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp));

        $stylesXml = (string) $zip->getFromName('xl/styles.xml');
        $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');

        // 1. Warna simbol terdaftar di styles.xml (√=008000, I=FFA500, A=FF0000, S=2563EB)
        $this->assertStringContainsString('FF008000', strtoupper($stylesXml));
        $this->assertStringContainsString('FFFFA500', strtoupper($stylesXml));
        $this->assertStringContainsString('FFFF0000', strtoupper($stylesXml));
        $this->assertStringContainsString('FF2563EB', strtoupper($stylesXml));

        // 2. Border tipis matrix & shading E5E7EB terdaftar di styles.xml
        $this->assertStringContainsString('<left style="thin"><color rgb="000000"/></left>', $stylesXml);
        $this->assertStringContainsString('<fgColor rgb="E5E7EB"/>', $stylesXml);

        // 3. Kop 3 baris ditulis di kolom S (kolom 19, indeks 18): S1, S2, S3 — BUKAN di A1..A3
        $this->assertStringContainsString('r="S1"', $sheetXml);
        $this->assertStringContainsString('r="S2"', $sheetXml);
        $this->assertStringContainsString('r="S3"', $sheetXml);
        $this->assertStringNotContainsString('r="A1"', $sheetXml);
        $this->assertStringNotContainsString('r="A2"', $sheetXml);
        $this->assertStringNotContainsString('r="A3"', $sheetXml);

        // 4. Seluruh 35 kolom matrix (A..AI) pada header (baris 8), angka hari (baris 9), dan siswa (baris 10) tertulis
        $this->assertStringContainsString('r="A8"', $sheetXml);
        $this->assertStringContainsString('r="AI8"', $sheetXml);
        $this->assertStringContainsString('r="A9"', $sheetXml);
        $this->assertStringContainsString('r="AI9"', $sheetXml);
        $this->assertStringContainsString('r="A10"', $sheetXml);
        $this->assertStringContainsString('r="AI10"', $sheetXml);

        // 5. Area non-matrix (kop baris 1-3, blok kanan baris 4-6, pemisah, footer) TIDAK menulis sel kosong berborder di kolom A
        $this->assertStringNotContainsString('r="A4"', $sheetXml);
        $this->assertStringNotContainsString('r="A5"', $sheetXml);
        $this->assertStringNotContainsString('r="A6"', $sheetXml);
        $this->assertStringContainsString('r="R4"', $sheetXml); // Kolom 18 (R) = BULAN :
        $this->assertStringContainsString('r="Y4"', $sheetXml); // Kolom 25 (Y) = November

        // 6. Format teks '@' (numFmtId="49") terdaftar di styles.xml untuk menjaga nol-depan NISN & INDUK
        $this->assertStringContainsString('numFmtId="49"', $stylesXml);

        $zip->close();
        unlink($tmp);
        Carbon::setTestNow();
    }
}
