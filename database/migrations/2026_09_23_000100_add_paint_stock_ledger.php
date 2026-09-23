<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('miri_paint_items', function (Blueprint $table) { $table->date('stock_period')->nullable(); });
        DB::table('miri_paint_items')->update(['stock_period' => now('Asia/Kuala_Lumpur')->startOfMonth()->toDateString()]);
        Schema::create('miri_paint_stock_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('paint_item_id')->constrained('miri_paint_items');
            $table->date('period');
            foreach (['opening_cans', 'opening_litres', 'closing_cans', 'closing_litres'] as $column) $table->decimal($column, 14, 3)->nullable();
            $table->timestamp('created_at');
            $table->unique(['paint_item_id', 'period']);
        });
        Schema::create('miri_paint_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('paint_item_id')->constrained('miri_paint_items');
            $table->foreignId('cog_item_id')->nullable()->constrained('miri_cog_items');
            $table->string('kind', 30);
            $table->string('unit', 3);
            $table->decimal('quantity', 14, 3)->nullable();
            $table->decimal('balance_before', 14, 3)->nullable();
            $table->decimal('balance_after', 14, 3)->nullable();
            $table->date('period');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->unique(['cog_item_id', 'kind', 'unit'], 'paint_stock_movement_once');
            $table->index(['paint_item_id', 'period']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('miri_paint_stock_movements');
        Schema::dropIfExists('miri_paint_stock_months');
        Schema::table('miri_paint_items', fn (Blueprint $table) => $table->dropColumn('stock_period'));
    }
};
