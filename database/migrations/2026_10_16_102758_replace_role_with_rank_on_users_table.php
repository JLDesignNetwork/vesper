<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the two-value role column with the five-rank hierarchy.
     * Existing admins become Admin I; everyone else starts as User II until
     * the rank recompute runs.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rank', 20)->default('user_two')->after('password')->index();
            $table->timestamp('rank_computed_at')->nullable()->after('rank');
        });

        DB::table('users')->where('role', 'admin')->update(['rank' => 'admin_one']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Restore the role column. Admin I and Admin II map back to admin.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('password');
        });

        DB::table('users')->whereIn('rank', ['admin_one', 'admin_two'])->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['rank']);
            $table->dropColumn(['rank', 'rank_computed_at']);
        });
    }
};
