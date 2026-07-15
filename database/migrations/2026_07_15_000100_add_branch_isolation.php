<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'locations', 'inventory_items', 'inventory_transactions',
        'inventory_location_balances', 'stocktakes', 'cogs', 'assets',
        'asset_movements', 'audit_logs', 'document_sequences',
    ];

    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access_level')->default('read');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['branch_id', 'user_id']);
        });

        DB::table('branches')->insert([
            ['code' => 'MIRI', 'name' => 'Miri Inventory', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'KL-IT', 'name' => 'KL IT Inventory', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('branch_id')->nullable()->index()->constrained()->nullOnDelete();
            });
        }

        $miriId = DB::table('branches')->where('code', 'MIRI')->value('id');
        $klId = DB::table('branches')->where('code', 'KL-IT')->value('id');

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                DB::table($table)->whereNull('branch_id')->update(['branch_id' => $miriId]);
            }
        }

        $miriUsers = ['duyan', 'leekp', 'mariesim', 'suhaileysuhailim', 'terrencelim'];
        foreach (DB::table('users')->select('id', 'username')->get() as $user) {
            $username = strtolower((string) $user->username);
            if ($username === 'codex') {
                DB::table('branch_user')->insert([
                    ['branch_id' => $klId, 'user_id' => $user->id, 'access_level' => 'manage', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
                    ['branch_id' => $miriId, 'user_id' => $user->id, 'access_level' => 'manage', 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ]);
            } elseif (in_array($username, ['jasri.ishak', 'muhammad.amir'], true)) {
                DB::table('branch_user')->insert([
                    ['branch_id' => $klId, 'user_id' => $user->id, 'access_level' => 'edit', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
                    ['branch_id' => $miriId, 'user_id' => $user->id, 'access_level' => 'read', 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ]);
                $permissions = json_decode((string) DB::table('users')->where('id', $user->id)->value('permissions'), true) ?: [];
                $permissions['it_assets'] = 'edit';
                DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($permissions)]);
            } else {
                DB::table('branch_user')->insert([
                    'branch_id' => in_array($username, $miriUsers, true) ? $miriId : $klId,
                    'user_id' => $user->id,
                    'access_level' => in_array($username, $miriUsers, true) ? 'read' : 'edit',
                    'is_default' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('branch_id'));
            }
        }
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('branches');
    }
};
