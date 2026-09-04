<?php

use App\Support\AccessMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $miriBranchId = DB::table('branches')->where('code', 'MIRI')->value('id');

        if (! $miriBranchId) {
            return;
        }

        $now = now();
        $permissions = json_encode(AccessMatrix::permissionsForRole('miri'), JSON_THROW_ON_ERROR);
        $password = '$2y$10$RwIpBv2nhbJJrL4ICHUQ5OfUagAiI9NTsW/CKttuhppdXzS2x8nB2';

        foreach ([
            ['username' => 'philipsiju', 'name' => 'Philips Iju'],
            ['username' => 'cyenthiaanggelie', 'name' => 'Cyenthia Anggelie'],
        ] as $account) {
            DB::table('users')->updateOrInsert(
                ['username' => $account['username']],
                [
                    'name' => $account['name'],
                    'email' => $account['username'].'@local.test',
                    'department' => 'HQ-Inventory',
                    'role' => 'miri',
                    'permissions' => $permissions,
                    'directory_active' => true,
                    'email_verified_at' => $now,
                    'password' => $password,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $userId = DB::table('users')->where('username', $account['username'])->value('id');
            DB::table('branch_user')->updateOrInsert(
                ['branch_id' => $miriBranchId, 'user_id' => $userId],
                ['access_level' => 'edit', 'is_default' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $userIds = DB::table('users')->whereIn('username', ['philipsiju', 'cyenthiaanggelie'])->pluck('id');
        DB::table('branch_user')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }
};
