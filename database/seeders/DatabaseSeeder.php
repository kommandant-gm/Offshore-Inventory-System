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
        $adminPermissions = AccessMatrix::permissionsForRole('admin');

        User::query()
            ->where('email', 'admin@admin.com')
            ->delete();

        User::query()
            ->where('username', '!=', 'codex')
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
    }
}
