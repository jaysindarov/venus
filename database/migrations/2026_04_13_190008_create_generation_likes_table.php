<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_likes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('generation_id');
            // No updated_at — likes are created or deleted, never updated
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            $table->foreign('generation_id')
                  ->references('id')->on('generations')
                  ->cascadeOnDelete();

            $table->unique(['user_id', 'generation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_likes');
    }
};
