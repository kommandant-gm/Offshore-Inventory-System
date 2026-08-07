<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_activity_logs', function (Blueprint $table) {
            $table->text('body')->nullable()->after('subject');
            $table->json('details')->nullable()->after('body');
            $table->string('action_url')->nullable()->after('details');
            $table->string('action_label')->nullable()->after('action_url');
            $table->string('attachment_name')->nullable()->after('action_label');
        });
    }

    public function down(): void
    {
        Schema::table('email_activity_logs', function (Blueprint $table) {
            $table->dropColumn(['body', 'details', 'action_url', 'action_label', 'attachment_name']);
        });
    }
};
