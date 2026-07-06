<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'opening_stock')) {
                $table->decimal('opening_stock', 12, 2)->default(0)->after('default_location_id');
            }

            if (! Schema::hasColumn('inventory_items', 'rack_no')) {
                $table->string('rack_no')->nullable()->after('minimum_stock');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('inventory_items', 'rack_no')) {
                $columnsToDrop[] = 'rack_no';
            }

            if (Schema::hasColumn('inventory_items', 'opening_stock')) {
                $columnsToDrop[] = 'opening_stock';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
