<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ban cases voted on by Admin I and II, their ballots, and the banned flag on users.
     */
    public function up(): void
    {
        Schema::create('ban_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('open');
            $table->string('decision', 30)->nullable();
            $table->timestamp('closes_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'closes_at']);
            $table->index(['subject_user_id', 'status']);
        });

        Schema::create('ban_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ban_case_id')->constrained('ban_cases')->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('ban');
            $table->timestamps();

            $table->unique(['ban_case_id', 'voter_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('banned_at')->nullable()->after('rank_computed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('banned_at');
        });

        Schema::dropIfExists('ban_votes');
        Schema::dropIfExists('ban_cases');
    }
};
