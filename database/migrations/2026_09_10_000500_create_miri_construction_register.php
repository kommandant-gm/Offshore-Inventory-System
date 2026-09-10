<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miri_construction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('category')->nullable();
            $table->string('section_1')->nullable();
            $table->string('section_2')->nullable();
            $table->string('description')->nullable();
            $table->string('model_brand')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('tag_no')->nullable();
            $table->decimal('stock_balance', 14, 3)->nullable();
            $table->string('status')->nullable();
            $table->string('current_location')->nullable();
            $table->text('certificate_reference')->nullable();
            $table->date('certificate_due_date')->nullable();
            $table->string('issue_location')->nullable();
            $table->text('issue_location_cog')->nullable();
            $table->decimal('issue_location_qty', 14, 3)->nullable();
            $table->text('personnel_details')->nullable();
            $table->text('issue_personnel_cog')->nullable();
            $table->decimal('issue_personnel_qty', 14, 3)->nullable();
            $table->text('remarks')->nullable();
            $table->text('lifting_inspection')->nullable();
            $table->text('lifting_conformity')->nullable();
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
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('closing_value', 14, 2)->nullable();
            $table->string('normalized_tag')->nullable()->storedAs("NULLIF(LOWER(TRIM(tag_no)), '')");
            $table->index(['branch_id', 'normalized_tag'], 'construction_branch_tag_index');
            $table->index(['branch_id', 'category']);
            $table->index(['branch_id', 'current_location']);
            $table->index(['branch_id', 'certificate_due_date']);
            $table->longText('source_values')->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_filename')->nullable();
            $table->json('import_warnings')->nullable();
            $table->boolean('grouping_review_required')->default(false);
            $table->boolean('grouping_reviewed')->default(false);
            $table->boolean('needs_review')->default(false);
            $table->text('review_note')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'needs_review']);
        });
        Schema::create('miri_construction_imports', function (Blueprint $table) {
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
            $table->unique(['branch_id', 'active_hash'], 'construction_import_hash_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miri_construction_imports');
        Schema::dropIfExists('miri_construction_items');
    }
};
