<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('miri_import_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('inventory_type', 20);
            $table->string('filename');
            $table->string('file_path');
            $table->string('status', 20)->default('queued');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('miri_import_tasks'); }
};
