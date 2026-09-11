<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miri_paint_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('category')->nullable();
            $table->string('section_1')->nullable();
            $table->string('section_2')->nullable();
            $table->string('description')->nullable();
            $table->decimal('opening_cans', 14, 3)->nullable();
            $table->decimal('opening_litres', 14, 3)->nullable();
            $table->decimal('opening_unit_price', 14, 2)->nullable();
            $table->decimal('opening_total_price', 14, 2)->nullable();
            $table->decimal('balance_cans', 14, 3)->nullable();
            $table->decimal('balance_litres', 14, 3)->nullable();
            $table->decimal('closing_unit_price', 14, 2)->nullable();
            $table->decimal('closing_total_price', 14, 2)->nullable();
            $table->string('current_location')->nullable();
            $table->string('batch_no')->nullable();
            $table->text('original_date')->nullable();
            $table->string('issue_location')->nullable();
            $table->text('issue_cog')->nullable();
            $table->decimal('issue_cans', 14, 3)->nullable();
            $table->decimal('issue_litres', 14, 3)->nullable();
            $table->decimal('issue_total_price', 14, 2)->nullable();
            $table->text('mr_reference')->nullable();
            $table->text('po_reference')->nullable();
            $table->text('do_reference')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('stock_in_qty', 14, 3)->nullable();
            $table->string('storage_rack')->nullable();
            $table->string('backload_location')->nullable();
            $table->text('backload_cog')->nullable();
            $table->decimal('backload_qty', 14, 3)->nullable();
            $table->string('backload_rack')->nullable();
            $table->text('unfit_report')->nullable();
            $table->text('writeoff_reference')->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('best_before_date')->nullable();
            $table->date('unconfirmed_date')->nullable();
            $table->string('date_status', 20)->default('not_recorded');
            $table->string('match_key', 64)->nullable();
            $table->longText('source_values')->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_filename')->nullable();
            $table->json('import_warnings')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'match_key']);
            $table->index(['branch_id', 'date_status', 'best_before_date']);
            $table->index(['branch_id', 'needs_review']);
            $table->index(['branch_id', 'section_2']);
            $table->index(['branch_id', 'current_location']);
        });
        Schema::create('miri_paint_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('file_hash', 64);
            $table->string('active_hash', 64)->nullable();
            $table->string('filename');
            $table->string('file_path');
            $table->string('status', 20)->default('queued');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'active_hash']);
            $table->index(['user_id', 'created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('miri_paint_imports');
        Schema::dropIfExists('miri_paint_items');
    }
};
