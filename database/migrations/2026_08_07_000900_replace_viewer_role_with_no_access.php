<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'viewer')->update(['role' => 'none', 'permissions' => json_encode([
            'dashboard' => 'none', 'assistant' => 'none', 'anomalies' => 'none', 'categories' => 'none',
            'locations' => 'none', 'assets' => 'none', 'it_assets' => 'none', 'movements' => 'none',
            'ledger' => 'none', 'cogs' => 'none', 'settings' => 'none',
        ])]);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'none')->update(['role' => 'viewer']);
    }
};
