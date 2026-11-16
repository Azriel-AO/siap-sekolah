<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\IzinSakit;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DummyStudentSeeder extends Seeder
{
    /**
     * Daftar nama depan laki-laki Indonesia yang realistis.
     *
     * @var list<string>
     */
    private const FIRST_NAMES_MALE = [
        'Budi', 'Rizky', 'Dimas', 'Bagas', 'Farhan', 'Aldi', 'Reza', 'Fajar',
        'Ilham', 'Wahyu', 'Bayu', 'Arif', 'Rendra', 'Galih', 'Naufal', 'Hendra',
        'Raka', 'Dafa', 'Yoga', 'Iqbal', 'Taufik', 'Satria', 'irfan', 'Gilang',
        'Akbar', 'Zaki', 'Fikri', 'Danu', 'Rian', 'Aditya', 'Rama', 'Kevin',
        'ifdhal', 'Bima', 'Cahyo', 'Deni', 'Eko', 'Fauzan', 'Guntur',
    ];

    /**
     * Daftar nama depan perempuan Indonesia yang realistis.
     *
     * @var list<string>
     */
    private const FIRST_NAMES_FEMALE = [
        'Siti', 'Aulia', 'Nabila', 'Putri', 'Citra', 'Dewi', 'Anisa', 'Rina',
        'Ayu', 'Intan', 'Adinda', 'Gita', 'Lestari', 'Nadia', 'amelia', 'Tiara',
        'Dian', 'Fitri', 'Salma', 'Zahra', 'Vina', 'Mega', 'Ratna', 'Salsa',
        'Indah', 'Melati', 'Wulan', 'Yuni', 'Kartika', 'Maya', 'Nurmala', 'Shinta',
        'Tari', 'Ulfa', 'Vivi', 'Widya', 'Yulia', 'Zulfa', 'hana',
    ];

    /**
     * Daftar nama belakang Indonesia yang realistis.
     *
     * @var list<string>
     */
    private const LAST_NAMES = [
        'Santoso', 'Aminah', 'Pratama', 'Saputra', 'Wijaya', 'Nugroho', 'Hidayat', 'Setiawan',
        'Maharani', 'Lestari', 'Kusuma', 'Permata', 'Ramadhan', 'Maulana', 'Wibowo', 'Susanto',
        'Utami', 'Rahmawati', 'Purnomo', 'Kurniawan', 'Handayani', 'Suryani', 'Firmansyah', 'Gunawan',
        'Hakim', 'Irawan', 'Jatmiko', 'Wardani', 'Novitasari', 'Octaviani', 'Pangestu', 'Qolbi',
        'hardiansyah', 'Sulistyo', 'Triyono', 'Utomo', 'Wahidin', 'Yulianto', 'Zulkarnain',
    ];

    /**
     * Generate 78 siswa dummy realistis (terdistribusi merata 2 siswa di setiap 39 kelas SMK KNBI)
     * beserta akun login otomatis ({NISN}@knbi.sch.id) dan 27 catatan kehadiran/izin 3 hari terakhir.
     * Bersifat idempotent (aman dijalankan berulang kali tanpa duplikasi maupun menghapus data asli).
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SchoolClassSeeder::class,
        ]);

        $classes = SchoolClass::orderBy('id')->get();
        if ($classes->isEmpty()) {
            return;
        }

        $totalStudents = 78; // 2 siswa per kelas untuk 39 kelas (dalam rentang 50-100 siswa)
        $classCount = $classes->count();
        $baseCreatedAt = Carbon::now('Asia/Jakarta')->subDays(14)->startOfDay();
        $defaultPasswordHash = Hash::make('password');
        $hasStudentIdCol = Schema::hasColumn('users', 'student_id');

        $seededStudents = [];

        for ($i = 0; $i < $totalStudents; $i++) {
            $isFemale = ($i % 2) === 1;
            $gender = $isFemale ? 'P' : 'L';

            $firstNamePool = $isFemale ? self::FIRST_NAMES_FEMALE : self::FIRST_NAMES_MALE;
            $firstName = ucfirst($firstNamePool[$i % count($firstNamePool)]);
            $lastName = ucfirst(self::LAST_NAMES[(($i * 7) + 3) % count(self::LAST_NAMES)]);
            $fullName = "{$firstName} {$lastName}";

            // NISN 10 digit unik deterministik: 0085100001 s/d 0085100078
            $nisn = sprintf('008510%04d', $i + 1);
            $induk = sprintf('24%04d', $i + 1);

            // Distribusi merata di seluruh 39 kelas
            $targetClass = $classes[$i % $classCount];

            $student = Student::updateOrCreate(
                ['nisn' => $nisn],
                [
                    'school_class_id' => $targetClass->id,
                    'nis' => $nisn,
                    'induk' => $induk,
                    'name' => $fullName,
                    'gender' => $gender,
                    'fingerprint_id' => sprintf('DUMMY-FP-%03d', $i + 1),
                ]
            );

            if ($student->created_at && $student->created_at->gt($baseCreatedAt)) {
                $student->timestamps = false;
                $student->created_at = $baseCreatedAt;
                $student->save();
                $student->timestamps = true;
            }

            $userAttrs = [
                'name' => $fullName,
                'password' => $defaultPasswordHash,
                'role' => 'siswa',
            ];
            if ($hasStudentIdCol) {
                $userAttrs['student_id'] = $student->id;
            }

            $user = User::updateOrCreate(
                ['email' => "{$nisn}@knbi.sch.id"],
                $userAttrs
            );

            if ((int) $student->user_id !== (int) $user->id) {
                $student->forceFill(['user_id' => $user->id])->saveQuietly();
            }

            RfidCard::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'card_uid' => sprintf('DUMMY-CARD-%03d', $i + 1),
                    'is_active' => true,
                ]
            );

            $seededStudents[] = $student;
        }

        // Generate 27 record kehadiran & izin untuk 3 hari terakhir (9 record per hari x 3 hari = 27 record)
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $adminId = User::where('role', 'admin')->value('id');

        for ($dayOffset = 0; $dayOffset < 3; $dayOffset++) {
            $targetDate = $today->copy()->subDays($dayOffset);
            $dateStr = $targetDate->toDateString();

            for ($slot = 0; $slot < 9; $slot++) {
                $studentIndex = ($dayOffset * 9 + $slot) % count($seededStudents);
                $student = $seededStudents[$studentIndex];

                if ($slot < 6) {
                    // 6 siswa HADIR tepat waktu (06:35 - 06:55)
                    $minute = 35 + ($slot * 3);
                    $checkIn = sprintf('06:%02d:15', $minute);
                    $checkOut = $dayOffset > 0 ? sprintf('15:%02d:00', 30 + $slot) : null;

                    Attendance::updateOrCreate(
                        ['student_id' => $student->id, 'date' => $dateStr],
                        [
                            'school_class_id' => $student->school_class_id,
                            'check_in_at' => $checkIn,
                            'check_out_at' => $checkOut,
                            'status' => 'HADIR',
                            'last_tap_at' => Carbon::parse("{$dateStr} {$checkIn}", 'Asia/Jakarta'),
                            'kiosk_device' => 'gerbang-kartu',
                        ]
                    );
                } elseif ($slot < 8) {
                    // 2 siswa TERLAMBAT (07:05 - 07:15)
                    $minute = 5 + (($slot - 6) * 7);
                    $checkIn = sprintf('07:%02d:30', $minute);
                    $checkOut = $dayOffset > 0 ? '15:35:00' : null;

                    Attendance::updateOrCreate(
                        ['student_id' => $student->id, 'date' => $dateStr],
                        [
                            'school_class_id' => $student->school_class_id,
                            'check_in_at' => $checkIn,
                            'check_out_at' => $checkOut,
                            'status' => 'TERLAMBAT',
                            'last_tap_at' => Carbon::parse("{$dateStr} {$checkIn}", 'Asia/Jakarta'),
                            'kiosk_device' => 'gerbang-kartu',
                        ]
                    );
                } else {
                    // 1 siswa IZIN
                    IzinSakit::updateOrCreate(
                        ['student_id' => $student->id, 'tanggal' => $dateStr],
                        [
                            'status' => 'izin',
                            'keterangan' => 'Keperluan keluarga',
                            'recorded_by' => $adminId,
                        ]
                    );
                }
            }
        }
    }
}
