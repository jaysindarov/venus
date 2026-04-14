<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('cover_generation_id')->nullable();
            $table->unsignedInteger('items_count')->default(0); // denormalized counter
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            $table->foreign('cover_generation_id')
                  ->references('id')->on('generations')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
