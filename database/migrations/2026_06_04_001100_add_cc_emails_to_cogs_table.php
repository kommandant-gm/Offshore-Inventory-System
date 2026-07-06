<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cogs', function (Blueprint $table) {
            $table->text('cc_emails')->nullable()->after('receiver_email');
        });
    }

    public function down(): void
    {
        Schema::table('cogs', function (Blueprint $table) {
            $table->dropColumn('cc_emails');
        });
    }
};
