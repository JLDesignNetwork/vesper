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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60);
            $table->string('locale', 10)->default('en');
            $table->string('name', 150);
            $table->string('category', 50)->default('General');
            $table->string('subject', 255);
            $table->string('preheader', 255)->nullable();
            $table->text('body_markdown');
            $table->string('button_text', 100)->nullable();
            $table->string('button_color', 30)->default('success');
            $table->text('footer_text')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'locale']);
            $table->index(['key', 'locale']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
