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
        Schema::create('customer_loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('sale_id')->nullable()->constrained()->onDelete('set null'); // If earned from sale
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Who processed the transaction
            $table->enum('type', ['earned', 'redeemed', 'expired', 'adjusted', 'bonus', 'penalty'])->default('earned');
            $table->integer('points'); // Can be positive or negative
            $table->decimal('transaction_amount', 15, 2)->nullable(); // Sale amount that generated points
            $table->decimal('points_rate', 8, 4)->nullable(); // Points per IDR (e.g., 0.01 = 1 point per 100 IDR)
            $table->string('description');
            $table->date('expiry_date')->nullable(); // When points expire
            $table->boolean('is_expired')->default(false);
            $table->json('metadata')->nullable(); // Additional data (promotion details, etc.)
            $table->timestamps();

            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['customer_id', 'expiry_date']);
            $table->index(['sale_id']);
            $table->index(['is_expired', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_points');
    }
};
