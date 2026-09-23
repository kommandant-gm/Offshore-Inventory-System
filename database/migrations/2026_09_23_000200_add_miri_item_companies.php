<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $tables = ['miri_inventory_items', 'miri_rental_items', 'miri_construction_items', 'miri_paint_items'];
    public function up(): void {
        foreach ($this->tables as $name) Schema::table($name, function (Blueprint $table) {
            $table->string('company', 4)->nullable();
            $table->index(['branch_id', 'company']);
        });
    }
    public function down(): void {
        foreach ($this->tables as $name) Schema::table($name, function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'company']); $table->dropColumn('company');
        });
    }
};
