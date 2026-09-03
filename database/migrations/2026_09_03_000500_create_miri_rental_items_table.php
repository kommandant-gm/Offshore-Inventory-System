<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miri_rental_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('section_1')->nullable();
            $table->string('section_2')->nullable();
            $table->string('description')->nullable();
            $table->string('serial_tag_equipment_no')->nullable();
            $table->string('unit')->nullable();
            $table->string('supplier')->nullable();
            $table->string('project_contract')->nullable();
            $table->string('current_location')->nullable();
            $table->date('rental_due_date')->nullable();
            $table->string('issue_out_cog_no')->nullable();
            $table->date('issue_out_cog_date')->nullable();
            $table->string('received_backload_from_location')->nullable();
            $table->string('received_backload_cog_no')->nullable();
            $table->date('received_backload_cog_date')->nullable();
            $table->string('offhire_certificate_no')->nullable();
            $table->date('offhire_certificate_date')->nullable();
            $table->string('return_cog_no')->nullable();
            $table->date('return_cog_date')->nullable();
            $table->string('mr_no')->nullable();
            $table->date('mr_date')->nullable();
            $table->string('po_or_sr_no')->nullable();
            $table->date('po_or_sr_date')->nullable();
            $table->string('do_no')->nullable();
            $table->date('do_date')->nullable();
            $table->string('onhire_certificate_no')->nullable();
            $table->date('onhire_certificate_date')->nullable();
            $table->string('status')->default('On Hire');
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'serial_tag_equipment_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miri_rental_items');
    }
};
