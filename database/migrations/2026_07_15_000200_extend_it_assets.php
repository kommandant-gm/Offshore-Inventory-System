<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('operating_system')->nullable()->after('model');
            $table->unsignedSmallInteger('purchase_year')->nullable()->after('acquisition_date');
            $table->string('storage_position')->nullable()->after('current_location_id');
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('assigned_to_name');
            $table->string('employee_id')->nullable();
            $table->string('department')->nullable();
            $table->date('assigned_at');
            $table->date('returned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'returned_at']);
            $table->index(['asset_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn([
            'operating_system', 'purchase_year', 'storage_position',
        ]));
    }
};
