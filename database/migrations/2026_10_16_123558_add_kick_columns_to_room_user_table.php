<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record who kicked a member and when. A kicked member keeps their room_user row
     * with status 'kicked', which blocks re-entry by link, code or public join.
     */
    public function up(): void
    {
        Schema::table('room_user', function (Blueprint $table) {
            $table->foreignId('kicked_by_user_id')->nullable()->after('invited_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('kicked_at')->nullable()->after('kicked_by_user_id');
        });
    }

    /**
     * Reverse the migrations. Kicked members become 'declined' so they are not
     * silently re-admitted as active members.
     */
    public function down(): void
    {
        DB::table('room_user')->where('status', 'kicked')->update(['status' => 'declined']);

        Schema::table('room_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kicked_by_user_id');
            $table->dropColumn('kicked_at');
        });
    }
};
