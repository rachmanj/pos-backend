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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount');
            $table->decimal('outstanding_amount', 15, 2)->default(0)->after('paid_amount');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'overpaid'])->default('unpaid')->after('outstanding_amount');
            $table->string('payment_terms')->default('Net 30')->after('payment_status');
            $table->date('due_date')->nullable()->after('payment_terms');

            // Index for payment queries
            $table->index(['payment_status', 'due_date'], 'po_payment_status_due_idx');
            $table->index('outstanding_amount', 'po_outstanding_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('po_payment_status_due_idx');
            $table->dropIndex('po_outstanding_idx');
            $table->dropColumn([
                'paid_amount',
                'outstanding_amount',
                'payment_status',
                'payment_terms',
                'due_date'
            ]);
        });
    }
};
