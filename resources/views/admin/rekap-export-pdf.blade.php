<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Kehadiran — {{ $kelasLabel }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2937; }
        .kop { text-align: center; border-bottom: 3px double #1e3a8a; padding-bottom: 10px; margin-bottom: 12px; }
        .kop .sekolah { font-size: 17px; font-weight: bold; color: #1e3a8a; letter-spacing: 1px; }
        .kop .alamat { font-size: 8px; color: #4b5563; margin-top: 2px; }
        .judul { text-align: center; margin: 14px 0 4px; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #111827; }
        .subjudul { text-align: center; font-size: 10px; color: #374151; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #1e3a8a; color: #ffffff; padding: 7px 8px; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #1e3a8a; text-align: left; }
        tbody td { padding: 6px 8px; border: 1px solid #d1d5db; }
        tbody tr:nth-child(even) td { background: #f3f4f6; }
        .status-hadir { color: #047857; font-weight: bold; }
        .status-terlambat { color: #b45309; font-weight: bold; }
        .status-belum { color: #6b7280; }
        .kaki { margin-top: 18px; font-size: 7.5px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .footer { margin-top: 26px; width: 100%; font-size: 9px; }
        .ttd { width: 35%; text-align: center; float: right; }
        .ttd .nama { margin-top: 48px; font-weight: bold; border-top: 1px solid #111827; display: inline-block; padding: 2px 18px 0; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="sekolah">{{ $sekolah }}</div>
        <div class="alamat">Jl. Pendidikan No. 1 — SMK Karya Nugraha — Rekap Absen Gerbang</div>
    </div>

    <div class="judul">Rekap Kehadiran Siswa</div>
    <div class="subjudul">Periode: {{ $periodeLabel }} ({{ $rentangLabel }}) &nbsp;|&nbsp; Kelas: {{ $kelasLabel }}</div>

    @if($periode === 'hari')
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>NISN</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Masuk</th>
                <th>Pulang</th>
                <th>Status</th>
                <th>Metode</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($rows, 1) as $row)
                <tr>
                    <td>{{ $row[0] }}</td>
                    <td>{{ $row[1] }}</td>
                    <td>{{ $row[2] }}</td>
                    <td>{{ $row[3] }}</td>
                    <td>{{ $row[4] }}</td>
                    <td>{{ $row[5] }}</td>
                    <td class="{{ match($row[6]) { 'Hadir' => 'status-hadir', 'Terlambat' => 'status-terlambat', default => 'status-belum' } }}">{{ $row[6] }}</td>
                    <td>{{ $row[7] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <table>
        <thead>
            <tr>
                <th>NISN</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Hadir</th>
                <th>Terlambat</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Alpa</th>
                <th>Persentase</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($rows, 1) as $row)
                <tr>
                    <td>{{ $row[0] }}</td>
                    <td>{{ $row[1] }}</td>
                    <td>{{ $row[2] }}</td>
                    <td>{{ $row[3] }}</td>
                    <td>{{ $row[4] }}</td>
                    <td>{{ $row[5] }}</td>
                    <td>{{ $row[6] }}</td>
                    <td>{{ $row[7] }}</td>
                    <td>{{ $row[8] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="kaki">
        @if($periode !== 'hari')
            Definisi: Hadir/Terlambat/Izin/Sakit = jumlah catatan dalam rentang. Alpa = hari sekolah (Senin–Jumat) yang sudah lewat dalam rentang − Hadir − Terlambat − Izin − Sakit (diturunkan, bukan disimpan). Persentase = (Hadir + Terlambat) / (hari sekolah dalam rentang − Izin − Sakit) × 100; bila pembagi ≤ 0 ditampilkan "—".<br>
        @endif
        Dicetak oleh sistem SIAP KNBI pada {{ \Carbon\Carbon::now('Asia/Jakarta')->translatedFormat('l, d F Y H.i') }} WIB. Jam yang tercatat adalah jam server.
    </div>

    <div class="footer">
        <div class="ttd">
            Guru / Wali Kelas,
            <div class="nama">( ............................. )</div>
        </div>
        <div class="clear"></div>
    </div>
</body>
</html>
