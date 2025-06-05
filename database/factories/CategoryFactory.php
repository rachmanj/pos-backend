<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'parent_id' => null,
            'image' => $this->faker->optional()->imageUrl(400, 300, 'categories'),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }

    /**
     * Category with active status
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Category with inactive status
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Category with parent
     */
    public function withParent($parentId = null): static
    {
        return $this->state(fn(array $attributes) => [
            'parent_id' => $parentId ?? \App\Models\Category::factory()->create()->id,
        ]);
    }
}
