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
            $table->boolean('hide_age')->default(false)->after('location_synced_at');
            $table->boolean('hide_birthday')->default(false)->after('hide_age');
            $table->boolean('hide_location')->default(false)->after('hide_birthday');
            $table->boolean('hide_bio')->default(false)->after('hide_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'hide_age',
                'hide_birthday',
                'hide_location',
                'hide_bio',
            ]);
        });
    }
};
