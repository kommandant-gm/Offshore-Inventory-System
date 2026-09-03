<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('major_equipment') && ! Schema::hasTable('miri_inventory_items')) {
            Schema::rename('major_equipment', 'miri_inventory_items');
        }

        if (Schema::hasTable('major_equipment_certificates') && ! Schema::hasTable('miri_inventory_certificates')) {
            Schema::rename('major_equipment_certificates', 'miri_inventory_certificates');
        }

        if (Schema::hasTable('miri_inventory_certificates') && Schema::hasColumn('miri_inventory_certificates', 'major_equipment_id') && ! Schema::hasColumn('miri_inventory_certificates', 'miri_inventory_item_id')) {
            Schema::table('miri_inventory_certificates', function (Blueprint $table): void {
                $table->renameColumn('major_equipment_id', 'miri_inventory_item_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('miri_inventory_certificates') && Schema::hasColumn('miri_inventory_certificates', 'miri_inventory_item_id') && ! Schema::hasColumn('miri_inventory_certificates', 'major_equipment_id')) {
            Schema::table('miri_inventory_certificates', function (Blueprint $table): void {
                $table->renameColumn('miri_inventory_item_id', 'major_equipment_id');
            });
        }

        if (Schema::hasTable('miri_inventory_certificates') && ! Schema::hasTable('major_equipment_certificates')) {
            Schema::rename('miri_inventory_certificates', 'major_equipment_certificates');
        }

        if (Schema::hasTable('miri_inventory_items') && ! Schema::hasTable('major_equipment')) {
            Schema::rename('miri_inventory_items', 'major_equipment');
        }
    }
};
