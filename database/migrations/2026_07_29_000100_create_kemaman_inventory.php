<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $branchId = DB::table('branches')->where('code', 'KEMAMAN')->value('id');

        if (! $branchId) {
            $branchId = DB::table('branches')->insertGetId([
                'code' => 'KEMAMAN',
                'name' => 'Kemaman Inventory',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('users')->select('id', 'username')->get() as $user) {
            DB::table('branch_user')->updateOrInsert(
                ['branch_id' => $branchId, 'user_id' => $user->id],
                [
                    'access_level' => strtolower((string) $user->username) === 'codex' ? 'manage' : 'edit',
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        Schema::create('kemaman_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('category')->index();
            $table->string('item_description');
            $table->string('size_swl')->nullable();
            $table->string('unit', 30)->default('EA');
            $table->string('tag_no')->nullable();
            $table->integer('total_quantity')->default(0);
            $table->integer('quantity_in')->default(0);
            $table->integer('quantity_out')->default(0);
            $table->integer('available_quantity')->default(0);
            $table->integer('location_quantity')->default(0);
            $table->integer('damaged_quantity')->default(0);
            $table->integer('beyond_repair_quantity')->default(0);
            $table->integer('not_traceable_quantity')->default(0);
            $table->date('date_issued')->nullable();
            $table->string('location')->nullable();
            $table->string('document_reference')->nullable();
            $table->date('backload_date')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('certificate_no')->nullable();
            $table->date('test_expiry_date')->nullable()->index();
            $table->string('equipment_status')->default('available')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'category']);
            $table->index(['branch_id', 'item_description']);
            $table->index(['branch_id', 'tag_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kemaman_inventory_items');

        $branchId = DB::table('branches')->where('code', 'KEMAMAN')->value('id');
        if ($branchId) {
            DB::table('branch_user')->where('branch_id', $branchId)->delete();
            DB::table('branches')->where('id', $branchId)->delete();
        }
    }
};
