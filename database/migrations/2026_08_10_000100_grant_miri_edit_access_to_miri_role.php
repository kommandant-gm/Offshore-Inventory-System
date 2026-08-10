<?php

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

        DB::table('users')
            ->where('role', 'miri')
            ->pluck('id')
            ->each(function ($userId) use ($miriBranchId, $now): void {
                DB::table('branch_user')
                    ->where('user_id', $userId)
                    ->where('branch_id', '!=', $miriBranchId)
                    ->delete();

                DB::table('branch_user')->updateOrInsert(
                    ['branch_id' => $miriBranchId, 'user_id' => $userId],
                    ['access_level' => 'edit', 'is_default' => true, 'updated_at' => $now, 'created_at' => $now],
                );
            });
    }

    public function down(): void
    {
        // Existing branch access is intentionally not downgraded on rollback.
    }
};
