<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $costPrice = $this->faker->randomFloat(2, 1, 500);
        $markup = $this->faker->randomFloat(2, 1.2, 3.0);

        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'sku' => strtoupper($this->faker->unique()->bothify('??##-###')),
            'barcode' => $this->faker->optional()->ean13(),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'cost_price' => $costPrice,
            'selling_price' => round($costPrice * $markup, 2),
            'min_stock_level' => $this->faker->numberBetween(1, 20),
            'max_stock_level' => $this->faker->optional()->numberBetween(50, 200),
            'tax_rate' => $this->faker->randomElement([0, 5, 8, 10, 15]),
            'image' => $this->faker->optional()->imageUrl(400, 400, 'products'),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }

    /**
     * Product with active status
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Product with inactive status
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Product with specific category
     */
    public function inCategory($categoryId): static
    {
        return $this->state(fn(array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Product with barcode
     */
    public function withBarcode(): static
    {
        return $this->state(fn(array $attributes) => [
            'barcode' => $this->faker->ean13(),
        ]);
    }

    /**
     * Product without barcode
     */
    public function withoutBarcode(): static
    {
        return $this->state(fn(array $attributes) => [
            'barcode' => null,
        ]);
    }

    /**
     * Product with low stock settings
     */
    public function lowStockSettings(): static
    {
        return $this->state(fn(array $attributes) => [
            'min_stock_level' => 10,
            'max_stock_level' => 50,
        ]);
    }
}
