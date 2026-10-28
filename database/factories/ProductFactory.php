<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('???-').fake()->numerify('###')),
            'price' => fake()->randomFloat(2, 10, 500),
            'tax_percentage' => fake()->randomElement([5.00, 12.00, 18.00]),
            'stock_on_hand' => fake()->numberBetween(5, 100),
        ];
    }

    /**
     * Indicate that the product has low stock.
     */
    public function lowStock(int $stock = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_on_hand' => $stock,
        ]);
    }
}
