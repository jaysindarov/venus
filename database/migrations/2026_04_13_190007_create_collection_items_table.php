<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('collection_id');
            $table->unsignedBigInteger('generation_id');
            $table->timestamp('added_at')->useCurrent();

            $table->foreign('collection_id')
                  ->references('id')->on('collections')
                  ->cascadeOnDelete();

            $table->foreign('generation_id')
                  ->references('id')->on('generations')
                  ->cascadeOnDelete();

            $table->unique(['collection_id', 'generation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_items');
    }
};
