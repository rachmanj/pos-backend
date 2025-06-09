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
        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();

            // Invoice and Product References
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('sales_order_item_id')->nullable()->constrained('sales_order_items')->onDelete('set null');
            $table->foreignId('delivery_order_item_id')->nullable()->constrained('delivery_order_items')->onDelete('set null');

            // Quantity and Pricing
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(11.00); // Indonesian PPN 11%
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);

            // Product Information (snapshot at time of invoice)
            $table->string('product_name', 255);
            $table->string('product_sku', 100)->nullable();
            $table->text('description')->nullable();

            // Additional Information
            $table->text('item_notes')->nullable();

            $table->timestamps();

            // Indexes for Performance
            $table->index(['sales_invoice_id', 'product_id']);
            $table->index(['product_id', 'sales_invoice_id']);
            $table->index('sales_order_item_id');
            $table->index('delivery_order_item_id');

            // Unique constraint to prevent duplicate products in same invoice
            $table->unique(['sales_invoice_id', 'product_id', 'sales_order_item_id'], 'unique_invoice_product_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_items');
    }
};
