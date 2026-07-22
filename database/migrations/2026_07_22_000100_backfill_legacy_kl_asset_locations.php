<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $branchId = DB::table('branches')->where('code', 'KL-IT')->value('id');
        if (! $branchId) return;

        $location = DB::table('locations')
            ->where('branch_id', $branchId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(code) = ?', ['kl'])
                    ->orWhereRaw('LOWER(name) = ?', ['kl']);
            })
            ->first();

        if (! $location) {
            $code = DB::table('locations')->where('code', 'KL')->exists() ? 'KL-IT-KL' : 'KL';
            $locationId = DB::table('locations')->insertGetId([
                'branch_id' => $branchId,
                'code' => $code,
                'name' => 'KL',
                'type' => 'yard',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $locationId = $location->id;
        }

        DB::table('assets')
            ->where('branch_id', $branchId)
            ->whereNull('current_location_id')
            ->where('remarks', 'Migrated from legacy KL IT inventory.')
            ->update(['current_location_id' => $locationId, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Imported location data is intentionally retained.
    }
};
