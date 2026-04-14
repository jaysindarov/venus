<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('generation_id')->nullable();
            $table->enum('type', ['grant', 'reserve', 'confirm', 'refund', 'topup', 'manual_adjust']);
            $table->integer('amount');                    // positive = add, negative = deduct
            $table->unsignedInteger('balance_after');     // running total snapshot
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();         // {"plan":"pro","period":"2024-01"}
            // No updated_at — ledger is append-only, never modified
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            $table->foreign('generation_id')
                  ->references('id')->on('generations')
                  ->nullOnDelete();

            // Index: credit balance calculation (SUM query)
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledgers');
    }
};
