<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cogs', function (Blueprint $table) {
            $table->id();
            $table->string('cog_no')->unique();
            $table->date('document_date');
            $table->string('consignee')->nullable();
            $table->string('shipper')->nullable();
            $table->string('destination')->nullable();
            $table->string('vessel')->nullable();
            $table->string('department')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_email')->nullable();
            $table->string('receiver_designation')->nullable();
            $table->string('issued_by_name')->nullable();
            $table->string('issued_by_designation')->nullable();
            $table->string('checked_by_name')->nullable();
            $table->string('checked_by_designation')->nullable();
            $table->string('status')->default('draft');
            $table->string('approval_token')->nullable()->unique();
            $table->timestamp('approval_sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('receiver_remarks')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cogs');
    }
};
