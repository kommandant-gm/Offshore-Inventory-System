<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('miri_construction_items', function (Blueprint $table) {
            $table->timestamp('stock_initialized_at')->nullable();
            $table->unsignedBigInteger('stock_revision')->default(0);
        });
        Schema::table('miri_cogs', function (Blueprint $table) {
            $table->boolean('construction_stock_workflow')->default(false);
            $table->timestamp('construction_stock_confirmed_at')->nullable();
        });
        Schema::table('miri_cog_items', fn (Blueprint $table) => $table->foreignId('construction_destination_id')->nullable()->constrained('miri_construction_items'));
        Schema::create('miri_construction_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('construction_item_id')->constrained('miri_construction_items');
            $table->foreignId('cog_item_id')->nullable()->constrained('miri_cog_items');
            $table->foreignId('related_item_id')->nullable()->constrained('miri_construction_items');
            $table->string('kind', 30);
            $table->string('unit');
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_before', 14, 3)->nullable();
            $table->decimal('balance_after', 14, 3);
            $table->string('location');
            $table->string('reference')->nullable();
            $table->text('note');
            $table->string('request_key', 120)->unique();
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at');
            $table->index(['construction_item_id', 'id'], 'construction_stock_history');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('miri_construction_stock_movements');
        Schema::table('miri_cog_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('construction_destination_id'));
        Schema::table('miri_cogs', fn (Blueprint $table) => $table->dropColumn(['construction_stock_workflow', 'construction_stock_confirmed_at']));
        Schema::table('miri_construction_items', fn (Blueprint $table) => $table->dropColumn(['stock_initialized_at', 'stock_revision']));
    }
};
