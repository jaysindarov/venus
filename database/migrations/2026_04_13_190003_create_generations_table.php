<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();              // public-facing ID
            $table->unsignedBigInteger('user_id');
            $table->string('model', 100);                // 'dall-e-3', 'sdxl', 'flux-dev'
            $table->string('provider', 50);              // 'openai', 'replicate', 'fal'
            $table->text('prompt');
            $table->text('negative_prompt')->nullable();
            $table->json('params');                      // {width, height, steps, cfg_scale, seed, style_preset}
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->unsignedTinyInteger('credits_cost')->default(2);
            $table->string('provider_job_id')->nullable(); // External job ID for polling
            $table->text('error_message')->nullable();
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            // Index: user gallery listing
            $table->index(['user_id', 'status', 'created_at']);

            // Index: public explore feed
            $table->index(['is_public', 'status', 'created_at']);

            // Index: trending feed (likes)
            $table->index(['is_public', 'likes_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
