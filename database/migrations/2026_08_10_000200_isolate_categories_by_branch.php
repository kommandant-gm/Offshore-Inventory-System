<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('id')->index()->constrained()->nullOnDelete();
            $table->dropUnique(['code']);
        });

        $miriBranchId = DB::table('branches')->where('code', 'MIRI')->value('id');

        foreach (DB::table('categories')->get() as $category) {
            $usedBranchIds = collect(['inventory_items', 'assets'])
                ->flatMap(fn (string $table) => Schema::hasTable($table)
                    ? DB::table($table)->where('category_id', $category->id)->whereNotNull('branch_id')->pluck('branch_id')
                    : collect())
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($usedBranchIds->isEmpty()) {
                $usedBranchIds = collect([(int) $miriBranchId]);
            }

            $primaryBranchId = $usedBranchIds->sort()->first();
            DB::table('categories')->where('id', $category->id)->update(['branch_id' => $primaryBranchId]);

            foreach ($usedBranchIds->reject(fn (int $branchId) => $branchId === $primaryBranchId) as $branchId) {
                $cloneId = DB::table('categories')->insertGetId([
                    'branch_id' => $branchId,
                    'code' => $category->code,
                    'name' => $category->name,
                    'type' => $category->type,
                    'active' => $category->active,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ]);

                foreach (['inventory_items', 'assets'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)
                            ->where('category_id', $category->id)
                            ->where('branch_id', $branchId)
                            ->update(['category_id' => $cloneId]);
                    }
                }
            }
        }

        Schema::table('categories', function (Blueprint $table): void {
            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['branch_id', 'code']);
            $table->unique('code');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
