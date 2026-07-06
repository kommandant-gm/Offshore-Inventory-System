<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cog_items', function (Blueprint $table) {
            $table->decimal('measurement_cu_metre', 12, 3)->nullable()->after('full_description');
            $table->decimal('gross_weight_kg', 12, 3)->nullable()->after('measurement_cu_metre');
            $table->string('po_no')->nullable()->after('gross_weight_kg');
            $table->string('ex_location')->nullable()->after('po_no');
        });
    }

    public function down(): void
    {
        Schema::table('cog_items', function (Blueprint $table) {
            $table->dropColumn([
                'measurement_cu_metre',
                'gross_weight_kg',
                'po_no',
                'ex_location',
            ]);
        });
    }
};
