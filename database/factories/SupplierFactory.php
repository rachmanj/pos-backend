<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'tax_number' => $this->faker->optional()->regexify('TAX[0-9]{9}'),
            'payment_terms' => $this->faker->randomElement([15, 30, 45, 60]),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }

    /**
     * Supplier with active status
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Supplier with inactive status
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Supplier without email
     */
    public function withoutEmail(): static
    {
        return $this->state(fn(array $attributes) => [
            'email' => null,
        ]);
    }

    /**
     * Supplier with specific payment terms
     */
    public function withPaymentTerms(int $days): static
    {
        return $this->state(fn(array $attributes) => [
            'payment_terms' => $days,
        ]);
    }

    /**
     * Technology supplier
     */
    public function technology(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => $this->faker->company() . ' Tech',
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
        ]);
    }
}
