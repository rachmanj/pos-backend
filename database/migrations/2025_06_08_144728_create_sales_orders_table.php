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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('sales_order_number', 50)->unique()->index();

            // Customer and Location Information
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');

            // Date Information
            $table->date('order_date');
            $table->date('requested_delivery_date');
            $table->date('confirmed_delivery_date')->nullable();

            // Financial Information
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Order Status and Workflow
            $table->enum('order_status', [
                'draft',
                'confirmed',
                'approved',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('draft');

            // Payment and Credit Information
            $table->integer('payment_terms_days')->default(30);
            $table->foreignId('credit_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('credit_approval_date')->nullable();

            // Sales Representative and Management
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->text('special_instructions')->nullable();

            // Audit Trail
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // Indexes for Performance
            $table->index(['customer_id', 'order_status']);
            $table->index(['warehouse_id', 'order_date']);
            $table->index(['sales_rep_id', 'order_status']);
            $table->index(['order_date', 'order_status']);
            $table->index('requested_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
