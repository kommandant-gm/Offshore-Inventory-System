<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index(['item_id', 'transaction_date', 'id'], 'inventory_transactions_item_date_id_index');
            $table->index(['transaction_date', 'id'], 'inventory_transactions_date_id_index');
        });

        Schema::table('inventory_location_balances', function (Blueprint $table) {
            $table->index(['location_id', 'inventory_item_id'], 'inventory_location_balances_location_item_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inventory_transactions_item_date_id_index');
            $table->dropIndex('inventory_transactions_date_id_index');
        });

        Schema::table('inventory_location_balances', function (Blueprint $table) {
            $table->dropIndex('inventory_location_balances_location_item_index');
        });
    }
};
