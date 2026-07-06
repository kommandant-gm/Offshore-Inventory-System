<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cog_id')->constrained('cogs')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->decimal('qty', 12, 2)->default(0);
            $table->string('unit')->nullable();
            $table->string('part_no')->nullable();
            $table->string('full_description');
            $table->string('serial_no')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cog_items');
    }
};
