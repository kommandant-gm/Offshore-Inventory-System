<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->string('checkin_status')->nullable()->after('policy_acknowledged_at');
            $table->string('checkin_token')->nullable()->unique()->after('checkin_status');
            $table->timestamp('checkin_sent_at')->nullable();
            $table->timestamp('checkin_signed_at')->nullable();
            $table->longText('checkin_signature')->nullable();
            $table->string('checkin_signed_ip')->nullable();
            $table->text('checkin_signed_user_agent')->nullable();
            $table->string('checkin_received_by_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropUnique(['checkin_token']);
            $table->dropColumn(['checkin_status', 'checkin_token', 'checkin_sent_at', 'checkin_signed_at', 'checkin_signature', 'checkin_signed_ip', 'checkin_signed_user_agent', 'checkin_received_by_email']);
        });
    }
};
