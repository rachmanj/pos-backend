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
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('warehouse_zone_id')->nullable()->constrained('warehouse_zones')->onDelete('set null');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');

            // Stock Information
            $table->decimal('quantity', 15, 4)->default(0); // Current stock quantity
            $table->decimal('reserved_quantity', 15, 4)->default(0); // Reserved for orders/transfers
            $table->decimal('available_quantity', 15, 4)->default(0); // Available for sale/transfer
            $table->decimal('minimum_stock', 15, 4)->default(0); // Minimum stock level
            $table->decimal('maximum_stock', 15, 4)->nullable(); // Maximum stock level
            $table->decimal('reorder_point', 15, 4)->default(0); // Reorder point
            $table->decimal('reorder_quantity', 15, 4)->default(0); // Reorder quantity

            // Cost Information
            $table->decimal('average_cost', 15, 4)->default(0); // Average cost per unit
            $table->decimal('last_cost', 15, 4)->default(0); // Last purchase cost per unit
            $table->decimal('total_value', 15, 4)->default(0); // Total stock value

            // Location Information
            $table->string('bin_location')->nullable(); // Specific bin/shelf location
            $table->string('lot_number')->nullable(); // Lot/batch number
            $table->date('expiry_date')->nullable(); // Expiry date for perishable items
            $table->date('manufacture_date')->nullable(); // Manufacture date

            // Stock Status
            $table->enum('status', ['available', 'reserved', 'damaged', 'expired', 'quarantine'])->default('available');
            $table->boolean('is_active')->default(true); // Is stock record active
            $table->text('notes')->nullable(); // Additional notes

            // Tracking Information
            $table->timestamp('last_movement_at')->nullable(); // Last stock movement timestamp
            $table->foreignId('last_movement_by')->nullable()->constrained('users')->onDelete('set null'); // Last movement user
            $table->decimal('last_movement_quantity', 15, 4)->default(0); // Last movement quantity
            $table->enum('last_movement_type', ['in', 'out', 'adjustment', 'transfer'])->nullable(); // Last movement type

            $table->timestamps();

            // Indexes
            $table->unique(['warehouse_id', 'product_id', 'unit_id', 'lot_number'], 'warehouse_stock_unique'); // Unique stock per warehouse/product/unit/lot
            $table->index(['warehouse_id', 'product_id']);
            $table->index(['warehouse_zone_id', 'product_id']);
            $table->index(['product_id', 'status']);
            $table->index(['warehouse_id', 'status']);
            $table->index('expiry_date');
            $table->index('last_movement_at');
            $table->index(['quantity', 'minimum_stock']); // For low stock alerts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
