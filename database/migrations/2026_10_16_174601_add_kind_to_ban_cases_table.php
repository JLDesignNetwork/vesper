<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ban cases can also vote on lifting a ban (owner decision Q13). Existing cases are ban votes.
     */
    public function up(): void
    {
        Schema::table('ban_cases', function (Blueprint $table) {
            $table->string('kind', 10)->default('ban')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ban_cases', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
