<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cogs', function (Blueprint $table) {
            $table->timestamp('approval_expires_at')->nullable()->after('approval_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('cogs', function (Blueprint $table) {
            $table->dropColumn('approval_expires_at');
        });
    }
};
