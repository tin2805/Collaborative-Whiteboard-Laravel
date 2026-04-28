<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ],
            [
                'name' => 'Demo User',
                'email' => 'demo@example.com',
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('11111111'),
                ]
            );
        }
    }
}
