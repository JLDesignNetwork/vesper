<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the single rooms.pinned_message_id with up to 10 pins per channel.
     * Any existing pin is carried over.
     */
    public function up(): void
    {
        Schema::create('message_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('pinned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['room_id', 'message_id']);
        });

        DB::table('rooms')->whereNotNull('pinned_message_id')->orderBy('id')->each(function (object $room): void {
            DB::table('message_pins')->insertOrIgnore([
                'room_id' => $room->id,
                'message_id' => $room->pinned_message_id,
                'pinned_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_message_id');
        });
    }

    /**
     * Restore the single pin column, keeping each channel's most recent pin.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('pinned_message_id')->nullable()->after('status')->constrained('messages')->nullOnDelete();
        });

        DB::table('message_pins')->orderBy('id')->each(function (object $pin): void {
            DB::table('rooms')->where('id', $pin->room_id)->update(['pinned_message_id' => $pin->message_id]);
        });

        Schema::dropIfExists('message_pins');
    }
};
