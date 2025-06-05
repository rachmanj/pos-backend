<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $units = [
            ['name' => 'Piece', 'symbol' => 'pcs'],
            ['name' => 'Kilogram', 'symbol' => 'kg'],
            ['name' => 'Gram', 'symbol' => 'g'],
            ['name' => 'Liter', 'symbol' => 'L'],
            ['name' => 'Milliliter', 'symbol' => 'ml'],
            ['name' => 'Meter', 'symbol' => 'm'],
            ['name' => 'Centimeter', 'symbol' => 'cm'],
            ['name' => 'Box', 'symbol' => 'box'],
            ['name' => 'Package', 'symbol' => 'pkg'],
            ['name' => 'Bottle', 'symbol' => 'btl'],
        ];

        $unit = $this->faker->randomElement($units);

        return [
            'name' => $unit['name'],
            'symbol' => $unit['symbol'],
            'base_unit_id' => null,
            'conversion_factor' => 1.000000,
        ];
    }

    /**
     * Base unit (no conversion)
     */
    public function baseUnit(): static
    {
        return $this->state(fn(array $attributes) => [
            'base_unit_id' => null,
            'conversion_factor' => 1.000000,
        ]);
    }

    /**
     * Derived unit with conversion factor
     */
    public function derivedUnit($baseUnitId, $conversionFactor): static
    {
        return $this->state(fn(array $attributes) => [
            'base_unit_id' => $baseUnitId,
            'conversion_factor' => $conversionFactor,
        ]);
    }

    /**
     * Piece unit
     */
    public function piece(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Piece',
            'symbol' => 'pcs',
        ]);
    }

    /**
     * Weight unit (kg)
     */
    public function kilogram(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ]);
    }

    /**
     * Volume unit (L)
     */
    public function liter(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Liter',
            'symbol' => 'L',
        ]);
    }
}
