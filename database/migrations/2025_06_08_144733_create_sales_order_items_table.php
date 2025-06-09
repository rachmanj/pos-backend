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
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();

            // Order and Product References
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            // Quantity Information
            $table->decimal('quantity_ordered', 10, 3);
            $table->decimal('quantity_delivered', 10, 3)->default(0);
            $table->decimal('quantity_remaining', 10, 3)->default(0);

            // Pricing Information
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(11.00); // Indonesian PPN 11%
            $table->decimal('line_total', 15, 2);

            // Delivery Status
            $table->enum('delivery_status', [
                'pending',
                'partial',
                'complete'
            ])->default('pending');

            // Additional Information
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for Performance
            $table->index(['sales_order_id', 'product_id']);
            $table->index(['product_id', 'delivery_status']);
            $table->index('delivery_status');

            // Unique constraint to prevent duplicate products in same order
            $table->unique(['sales_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
