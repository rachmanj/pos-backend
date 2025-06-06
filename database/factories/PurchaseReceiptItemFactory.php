<?php

namespace Database\Factories;

use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseReceiptItem>
 */
class PurchaseReceiptItemFactory extends Factory
{
    protected $model = PurchaseReceiptItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantityReceived = fake()->numberBetween(1, 50);
        $quantityAccepted = fake()->numberBetween(0, $quantityReceived);
        $quantityRejected = $quantityReceived - $quantityAccepted;

        return [
            'purchase_receipt_id' => PurchaseReceipt::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'quantity_received' => $quantityReceived,
            'quantity_accepted' => $quantityAccepted,
            'quantity_rejected' => $quantityRejected,
            'quality_notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the item passed quality check.
     */
    public function passed(): static
    {
        return $this->state(function (array $attributes) {
            $quantityReceived = $attributes['quantity_received'];
            return [
                'quantity_accepted' => $quantityReceived,
                'quantity_rejected' => 0,
            ];
        });
    }

    /**
     * Indicate that the item failed quality check.
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $quantityReceived = $attributes['quantity_received'];
            return [
                'quantity_accepted' => 0,
                'quantity_rejected' => $quantityReceived,
            ];
        });
    }
}
