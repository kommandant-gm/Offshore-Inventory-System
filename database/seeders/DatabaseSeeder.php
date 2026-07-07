<?php

namespace Database\Seeders;

use App\Support\AccessMatrix;
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
        $adminPermissions = AccessMatrix::permissionsForRole('admin');

        User::query()
            ->where('email', 'admin@admin.com')
            ->delete();

        User::updateOrCreate(
            ['username' => 'codex'],
            [
                'name' => 'Codex',
                'email' => 'codex@local.test',
                'role' => 'admin',
                'permissions' => $adminPermissions,
                'password' => Hash::make('Codex@123'),
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
                    'role' => 'viewer',
                    'password' => $defaultPassword,
                    'email_verified_at' => now(),
                ]
            );
        });
    }
}
