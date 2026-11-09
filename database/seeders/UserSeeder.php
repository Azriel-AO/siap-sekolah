<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@knbi.sch.id'],
            [
                'name' => 'Admin KNBI',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'guru@knbi.sch.id'],
            [
                'name' => 'Bu Sari',
                'password' => Hash::make('password'),
                'role' => 'guru',
            ]
        );

        User::updateOrCreate(
            ['email' => 'siswa@knbi.sch.id'],
            [
                'name' => 'Raka Siswa',
                'password' => Hash::make('password'),
                'role' => 'siswa',
            ]
        );
    }
}
