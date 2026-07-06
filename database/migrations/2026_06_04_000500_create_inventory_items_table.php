<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('description');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('uom');
            $table->foreignId('default_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->decimal('opening_stock', 12, 2)->default(0);
            $table->decimal('standard_cost', 12, 2)->default(0);
            $table->decimal('minimum_stock', 12, 2)->nullable();
            $table->string('rack_no')->nullable();
            $table->boolean('active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
