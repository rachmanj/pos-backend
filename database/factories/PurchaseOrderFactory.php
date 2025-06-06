<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'po_number' => 'PO' . now()->format('Ymd') . str_pad(fake()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'supplier_id' => Supplier::factory(),
            'created_by' => User::factory(),
            'order_date' => fake()->dateTimeBetween('-7 days', 'now'),
            'expected_delivery_date' => fake()->dateTimeBetween('now', '+30 days'),
            'status' => fake()->randomElement(['draft', 'pending_approval', 'approved', 'sent_to_supplier']),
            'notes' => fake()->optional()->sentence(),
            'subtotal' => fake()->numberBetween(100000, 5000000), // 100k - 5M IDR
            'tax_amount' => 0, // Will be calculated automatically
            'total_amount' => 0, // Will be calculated automatically
        ];
    }

    /**
     * Indicate that the purchase order is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the purchase order is pending approval.
     */
    public function pendingApproval(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'pending_approval',
        ]);
    }

    /**
     * Indicate that the purchase order is approved.
     */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (PurchaseOrder $purchaseOrder) {
            // Calculate tax and total after subtotal is set
            $taxRate = 11; // Standard PPN in Indonesia
            $purchaseOrder->tax_amount = $purchaseOrder->subtotal * ($taxRate / 100);
            $purchaseOrder->total_amount = $purchaseOrder->subtotal + $purchaseOrder->tax_amount;
            $purchaseOrder->save();
        });
    }
}
