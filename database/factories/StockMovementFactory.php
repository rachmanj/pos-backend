<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $movementType = $this->faker->randomElement(['in', 'out', 'adjustment']);
        $quantity = $this->faker->numberBetween(1, 100);

        return [
            'product_id' => Product::factory(),
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'unit_cost' => $this->faker->optional()->randomFloat(2, 1, 100),
            'reference_type' => $this->faker->optional()->randomElement(['purchase', 'sale', 'adjustment']),
            'reference_id' => $this->faker->optional()->numberBetween(1, 1000),
            'notes' => $this->faker->optional()->sentence(),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Stock in movement
     */
    public function stockIn(): static
    {
        return $this->state(fn(array $attributes) => [
            'movement_type' => 'in',
            'reference_type' => 'purchase',
        ]);
    }

    /**
     * Stock out movement
     */
    public function stockOut(): static
    {
        return $this->state(fn(array $attributes) => [
            'movement_type' => 'out',
            'reference_type' => 'sale',
        ]);
    }

    /**
     * Stock adjustment movement
     */
    public function adjustment(): static
    {
        return $this->state(fn(array $attributes) => [
            'movement_type' => 'adjustment',
            'reference_type' => 'adjustment',
        ]);
    }

    /**
     * Movement for specific product
     */
    public function forProduct($productId): static
    {
        return $this->state(fn(array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    /**
     * Movement by specific user
     */
    public function byUser($userId): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Movement with specific quantity
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(fn(array $attributes) => [
            'quantity' => $quantity,
        ]);
    }

    /**
     * Movement with unit cost
     */
    public function withCost(float $unitCost): static
    {
        return $this->state(fn(array $attributes) => [
            'unit_cost' => $unitCost,
        ]);
    }
}
