<?php

namespace Database\Factories;

use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseReceipt>
 */
class PurchaseReceiptFactory extends Factory
{
    protected $model = PurchaseReceipt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => 'GR' . now()->format('Ymd') . str_pad(fake()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'purchase_order_id' => PurchaseOrder::factory(),
            'received_by' => User::factory(),
            'status' => 'draft',
            'receipt_date' => fake()->dateTimeBetween('-7 days', 'now'),
            'stock_updated' => false,
            'notes' => fake()->optional()->sentence(),
            'quality_check_notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the receipt is approved.
     */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'approved',
            'stock_updated' => true,
            'quality_check_notes' => 'Quality check passed',
        ]);
    }

    /**
     * Indicate that the receipt is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'draft',
            'stock_updated' => false,
        ]);
    }
}
