<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->string('assigned_email')->nullable()->after('assigned_to_name');
            $table->string('checkout_status')->default('signed')->after('remarks');
            $table->string('checkout_token')->nullable()->unique()->after('checkout_status');
            $table->timestamp('checkout_sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->longText('signature')->nullable();
            $table->string('signed_ip')->nullable();
            $table->text('signed_user_agent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropUnique(['checkout_token']);
            $table->dropColumn(['assigned_email', 'checkout_status', 'checkout_token', 'checkout_sent_at', 'signed_at', 'signature', 'signed_ip', 'signed_user_agent']);
        });
    }
};
