<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miri_inventory_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
            $table->unique(['branch_id', 'name']);
        });

        $branchId = DB::table('branches')->where('code', 'MIRI')->value('id');
        if (! $branchId || ! Schema::hasTable('miri_inventory_items')) {
            return;
        }

        $names = DB::table('miri_inventory_items')->where('branch_id', $branchId)
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category');

        foreach ($names as $index => $name) {
            DB::table('miri_inventory_categories')->insert([
                'branch_id' => $branchId,
                'code' => 'MIRI-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'name' => trim((string) $name),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('miri_inventory_categories');
    }
};
