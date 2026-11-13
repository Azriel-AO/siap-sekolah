<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\IzinSakit;
use App\Models\SchoolClass;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
    /** Kolom tanggal selalu 1..31 (format resmi sekolah), berapa pun hari dalam bulan. */
    public const JUMLAH_KOLOM_TANGGAL = 31;

    /** Total kolom tabel: NO, NISN, INDUK, NAMA + 31 tanggal. */
    public const JUMLAH_KOLOM = 35;

    /** Indeks (0-based) kolom tengah untuk kop 3 baris = kolom 19 (S). */
    public const KOLOM_KOP_TENGAH = 18;

    /** Indeks (0-based) kolom label blok BULAN/KELAS/WALI = kolom 18. */
    public const KOLOM_LABEL_KANAN = 17;

    /** Indeks (0-based) kolom nilai blok & mulai footer tanda tangan = kolom 25. */
    public const KOLOM_NILAI_KANAN = 24;

    /**
     * Baris kop laporan di kolom tengah (kolom 19 / indeks 18 = S), tetangga kosong.
     *
     * @return array<int, string>
     */
    public static function barisKopTengah(string $teks): array
    {
        $baris = array_fill(0, self::JUMLAH_KOLOM, '');
        $baris[self::KOLOM_KOP_TENGAH] = $teks;

        return $baris;
    }

    /**
     * Baris label + nilai di area kanan (label kolom 18, nilai kolom 25).
     *
     * @return array<int, string>
     */
    public static function barisBlokKanan(string $label, string $nilai): array
    {
        $baris = array_fill(0, self::JUMLAH_KOLOM, '');
        $baris[self::KOLOM_LABEL_KANAN] = $label;
        $baris[self::KOLOM_NILAI_KANAN] = $nilai;

        return $baris;
    }

    /**
     * Baris footer tanda tangan; teks di kolom 25.
     *
     * @return array<int, string>
     */
    public static function barisFooter(string $teks): array
    {
        $baris = array_fill(0, self::JUMLAH_KOLOM, '');
        $baris[self::KOLOM_NILAI_KANAN] = $teks;

        return $baris;
    }

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
     * @return Collection<int, SchoolClass>
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
            self::barisKopTengah('DAFTAR HADIR SISWA SEMESTER '.strtoupper($pengaturan['laporan_semester'])),
            self::barisKopTengah($pengaturan['laporan_nama_sekolah']),
            self::barisKopTengah('TAHUN PELAJARAN '.$pengaturan['laporan_tahun_pelajaran']),
            self::barisBlokKanan('BULAN  :', $namaBulan),
            self::barisBlokKanan('KELAS  :', $kelas->name),
            self::barisBlokKanan('WALI KELAS :', $kelas->wali_kelas ?: '-'),
            [''],
            ['NO', 'NISN', 'INDUK', 'NAMA', 'TANGGAL'],
            array_merge(['', '', '', ''], range(1, self::JUMLAH_KOLOM_TANGGAL)),
        ];

        foreach ($alunos as $index => $siswa) {
            $baris = [$index + 1, (string) ($siswa->nisn ?? ''), (string) ($siswa->induk ?? ''), (string) $siswa->name];

            for ($d = 1; $d <= self::JUMLAH_KOLOM_TANGGAL; $d++) {
                // Tanggal di luar jumlah hari bulan ini ? sel kosong
                if ($d > $hariDalamBulan) {
                    $baris[] = '';

                    continue;
                }

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
        $rows[] = self::barisFooter($pengaturan['laporan_kota'].', '.$namaBulan.' '.$tahun);
        $rows[] = self::barisFooter($pengaturan['laporan_jabatan_ttd'].',');
        $rows[] = self::barisFooter('');
        $rows[] = self::barisFooter($pengaturan['laporan_nama_ttd']);

        return [
            'kelas' => $kelas,
            'rows' => $rows,
            'namaBulan' => $namaBulan,
            'tahun' => $tahun,
        ];
    }
}
