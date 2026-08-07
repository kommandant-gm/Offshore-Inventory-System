<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->json('policy_acknowledgments')->nullable()->after('signature');
            $table->timestamp('policy_acknowledged_at')->nullable()->after('policy_acknowledgments');
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropColumn(['policy_acknowledgments', 'policy_acknowledged_at']);
        });
    }
};
