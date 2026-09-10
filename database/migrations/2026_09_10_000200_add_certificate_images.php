<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miri_inventory_certificates', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->string('image_name')->nullable();
            $table->string('image_mime', 40)->nullable();
            $table->unsignedInteger('image_size')->nullable();
            $table->foreignId('image_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('image_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        // File deletion must be reviewed separately; never discard attachment references automatically.
        throw new RuntimeException('Back up certificate files and metadata before manually reverting this migration.');
    }
};
