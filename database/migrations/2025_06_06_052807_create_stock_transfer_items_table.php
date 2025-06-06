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
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');

            // Source Location
            $table->foreignId('from_warehouse_stock_id')->nullable()->constrained('warehouse_stocks')->onDelete('set null');
            $table->foreignId('from_zone_id')->nullable()->constrained('warehouse_zones')->onDelete('set null');
            $table->string('from_bin_location')->nullable(); // Source bin location

            // Destination Location
            $table->foreignId('to_zone_id')->nullable()->constrained('warehouse_zones')->onDelete('set null');
            $table->string('to_bin_location')->nullable(); // Destination bin location

            // Quantity Information
            $table->decimal('requested_quantity', 15, 4); // Requested quantity
            $table->decimal('shipped_quantity', 15, 4)->default(0); // Actually shipped quantity
            $table->decimal('received_quantity', 15, 4)->default(0); // Actually received quantity
            $table->decimal('damaged_quantity', 15, 4)->default(0); // Damaged during transfer
            $table->decimal('variance_quantity', 15, 4)->default(0); // Variance (received - shipped)

            // Cost Information
            $table->decimal('unit_cost', 15, 4)->default(0); // Unit cost at time of transfer
            $table->decimal('total_cost', 15, 4)->default(0); // Total cost for this line item

            // Lot/Batch Information
            $table->string('lot_number')->nullable(); // Lot/batch number
            $table->date('expiry_date')->nullable(); // Expiry date
            $table->date('manufacture_date')->nullable(); // Manufacture date

            // Status and Quality
            $table->enum('status', ['pending', 'shipped', 'in_transit', 'received', 'damaged', 'cancelled'])->default('pending');
            $table->enum('quality_status', ['good', 'damaged', 'expired', 'quarantine'])->default('good');
            $table->text('quality_notes')->nullable(); // Quality inspection notes

            // Tracking Information
            $table->timestamp('shipped_at')->nullable(); // When item was shipped
            $table->timestamp('received_at')->nullable(); // When item was received
            $table->foreignId('shipped_by')->nullable()->constrained('users')->onDelete('set null'); // User who shipped
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null'); // User who received

            // Additional Information
            $table->text('notes')->nullable(); // Additional notes
            $table->json('custom_fields')->nullable(); // Custom fields (JSON object)
            $table->integer('line_number')->default(0); // Line number in transfer

            $table->timestamps();

            // Indexes
            $table->index(['stock_transfer_id', 'product_id']);
            $table->index(['product_id', 'status']);
            $table->index(['from_warehouse_stock_id']);
            $table->index(['lot_number', 'expiry_date']);
            $table->index(['status', 'quality_status']);
            $table->index('line_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
