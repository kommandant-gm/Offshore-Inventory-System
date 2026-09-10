<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miri_inventory_items', function (Blueprint $table) {
            $table->string('inventory_type', 20)->default('machinery')->index();
            $table->string('size_model')->nullable();
            $table->string('size_ton')->nullable();
            $table->string('size_length')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->json('import_warnings')->nullable();
            $table->json('source_values')->nullable();
            $table->dropUnique(['branch_id', 'tag_no']);
            $table->index(['branch_id', 'tag_no']);
        });
        DB::table('miri_inventory_items')->whereRaw("UPPER(TRIM(section_1)) = 'CARGO SET'")->update(['inventory_type' => 'cargo']);
        DB::table('miri_inventory_items')->whereIn(DB::raw('UPPER(TRIM(section_1))'), ['MACHINARY', 'MACHINERY'])->update(['section_1' => 'Machinery']);
        Schema::create('miri_inventory_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('inventory_type', 20);
            $table->string('file_hash', 64);
            $table->string('filename');
            $table->unsignedInteger('records_count');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'inventory_type', 'file_hash'], 'miri_import_file_unique');
        });
    }

    public function down(): void
    {
        // Restoring unique tags would fail or lose data once duplicate tags exist.
        throw new RuntimeException('Cargo inventory rollback requires a reviewed data backup; automatic rollback is disabled.');
    }
};
