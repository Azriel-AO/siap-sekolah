<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat Kelas X TKJ 1
        $class = SchoolClass::updateOrCreate(
            ['slug' => 'X-TKJ-1'],
            [
                'name' => 'X TKJ 1',
                'department' => 'Teknik Komputer & Jaringan',
                'entry_time' => '07:00:00',
                'exit_time' => '15:30:00',
            ]
        );

        // Ambil akun user siswa demo
        $userSiswa = User::where('email', 'siswa@knbi.sch.id')->first();

        // 2. Daftar 10 Siswa
        $studentsData = [
            ['nis' => '10240101', 'name' => 'Raka Pratama', 'gender' => 'L', 'card' => 'CARD-TKJ-001', 'user_id' => $userSiswa?->id],
            ['nis' => '10240102', 'name' => 'Adinda Rahma', 'gender' => 'P', 'card' => 'CARD-TKJ-002', 'user_id' => null],
            ['nis' => '10240103', 'name' => 'Budi Santoso', 'gender' => 'L', 'card' => 'CARD-TKJ-003', 'user_id' => null],
            ['nis' => '10240104', 'name' => 'Citra Lestari', 'gender' => 'P', 'card' => 'CARD-TKJ-004', 'user_id' => null],
            ['nis' => '10240105', 'name' => 'Dimas Wahyu', 'gender' => 'L', 'card' => 'CARD-TKJ-005', 'user_id' => null],
            ['nis' => '10240106', 'name' => 'Eka Saputra', 'gender' => 'L', 'card' => 'CARD-TKJ-006', 'user_id' => null],
            ['nis' => '10240107', 'name' => 'Farhan Maulana', 'gender' => 'L', 'card' => 'CARD-TKJ-007', 'user_id' => null],
            ['nis' => '10240108', 'name' => 'Gita Maharani', 'gender' => 'P', 'card' => 'CARD-TKJ-008', 'user_id' => null],
            ['nis' => '10240109', 'name' => 'Hendra Wijaya', 'gender' => 'L', 'card' => 'CARD-TKJ-009', 'user_id' => null], // Belum absen
            ['nis' => '10240110', 'name' => 'Intan Permata', 'gender' => 'P', 'card' => 'CARD-TKJ-010', 'user_id' => null], // Belum absen
        ];

        $today = Carbon::now('Asia/Jakarta')->toDateString();

        foreach ($studentsData as $idx => $s) {
            $student = Student::updateOrCreate(
                ['nis' => $s['nis']],
                [
                    'school_class_id' => $class->id,
                    'user_id' => $s['user_id'],
                    'name' => $s['name'],
                    'gender' => $s['gender'],
                ]
            );

            RfidCard::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'card_uid' => $s['card'],
                    'is_active' => true,
                ]
            );

            // Buat data absensi hari ini untuk siswa 1 s.d. 8 (siswa 9 & 10 dibiarkan belum absen untuk live demo)
            if ($idx === 0) { // Raka (Hadir)
                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $today],
                    [
                        'school_class_id' => $class->id,
                        'check_in_at' => '06:48:15',
                        'status' => 'HADIR',
                        'last_tap_at' => Carbon::parse("$today 06:48:15"),
                        'kiosk_device' => 'kiosk-tkj-1',
                    ]
                );
            } elseif ($idx === 1) { // Adinda (Hadir)
                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $today],
                    [
                        'school_class_id' => $class->id,
                        'check_in_at' => '06:52:40',
                        'status' => 'HADIR',
                        'last_tap_at' => Carbon::parse("$today 06:52:40"),
                        'kiosk_device' => 'kiosk-tkj-1',
                    ]
                );
            } elseif ($idx === 2) { // Budi (Hadir)
                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $today],
                    [
                        'school_class_id' => $class->id,
                        'check_in_at' => '06:58:10',
                        'status' => 'HADIR',
                        'last_tap_at' => Carbon::parse("$today 06:58:10"),
                        'kiosk_device' => 'kiosk-tkj-1',
                    ]
                );
            } elseif ($idx === 3) { // Citra (Terlambat)
                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $today],
                    [
                        'school_class_id' => $class->id,
                        'check_in_at' => '07:08:22',
                        'status' => 'TERLAMBAT',
                        'last_tap_at' => Carbon::parse("$today 07:08:22"),
                        'kiosk_device' => 'kiosk-tkj-1',
                    ]
                );
            } elseif ($idx === 4) { // Dimas (Terlambat)
                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $today],
                    [
                        'school_class_id' => $class->id,
                        'check_in_at' => '07:14:05',
                        'status' => 'TERLAMBAT',
                        'last_tap_at' => Carbon::parse("$today 07:14:05"),
                        'kiosk_device' => 'kiosk-tkj-1',
                    ]
                );
            } elseif ($idx === 5) { // Eka (Hadir & Sudah Pulang)
                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $today],
                    [
                        'school_class_id' => $class->id,
                        'check_in_at' => '06:45:00',
                        'check_out_at' => '15:35:10',
                        'status' => 'HADIR',
                        'last_tap_at' => Carbon::parse("$today 15:35:10"),
                        'kiosk_device' => 'kiosk-tkj-1',
                    ]
                );
            } elseif ($idx === 6) { // Farhan (Hadir)
                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $today],
                    [
                        'school_class_id' => $class->id,
                        'check_in_at' => '06:59:12',
                        'status' => 'HADIR',
                        'last_tap_at' => Carbon::parse("$today 06:59:12"),
                        'kiosk_device' => 'kiosk-tkj-1',
                    ]
                );
            } elseif ($idx === 7) { // Gita (Terlambat)
                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'date' => $today],
                    [
                        'school_class_id' => $class->id,
                        'check_in_at' => '07:04:45',
                        'status' => 'TERLAMBAT',
                        'last_tap_at' => Carbon::parse("$today 07:04:45"),
                        'kiosk_device' => 'kiosk-tkj-1',
                    ]
                );
            }
            // Siswa idx 8 (Hendra) dan idx 9 (Intan) TIDAK dibuat absensinya hari ini (status Belum Absen)
        }
    }
}
