<?php

namespace Database\Seeders;

use App\Actions\PlaceOrder;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->assets()->createMany([
            ['symbol' => 'BTC', 'amount' => 0.55],
            ['symbol' => 'ETH', 'amount' => 10],
        ]);

        $placeOrderAction = new PlaceOrder();
        $placeOrderAction->execute($user->orders()->make([
            'symbol' => 'BTC',
            'side' => OrderSide::SELL,
            'amount' => 0.1,
            'price' => 550,
            'status' => OrderStatus::OPEN,
        ]));
        $placeOrderAction->execute($user->orders()->make([
            'symbol' => 'ETH',
            'side' => OrderSide::BUY,
            'amount' => 0.5,
            'price' => 550,
            'status' => OrderStatus::OPEN,
        ]));
    }
}
