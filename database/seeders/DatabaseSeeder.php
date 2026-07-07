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
        $defaultPassword = Hash::make('Dayang@123');

        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        collect([
            ['name' => 'Marie Sim', 'username' => 'marie.sim', 'email' => 'mariesim@desb.net'],
            ['name' => 'Patrick Leong', 'username' => 'patrick.leong', 'email' => 'patrickleong@desb.net'],
            ['name' => 'Duyan Ak Jemat', 'username' => 'duyan', 'email' => 'duyan@desb.net'],
            ['name' => 'Leekp', 'username' => 'leekp', 'email' => 'leekp@desb.net'],
            ['name' => 'Christopher Ung', 'username' => 'ung.christopher', 'email' => 'ung.christopher@yahoo.com'],
            ['name' => 'Alex Leong', 'username' => 'alex.leong', 'email' => 'alexleong@desb.net'],
        ])->each(function (array $user) use ($defaultPassword): void {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'password' => $defaultPassword,
                    'email_verified_at' => now(),
                ]
            );
        });
    }
}
