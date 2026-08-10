<?php

namespace Database\Seeders;

use App\Support\AccessMatrix;
use App\Models\Branch;
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

        User::query()->where('email', 'admin@admin.com')->delete();

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

        $miri = Branch::query()->where('code', 'MIRI')->firstOrFail();
        $miriPermissions = AccessMatrix::permissionsForRole('miri');

        foreach ([
            'mariesim', 'duyan', 'patrickleong', 'leekp', 'christopher', 'alexleong',
            'terrencelim', 'gevrina', 'dywan', 'frankypilai', 'suhaileysuhailim',
        ] as $username) {
            $user = User::query()->firstOrNew(['username' => $username]);
            $user->fill([
                'name' => str($username)->headline()->value(),
                'email' => $user->email ?: "{$username}@local.test",
                'department' => 'HQ-Inventory',
                'role' => 'miri',
                'permissions' => $miriPermissions,
                'directory_active' => true,
                'email_verified_at' => $user->email_verified_at ?: now(),
                'password' => Hash::make('Dayang@123'),
            ]);
            $user->save();
            $user->branches()->sync([
                $miri->id => ['access_level' => 'edit', 'is_default' => true],
            ]);
        }
    }
}
