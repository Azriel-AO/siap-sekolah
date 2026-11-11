<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\IzinSakit;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Pembangkit laporan "Daftar Hadir" (format sekolah SMK Karya Nugraha Boyolali).
 *
 * Susunan blok per sheet (satu sheet per kelas):
 *   DAFTAR HADIR SISWA SEMESTER {semester}
 *   {nama sekolah}
 *   TAHUN PELAJARAN {tahun pelajaran}
 *   BULAN  : {nama bulan}
 *   KELAS  : {nama kelas}
 *   WALI KELAS : {wali kelas}
 *   NO | NISN | INDUK | NAMA | TANGGAL
 *   1  2  3  ... 31
 *   [baris siswa: √ hadir, I izin, S sakit, A alpa]
 *   {kota}, {bulan} {tahun}
 *   {jabatan penandatangan}
 *   {nama penandatangan}
 *
 * Simbol sel:
 *   √  = hadir (termasuk terlambat — keterlambatan terlihat di rekap)
 *   I  = izin
 *   S  = sakit
 *   A  = alpa (hari sekolah yang SUDAH LEWAT, tanpa tap & tanpa izin/sakit — diturunkan)
 *   kosong = bukan hari sekolah / belum lewat / hari ini belum tap
 *
 * NISN & INDUK ditulis sebagai TEKS (leading zeros aman).
 */
class DaftarHadirBulanan
{
    /**
     * Default pengaturan laporan (dipakai bila tabel settings belum diisi).
     */
    public static function defaultPengaturan(): array
    {
        return [
            'laporan_nama_sekolah' => 'SMK KARYA NUGRAHA BOYOLALI',
            'laporan_tahun_pelajaran' => '2026/2027',
            'laporan_semester' => 'Ganjil',
            'laporan_kota' => 'Boyolali',
            'laporan_jabatan_ttd' => 'Guru mapel',
            'laporan_nama_ttd' => 'Indriyanto, S.Kom',
        ];
    }

    public static function pengaturan(): array
    {
        $hasil = [];

        foreach (self::defaultPengaturan() as $key => $default) {
            $hasil[$key] = Setting::get($key, $default) ?? $default;
        }

        return $hasil;
    }

    /**
     * Kelas yang akan diexport (semua, atau satu via slug).
     *
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    public function classesExport(?string $slug)
    {
        $query = SchoolClass::query()->orderBy('name');

        if ($slug !== null && $slug !== '' && $slug !== 'semua') {
            $query->where('slug', $slug);
        }

        return $query->get();
    }

    /**
     * Nama sheet aman dari nama kelas (maks 31 karakter, tanpa []:*?/\\).
     */
    public static function namaSheet(string $namaKelas): string
    {
        $nama = preg_replace('/[\[\]:\*\?\/\\\\]/', '-', $namaKelas);
        $nama = trim($nama);

        return mb_substr($nama, 0, 31);
    }

    /**
     * Bangun seluruh baris blok laporan untuk satu kelas & satu bulan/tahun.
     *
     * @return array<int, array> baris-baris (termasuk judul, kosong, siswa, ttd)
     */
    public function buildRows(SchoolClass $kelas, int $bulan, int $tahun, array $pengaturan): array
    {
        $pengaturan = $pengaturan ?: self::pengaturan();

        $tanggalAwal = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $hariDalamBulan = (int) $tanggalAwal->daysInMonth;
        $namaBulan = $tanggalAwal->translatedFormat('F');

        $alunos = $kelas->students()->orderBy('name')->get();

        // Peta kehadiran & izin/sakit per siswa untuk bulan tsb
        $tanggalAkhir = $tanggalAwal->copy()->endOfMonth();
        $attMap = Attendance::whereIn('student_id', $alunos->pluck('id'))
            ->whereBetween('date', [$tanggalAwal->toDateString(), $tanggalAkhir->toDateString()])
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($a) => $a->date->format('Y-m-d')));

        $izinSakitMap = IzinSakit::whereIn('student_id', $alunos->pluck('id'))
            ->whereBetween('tanggal', [$tanggalAwal->toDateString(), $tanggalAkhir->toDateString()])
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($a) => $a->tanggal->format('Y-m-d')));

        $hariIni = Carbon::now('Asia/Jakarta')->startOfDay();

        $rows = [
            ['DAFTAR HADIR SISWA SEMESTER ' . strtoupper($pengaturan['laporan_semester'])],
            [$pengaturan['laporan_nama_sekolah']],
            ['TAHUN PELAJARAN ' . $pengaturan['laporan_tahun_pelajaran']],
            ['BULAN  :', '', '', '', $namaBulan],
            ['KELAS  :', '', '', '', $kelas->name],
            ['WALI KELAS :', '', '', '', ($kelas->wali_kelas ?: '-')],
            [''],
            array_merge(['NO', 'NISN', 'INDUK', 'NAMA', 'TANGGAL']),
            array_merge(['', '', '', ''], range(1, $hariDalamBulan)),
        ];

        foreach ($alunos as $index => $siswa) {
            $baris = [$index + 1, $siswa->nisn ?? '', $siswa->induk ?? '', $siswa->name];

            for ($d = 1; $d <= $hariDalamBulan; $d++) {
                $date = Carbon::create($tahun, $bulan, $d)->startOfDay();
                $dateStr = $date->toDateString();

                // Bukan hari sekolah (Sabtu/Minggu) → kosong
                if (! $date->isWeekday()) {
                    $baris[] = '';
                    continue;
                }

                $att = $attMap[$siswa->id][$dateStr] ?? null;
                if ($att) {
                    $baris[] = '√'; // Hadir maupun Terlambat = √
                    continue;
                }

                $izinSakit = $izinSakitMap[$siswa->id][$dateStr] ?? null;
                if ($izinSakit) {
                    $baris[] = $izinSakit->status === 'izin' ? 'I' : 'S';
                    continue;
                }

                // Alpa hanya untuk hari sekolah yang sudah LEWAT (tanggal < hari ini)
                $baris[] = $date->lt($hariIni) ? 'A' : '';
            }

            $rows[] = $baris;
        }

        $rows[] = [''];
        $rows[] = ['', '', '', '', '', $pengaturan['laporan_kota'] . ', ' . $namaBulan . ' ' . $tahun];
        $rows[] = ['', '', '', '', '', $pengaturan['laporan_jabatan_ttd'] . ','];
        $rows[] = ['', '', '', '', ''];
        $rows[] = ['', '', '', '', '', $pengaturan['laporan_nama_ttd']];

        return [
            'kelas' => $kelas,
            'rows' => $rows,
            'namaBulan' => $namaBulan,
            'tahun' => $tahun,
        ];
    }
}
