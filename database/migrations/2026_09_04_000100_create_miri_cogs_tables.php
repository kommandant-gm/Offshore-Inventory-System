<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('miri_cogs', function (Blueprint $table): void {
            $table->id(); $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('cog_no'); $table->string('movement_type'); $table->date('document_date');
            $table->string('from_location')->nullable(); $table->string('to_location')->nullable();
            $table->string('receiver_name')->nullable(); $table->string('receiver_email')->nullable();
            $table->string('issued_by_name')->nullable(); $table->text('remarks')->nullable();
            $table->string('status')->default('draft'); $table->longText('signature')->nullable();
            $table->timestamp('signed_at')->nullable(); $table->string('signed_ip', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->unique(['branch_id', 'cog_no']); $table->index(['branch_id', 'status']);
        });
        Schema::create('miri_cog_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('miri_cog_id')->constrained('miri_cogs')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('item_type'); $table->unsignedBigInteger('item_id'); $table->string('identifier')->nullable();
            $table->string('description')->nullable(); $table->decimal('quantity', 12, 2)->default(1); $table->string('unit')->nullable();
            $table->string('current_location')->nullable(); $table->text('remarks')->nullable(); $table->timestamps();
            $table->index(['branch_id', 'item_type', 'item_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('miri_cog_items'); Schema::dropIfExists('miri_cogs'); }
};
