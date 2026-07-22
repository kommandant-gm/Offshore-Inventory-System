<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('license_code');
            $table->string('software_name');
            $table->string('vendor')->nullable();
            $table->string('license_type');
            $table->text('license_key')->nullable();
            $table->unsignedInteger('seats_total')->default(1);
            $table->unsignedInteger('seats_assigned')->default(0);
            $table->string('assigned_to')->nullable();
            $table->string('department')->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->decimal('renewal_cost', 12, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->string('purchase_reference')->nullable();
            $table->boolean('active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'license_code']);
            $table->index(['branch_id', 'expiry_date']);
            $table->index(['branch_id', 'software_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_licenses');
    }
};
