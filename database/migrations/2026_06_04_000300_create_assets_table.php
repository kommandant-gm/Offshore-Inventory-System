<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag_no')->unique();
            $table->string('description');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('year')->nullable();
            $table->string('ownership')->nullable();
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('current_status');
            $table->string('current_condition')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 12, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'current_status']);
            $table->index('current_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
