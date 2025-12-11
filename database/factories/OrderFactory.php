<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => strtoupper($this->faker->bothify('?????')),
            'side' => $this->faker->randomElement(['buy', 'sell']),
            'price' => $this->faker->randomFloat(2, 10, 50),
            'amount' => $this->faker->randomFloat(2, 0.01, 10),
            'status' => $this->faker->randomElement(OrderStatus::cases()),
        ];
    }
}
