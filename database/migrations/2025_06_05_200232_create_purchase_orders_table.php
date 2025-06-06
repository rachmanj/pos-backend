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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');

            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'sent_to_supplier',
                'partially_received',
                'fully_received',
                'cancelled'
            ])->default('draft');

            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('approved_date')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();

            $table->timestamps();

            $table->index(['status', 'order_date']);
            $table->index(['supplier_id', 'status']);
            $table->index('po_number');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
