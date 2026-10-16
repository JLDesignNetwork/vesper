<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Give every member a unique @tag generated from their name.
     * The generation is inlined so this migration does not change if the
     * application's tag rules change later.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tag', 30)->nullable()->unique()->after('name');
        });

        $reserved = ['admin', 'administrator', 'admins', 'all', 'channel', 'everyone', 'here',
            'mod', 'moderator', 'owner', 'root', 'staff', 'support', 'system', 'vesper'];
        $taken = [];

        DB::table('users')->orderBy('id')->select('id', 'name')->each(function (object $user) use (&$taken, $reserved): void {
            $base = (string) Str::of(Str::ascii((string) $user->name))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');

            if ($base === '' || ! ctype_alpha($base[0])) {
                $base = 'member_'.$base;
            }

            $base = substr(rtrim(str_pad($base, 3, '0'), '_'), 0, 24);

            if (in_array($base, $reserved, true)) {
                $base .= '_1';
            }

            $candidate = $base;
            $suffix = 2;

            while (isset($taken[$candidate])) {
                $candidate = $base.'_'.$suffix++;
            }

            $taken[$candidate] = true;
            DB::table('users')->where('id', $user->id)->update(['tag' => $candidate]);
        });
    }

    /**
     * Remove user tags.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tag']);
            $table->dropColumn('tag');
        });
    }
};
