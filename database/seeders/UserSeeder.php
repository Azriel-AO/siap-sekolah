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
    }
}
