<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('password');
            $table->date('birthday')->nullable()->after('role');
            $table->string('gender')->nullable()->after('birthday');
            $table->string('location')->nullable()->after('gender');
            $table->text('bio')->nullable()->after('location');
        });

        // Ensure any existing administrator accounts are explicitly marked with admin role
        \Illuminate\Support\Facades\DB::table('users')->update(['role' => 'admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'birthday', 'gender', 'location', 'bio']);
        });
    }
};

