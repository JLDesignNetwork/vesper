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
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedInteger('media_ttl_seconds')->nullable()->after('ttl_seconds');
            $table->timestamp('media_expires_at')->nullable()->after('expires_at');
        });

        Schema::table('message_user_views', function (Blueprint $table) {
            $table->timestamp('media_expires_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_user_views', function (Blueprint $table) {
            $table->dropColumn('media_expires_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['media_ttl_seconds', 'media_expires_at']);
        });
    }
};
