<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop children before their legacy parent tables.
        Schema::dropIfExists('stocktake_items');
        Schema::dropIfExists('cog_items');
        Schema::dropIfExists('inventory_location_balances');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('stocktakes');
        Schema::dropIfExists('cogs');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('document_sequences');
    }

    public function down(): void
    {
        // This cleanup is intentionally destructive. Restore from the database backup if required.
    }
};
