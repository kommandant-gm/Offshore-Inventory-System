<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_movement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_assignment_id')->constrained('asset_assignments')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('filename');
            $table->string('path');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['document_type', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_movement_documents');
    }
};
