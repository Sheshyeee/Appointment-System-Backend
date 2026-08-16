<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'dmbc2022-2141-53989@bicol-u.edu.ph'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admindave'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
