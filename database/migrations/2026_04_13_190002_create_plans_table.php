<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 50)->unique();          // 'free', 'basic', 'pro', 'creator'
            $table->string('name', 100);
            $table->unsignedInteger('monthly_credits');
            $table->string('stripe_monthly_id')->nullable(); // Stripe Price ID
            $table->string('stripe_yearly_id')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->json('features');                       // {"api_access": true, "priority_queue": true}
            $table->unsignedInteger('max_resolution')->default(1024);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
