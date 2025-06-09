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
        Schema::create('purchase_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->decimal('allocated_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['purchase_payment_id', 'purchase_order_id'], 'pp_alloc_payment_order_idx');
            $table->index('purchase_order_id', 'pp_alloc_order_idx');

            // Ensure unique allocation per payment-order combination
            $table->unique(['purchase_payment_id', 'purchase_order_id'], 'pp_alloc_unique_payment_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_payment_allocations');
    }
};
