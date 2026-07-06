<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->date('movement_date');
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type');
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('condition_before')->nullable();
            $table->string('condition_after')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('handled_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('project_ref')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'movement_date']);
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};
