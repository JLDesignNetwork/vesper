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
        // 1. Hardware Biometrics & Passkeys Table
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 255)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('counter')->default(0);
            $table->string('device_name', 100)->default('Biometric Key / Touch ID');
            $table->json('transports')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // 2. Social / OAuth Accounts Table
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50); // google, apple, etc.
            $table->string('provider_id', 255);
            $table->string('email', 255)->nullable();
            $table->text('avatar')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'provider']);
        });

        // 3. User Table Security Columns (TOTP 2FA, Recovery Email, Emergency Codes)
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
            $table->string('recovery_email', 255)->nullable()->after('email');
            $table->timestamp('recovery_email_verified_at')->nullable()->after('recovery_email');
            $table->string('recovery_token', 100)->nullable()->after('remember_token');
            $table->timestamp('recovery_token_expires_at')->nullable()->after('recovery_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
                'recovery_email',
                'recovery_email_verified_at',
                'recovery_token',
                'recovery_token_expires_at',
            ]);
        });

        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('webauthn_credentials');
    }
};
