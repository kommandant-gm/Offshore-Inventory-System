<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        DB::table('users')
            ->select('id', 'name', 'email')
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $baseUsername = Str::of($user->email)
                    ->before('@')
                    ->lower()
                    ->replaceMatches('/[^a-z0-9._-]+/', '.')
                    ->trim('.')
                    ->value();

                if ($baseUsername === '') {
                    $baseUsername = 'user'.$user->id;
                }

                $username = $baseUsername;
                $suffix = 1;

                while (
                    DB::table('users')
                        ->where('username', $username)
                        ->where('id', '!=', $user->id)
                        ->exists()
                ) {
                    $username = $baseUsername.$suffix;
                    $suffix++;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
