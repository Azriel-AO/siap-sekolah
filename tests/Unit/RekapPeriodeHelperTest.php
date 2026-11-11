<?php

namespace Tests\Unit;

use App\Services\RekapPeriodeHelper;
use Carbon\Carbon;
use Tests\TestCase;

class RekapPeriodeHelperTest extends TestCase
{
    public function test_normalisasi_periode_mengenali_alias(): void
    {
        $this->assertSame('hari', RekapPeriodeHelper::normalisasiPeriode(null));
        $this->assertSame('hari', RekapPeriodeHelper::normalisasiPeriode('hari-ini'));
        $this->assertSame('hari', RekapPeriodeHelper::normalisasiPeriode('entah-apa'));
        $this->assertSame('minggu', RekapPeriodeHelper::normalisasiPeriode('minggu'));
        $this->assertSame('minggu', RekapPeriodeHelper::normalisasiPeriode('Minggu-Ini'));
        $this->assertSame('bulan', RekapPeriodeHelper::normalisasiPeriode('bulan'));
        $this->assertSame('bulan', RekapPeriodeHelper::normalisasiPeriode('BULANAN'));
    }

    public function test_rentang_hari_adalah_tanggal_itu_sendiri(): void
    {
        $r = RekapPeriodeHelper::hitungRentang('hari', '2026-11-10');

        $this->assertSame('hari', $r['periode']);
        $this->assertSame('2026-11-10', $r['start_str']);
        $this->assertSame('2026-11-10', $r['end_str']);
        $this->assertSame(1, $r['hari_sekolah']);
        $this->assertSame('Hari Ini', $r['label_periode']);
    }

    public function test_rentang_minggu_senin_sampai_minggu(): void
    {
        // 2026-11-10 adalah hari Selasa
        $r = RekapPeriodeHelper::hitungRentang('minggu', '2026-11-10');

        $this->assertSame('minggu', $r['periode']);
        $this->assertSame('2026-11-09', $r['start_str']); // Senin
        $this->assertSame('2026-11-15', $r['end_str']);   // Minggu
        $this->assertSame(5, $r['hari_sekolah']);         // Senin–Jumat
    }

    public function test_rentang_bulan_tanggal_1_sampai_akhir_bulan(): void
    {
        $r = RekapPeriodeHelper::hitungRentang('bulan', '2026-11-10');

        $this->assertSame('bulan', $r['periode']);
        $this->assertSame('2026-11-01', $r['start_str']);
        $this->assertSame('2026-11-30', $r['end_str']);
        // November 2026: hari kerja = 2–6 (5) + 9–13 (5) + 16–20 (5) + 23–27 (5) + 30 (1) = 21
        $this->assertSame(21, $r['hari_sekolah']);
    }

    public function test_tanggal_invalid_jatuh_ke_hari_ini(): void
    {
        Carbon::setTestNow('2026-11-11 08:00:00');

        $r = RekapPeriodeHelper::hitungRentang('hari', 'bukan-tanggal');
        $this->assertSame('2026-11-11', $r['tanggal']);

        $r2 = RekapPeriodeHelper::hitungRentang('minggu', '2026-13-99');
        $this->assertSame('minggu', $r2['periode']);

        Carbon::setTestNow();
    }
}
