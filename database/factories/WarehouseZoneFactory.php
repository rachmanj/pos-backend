<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WarehouseZone>
 */
class WarehouseZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $zoneTypes = ['general', 'cold_storage', 'hazardous', 'high_value', 'bulk'];

        return [
            'warehouse_id' => Warehouse::factory(),
            'name' => 'Zone ' . fake()->randomLetter() . '-' . fake()->numberBetween(1, 99),
            'code' => strtoupper(fake()->lexify('?-??')),
            'zone_type' => fake()->randomElement($zoneTypes),
            'capacity' => fake()->numberBetween(100, 5000),
            'current_utilization' => fake()->numberBetween(0, 80),
            'temperature_min' => fake()->numberBetween(-20, 10),
            'temperature_max' => fake()->numberBetween(15, 40),
            'humidity_min' => fake()->numberBetween(30, 60),
            'humidity_max' => fake()->numberBetween(70, 90),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the zone is for cold storage.
     */
    public function coldStorage(): static
    {
        return $this->state(fn(array $attributes) => [
            'zone_type' => 'cold_storage',
            'temperature_min' => -5,
            'temperature_max' => 5,
            'humidity_min' => 80,
            'humidity_max' => 90,
        ]);
    }

    /**
     * Indicate that the zone is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
