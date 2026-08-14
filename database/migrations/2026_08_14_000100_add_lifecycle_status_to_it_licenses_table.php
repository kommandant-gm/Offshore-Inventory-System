<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_licenses', function (Blueprint $table) {
            $table->string('lifecycle_status')->default('active')->after('purchase_reference')->index();
        });
    }

    public function down(): void
    {
        Schema::table('it_licenses', function (Blueprint $table) {
            $table->dropIndex(['lifecycle_status']);
            $table->dropColumn('lifecycle_status');
        });
    }
};
