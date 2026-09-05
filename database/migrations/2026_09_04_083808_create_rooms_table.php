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
        Schema::create('rooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique()->index();
            $table->string('title')->nullable();
            $table->string('passcode_hash');
            $table->boolean('burn_after_reading')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->string('created_by_ip')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

