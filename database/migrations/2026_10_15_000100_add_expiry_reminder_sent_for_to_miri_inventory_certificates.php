<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miri_inventory_certificates', function (Blueprint $table): void {
            $table->date('expiry_reminder_sent_for')->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('miri_inventory_certificates', function (Blueprint $table): void {
            $table->dropColumn('expiry_reminder_sent_for');
        });
    }
};
