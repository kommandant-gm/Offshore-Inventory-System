<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miri_inventory_items', function (Blueprint $table) {
            // Database-maintained: imports, model saves and direct/bulk SQL updates
            // all use the same existing LOWER(TRIM(tag_no)) matching rule.
            $table->string('normalized_tag')->nullable()->storedAs("NULLIF(LOWER(TRIM(tag_no)), '')");
            $table->index(['branch_id', 'normalized_tag'], 'miri_branch_normalized_tag_index');
        });
    }

    public function down(): void
    {
        Schema::table('miri_inventory_items', function (Blueprint $table) {
            $table->dropIndex('miri_branch_normalized_tag_index');
            $table->dropColumn('normalized_tag');
        });
    }
};
