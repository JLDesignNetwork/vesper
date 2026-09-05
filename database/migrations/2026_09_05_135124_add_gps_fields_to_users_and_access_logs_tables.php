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
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('city')->nullable()->after('longitude');
            $table->string('country')->nullable()->after('city');
            $table->string('country_code', 10)->nullable()->after('country');
            $table->timestamp('location_synced_at')->nullable()->after('country_code');
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('room_id')->constrained('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'city',
                'country',
                'country_code',
                'location_synced_at',
            ]);
        });
    }
};
