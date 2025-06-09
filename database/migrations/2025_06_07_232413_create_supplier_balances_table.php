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
        Schema::create('supplier_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('total_outstanding', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->date('last_payment_date')->nullable();
            $table->decimal('advance_balance', 15, 2)->default(0); // Prepaid amounts
            $table->enum('payment_status', ['current', 'overdue', 'blocked'])->default('current');
            $table->timestamps();

            // Indexes for performance
            $table->index(['supplier_id', 'payment_status']);
            $table->index('total_outstanding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_balances');
    }
};
