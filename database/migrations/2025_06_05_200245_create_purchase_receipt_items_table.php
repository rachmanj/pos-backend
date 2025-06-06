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
        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_order_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');

            $table->decimal('quantity_received', 10, 3);
            $table->decimal('quantity_accepted', 10, 3)->default(0);
            $table->decimal('quantity_rejected', 10, 3)->default(0);

            $table->enum('quality_status', [
                'pending',
                'passed',
                'failed',
                'partial'
            ])->default('pending');

            $table->text('quality_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['purchase_receipt_id', 'product_id']);
            $table->index('purchase_order_item_id');
            $table->index('quality_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
    }
};
