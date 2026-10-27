<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('miri_inventory_items', fn (Blueprint $table) => $table->string('scaffolding_contract')->nullable());
        Schema::table('miri_cogs', function (Blueprint $table) {
            $table->boolean('scaffolding_stock_workflow')->default(false);
            $table->timestamp('scaffolding_stock_confirmed_at')->nullable();
        });
        Schema::table('miri_cog_items', function (Blueprint $table) {
            $table->boolean('scaffolding_stock')->default(false);
            $table->string('scaffolding_yard', 20)->nullable();
            $table->string('scaffolding_contract')->nullable();
        });
        Schema::create('scaffolding_stock_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('equipment_id')->constrained('miri_inventory_items');
            $table->string('yard', 20);
            $table->string('unit', 20);
            $table->decimal('opening_quantity', 14, 3);
            $table->decimal('balance', 14, 3);
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['equipment_id', 'yard']);
        });
        Schema::create('scaffolding_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('scaffolding_stock_accounts');
            $table->foreignId('cog_item_id')->nullable()->constrained('miri_cog_items');
            $table->string('kind', 30);
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('destination')->nullable();
            $table->string('contract')->nullable();
            $table->string('mr_reference')->nullable();
            $table->string('reference')->nullable();
            $table->date('document_date');
            $table->text('note');
            $table->string('request_key', 120)->unique();
            $table->string('request_hash', 64);
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at');
            $table->index(['account_id', 'id']);
        });
        Schema::create('scaffolding_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('kind', 20);
            $table->string('value');
            $table->unique(['branch_id', 'kind', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scaffolding_options');
        Schema::dropIfExists('scaffolding_stock_movements');
        Schema::dropIfExists('scaffolding_stock_accounts');
        Schema::table('miri_cog_items', fn (Blueprint $table) => $table->dropColumn(['scaffolding_stock', 'scaffolding_yard', 'scaffolding_contract']));
        Schema::table('miri_cogs', fn (Blueprint $table) => $table->dropColumn(['scaffolding_stock_workflow', 'scaffolding_stock_confirmed_at']));
        Schema::table('miri_inventory_items', fn (Blueprint $table) => $table->dropColumn('scaffolding_contract'));
    }
};
