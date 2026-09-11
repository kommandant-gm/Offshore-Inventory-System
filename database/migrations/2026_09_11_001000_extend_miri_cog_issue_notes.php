<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('miri_cogs', function (Blueprint $table) {
            foreach (['consignee_name','consignee_department','from_department','copy_to','destination','issued_designation','verified_by_name','verified_designation','receiver_designation'] as $key) $table->string($key)->nullable();
            foreach (['issued_date','verified_date','received_date'] as $key) $table->date($key)->nullable();
        });
        Schema::table('miri_cog_items', function (Blueprint $table) {
            foreach (['size_model','serial_no','batch_no'] as $key) $table->string($key)->nullable();
            $table->text('mr_reference')->nullable();
            $table->decimal('quantity',14,3)->default(1)->change();
        });
    }
    public function down(): void
    {
        Schema::table('miri_cogs', fn (Blueprint $table) => $table->dropColumn(['consignee_name','consignee_department','from_department','copy_to','destination','issued_designation','verified_by_name','verified_designation','receiver_designation','issued_date','verified_date','received_date']));
        Schema::table('miri_cog_items', fn (Blueprint $table) => $table->dropColumn(['size_model','serial_no','batch_no','mr_reference']));
        // Retain the wider quantity precision to avoid rounding existing documents on rollback.
    }
};
