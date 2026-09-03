<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miri_inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('MAJOR EQUIPMENT');
            $table->string('section_1')->nullable();
            $table->string('section_2')->nullable();
            $table->string('description')->nullable();
            $table->string('unit')->nullable();
            $table->string('model_brand')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('tag_no')->nullable();
            $table->string('current_location')->nullable();
            $table->string('status')->nullable();
            $table->string('issue_out_location')->nullable();
            $table->string('issue_out_cog_no')->nullable();
            $table->date('issue_out_cog_date')->nullable();
            $table->string('received_backload_cog_no')->nullable();
            $table->date('received_backload_cog_date')->nullable();
            $table->string('mr_request')->nullable();
            $table->string('purchase_order')->nullable();
            $table->string('delivery_order')->nullable();
            $table->string('supplier')->nullable();
            $table->text('unfit_report')->nullable();
            $table->text('write_off_reference')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'category']);
            $table->index(['branch_id', 'section_1', 'section_2']);
            $table->index(['branch_id', 'status']);
            $table->unique(['branch_id', 'tag_no']);
        });

        Schema::create('miri_inventory_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('miri_inventory_item_id')->constrained('miri_inventory_items')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_type');
            $table->string('certificate_no')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('raw_value')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'expiry_date']);
            $table->index(['miri_inventory_item_id', 'certificate_type'], 'miri_inv_cert_item_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miri_inventory_certificates');
        Schema::dropIfExists('miri_inventory_items');
    }
};
