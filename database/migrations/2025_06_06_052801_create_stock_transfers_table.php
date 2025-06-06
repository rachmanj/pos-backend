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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique(); // Transfer number (e.g., TRF-2025-001)
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->onDelete('cascade');

            // Transfer Information
            $table->enum('type', ['transfer', 'adjustment', 'return', 'emergency'])->default('transfer');
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'in_transit', 'partially_received', 'completed', 'cancelled'])->default('draft');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Dates
            $table->date('requested_date'); // Date transfer was requested
            $table->date('expected_date')->nullable(); // Expected completion date
            $table->date('shipped_date')->nullable(); // Date items were shipped
            $table->date('received_date')->nullable(); // Date items were received

            // User Information
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade'); // User who requested transfer
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null'); // User who approved transfer
            $table->foreignId('shipped_by')->nullable()->constrained('users')->onDelete('set null'); // User who shipped items
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null'); // User who received items

            // Approval Information
            $table->timestamp('approved_at')->nullable(); // Approval timestamp
            $table->text('approval_notes')->nullable(); // Approval notes
            $table->timestamp('shipped_at')->nullable(); // Shipping timestamp
            $table->text('shipping_notes')->nullable(); // Shipping notes
            $table->timestamp('received_at')->nullable(); // Receiving timestamp
            $table->text('receiving_notes')->nullable(); // Receiving notes

            // Transfer Details
            $table->text('reason')->nullable(); // Reason for transfer
            $table->text('description')->nullable(); // Transfer description
            $table->integer('total_items')->default(0); // Total number of different items
            $table->decimal('total_quantity', 15, 4)->default(0); // Total quantity being transferred
            $table->decimal('total_value', 15, 4)->default(0); // Total value of transfer

            // Shipping Information
            $table->string('carrier')->nullable(); // Shipping carrier
            $table->string('tracking_number')->nullable(); // Tracking number
            $table->decimal('shipping_cost', 15, 4)->default(0); // Shipping cost
            $table->text('shipping_address')->nullable(); // Shipping address

            // Status Tracking
            $table->boolean('is_urgent')->default(false); // Is urgent transfer
            $table->boolean('requires_approval')->default(true); // Requires approval
            $table->decimal('completion_percentage', 5, 2)->default(0); // Completion percentage
            $table->json('status_history')->nullable(); // Status change history (JSON array)

            $table->timestamps();

            // Indexes
            $table->index(['from_warehouse_id', 'status']);
            $table->index(['to_warehouse_id', 'status']);
            $table->index(['status', 'requested_date']);
            $table->index(['requested_by', 'status']);
            $table->index('expected_date');
            $table->index('is_urgent');
            $table->index('requires_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
