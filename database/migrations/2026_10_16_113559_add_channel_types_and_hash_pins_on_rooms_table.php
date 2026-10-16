<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add channel types, stop storing PINs in plaintext, and seed the two system
     * admin channels. Existing rooms were code-and-PIN and hidden, so they become
     * private secured (prs).
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('type', 10)->default('prs')->after('title')->index();
            $table->text('description')->nullable()->after('type');
            $table->boolean('is_system')->default(false)->after('status');
            $table->string('passcode_hash')->nullable()->change();
        });

        DB::table('rooms')->whereNotNull('pin')->where('pin', '!=', '')->orderBy('id')->each(function (object $room): void {
            DB::table('rooms')->where('id', $room->id)->update(['passcode_hash' => Hash::make($room->pin)]);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('pin');
        });

        Schema::table('room_user', function (Blueprint $table) {
            $table->boolean('biometric_entry')->default(true)->after('status');
        });

        $ownerId = DB::table('users')->where('rank', 'admin_one')->orderBy('id')->value('id');

        foreach ([['PRA', 'pra', 'Admin Command'], ['PRA-2', 'pra-2', 'Admin Council']] as [$code, $type, $title]) {
            DB::table('rooms')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'code' => $code,
                'title' => $title,
                'type' => $type,
                'passcode_hash' => Hash::make(Str::random(40)),
                'status' => 'active',
                'is_system' => true,
                'created_by_user_id' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Restore the pin column. Plaintext PINs cannot be recovered from their hashes,
     * so existing rooms keep working only through passcode_hash.
     */
    public function down(): void
    {
        DB::table('rooms')->where('is_system', true)->delete();

        Schema::table('room_user', function (Blueprint $table) {
            $table->dropColumn('biometric_entry');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('pin')->nullable()->after('passcode_hash');
        });

        DB::table('rooms')->whereNull('passcode_hash')->update(['passcode_hash' => Hash::make(Str::random(40))]);

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'description', 'is_system']);
            $table->string('passcode_hash')->nullable(false)->change();
        });
    }
};
