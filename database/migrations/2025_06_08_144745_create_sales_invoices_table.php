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
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique()->index();

            // Order and Customer References
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->foreignId('delivery_order_id')->nullable()->constrained('delivery_orders')->onDelete('set null');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');

            // Invoice Dates
            $table->date('invoice_date');
            $table->date('due_date');
            $table->integer('payment_terms_days')->default(30);

            // Financial Information
            $table->decimal('subtotal_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('outstanding_amount', 15, 2)->default(0);

            // Invoice Status
            $table->enum('invoice_status', [
                'draft',
                'sent',
                'viewed',
                'paid',
                'partial_paid',
                'overdue',
                'cancelled'
            ])->default('draft');

            // Payment Status
            $table->enum('payment_status', [
                'unpaid',
                'partial',
                'paid',
                'overpaid'
            ])->default('unpaid');

            // Invoice Management
            $table->text('invoice_notes')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->string('invoice_file_path')->nullable(); // For PDF storage

            // Audit Trail
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('sent_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Additional Information
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Indexes for Performance
            $table->index(['customer_id', 'invoice_status']);
            $table->index(['sales_order_id', 'payment_status']);
            $table->index(['due_date', 'payment_status']);
            $table->index(['invoice_date', 'invoice_status']);
            $table->index('payment_status');
            $table->index('invoice_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
