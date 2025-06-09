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
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_order_number', 50)->unique()->index();

            // Order and Location References
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');

            // Delivery Information
            $table->date('delivery_date');
            $table->text('delivery_address');
            $table->string('delivery_contact', 100)->nullable();
            $table->string('delivery_phone', 20)->nullable();

            // Delivery Status and Management
            $table->enum('delivery_status', [
                'pending',
                'packed',
                'in_transit',
                'delivered',
                'failed',
                'cancelled'
            ])->default('pending');

            // Driver and Vehicle Information
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('vehicle_id', 50)->nullable();
            $table->text('delivery_notes')->nullable();

            // Timing Information
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivery_confirmed_by')->nullable()->constrained('users')->onDelete('set null');

            // GPS and Location Tracking
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->text('delivery_signature')->nullable(); // For future signature capture

            // Additional Information
            $table->text('special_instructions')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // Indexes for Performance
            $table->index(['sales_order_id', 'delivery_status']);
            $table->index(['warehouse_id', 'delivery_date']);
            $table->index(['driver_id', 'delivery_status']);
            $table->index(['delivery_date', 'delivery_status']);
            $table->index('delivery_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
