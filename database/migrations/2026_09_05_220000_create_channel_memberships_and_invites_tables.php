<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('room_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30)->default('member'); // 'member', 'channel_admin'
            $table->string('alias', 50)->nullable();
            $table->string('status', 30)->default('active'); // 'active', 'invited', 'declined'
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('channel_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('code', 16)->unique();
            $table->integer('max_uses')->nullable()->default(1);
            $table->integer('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'code']);
        });

        // Backfill existing channel memberships from rooms created by users
        try {
            $createdRooms = DB::table('rooms')
                ->whereNotNull('created_by_user_id')
                ->select('id as room_id', 'created_by_user_id as user_id', 'created_at', 'updated_at')
                ->get();

            foreach ($createdRooms as $entry) {
                DB::table('room_user')->insertOrIgnore([
                    'room_id' => $entry->room_id,
                    'user_id' => $entry->user_id,
                    'role' => 'channel_admin',
                    'status' => 'active',
                    'last_accessed_at' => $entry->created_at,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ]);
            }

            // Backfill existing memberships from access logs
            $accessEntries = DB::table('access_logs')
                ->whereNotNull('user_id')
                ->select('room_id', 'user_id', 'alias', 'last_seen_at')
                ->distinct()
                ->get();

            foreach ($accessEntries as $entry) {
                DB::table('room_user')->insertOrIgnore([
                    'room_id' => $entry->room_id,
                    'user_id' => $entry->user_id,
                    'role' => 'member',
                    'alias' => $entry->alias,
                    'status' => 'active',
                    'last_accessed_at' => $entry->last_seen_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Ignore backfill errors in fresh environments
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_invitations');
        Schema::dropIfExists('room_user');
    }
};
