<?php

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;

test('sends status ok on order route', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->get(route('api.orders'))
        ->assertStatus(200)
        ->assertJsonCount(0, 'orders');
    // ->assertJson(fn (AssertableJson $json) =>
    //     $json->etc()
    // );
});

test('receive user open orders in correct order', function () {
    $user = User::factory()->create([
        'balance' => 15000,
    ]);

    $user->orders()->createMany([
        ['symbol' => 'BTC', 'side' => OrderSide::BUY, 'status' => OrderStatus::OPEN, 'amount' => 0.001, 'price' => 200000, 'updated_at' => now()->subMinutes(2)],
        ['symbol' => 'ETH', 'side' => OrderSide::BUY, 'status' => OrderStatus::OPEN, 'amount' => 0.05, 'price' => 2000, 'updated_at' => now()->subMinutes(1)],
        ['symbol' => 'ETH', 'side' => OrderSide::SELL, 'status' => OrderStatus::FILLED, 'amount' => 0.05, 'price' => 2000],
        ['symbol' => 'ETH', 'side' => OrderSide::SELL, 'status' => OrderStatus::CANCELLED, 'amount' => 0.05, 'price' => 2000],
    ]);

    Sanctum::actingAs($user);

    $this->get(route('api.orders'))
        ->assertJsonCount(2, 'orders')
        ->assertJsonPath('orders.0.symbol', 'ETH')
        ->assertJsonPath('orders.1.symbol', 'BTC');
});

test('receive only open orders by default on filtering by symbol', function () {
    $user = User::factory()->create([
        'balance' => 15000,
    ]);

    $user->orders()->createMany([
        ['symbol' => 'BTC', 'side' => OrderSide::BUY, 'status' => OrderStatus::OPEN, 'amount' => 0.001, 'price' => 200000, 'updated_at' => now()->subMinutes(2)],
        ['symbol' => 'ETH', 'side' => OrderSide::BUY, 'status' => OrderStatus::OPEN, 'amount' => 0.05, 'price' => 2000, 'updated_at' => now()->subMinutes(1)],
        ['symbol' => 'ETH', 'side' => OrderSide::SELL, 'status' => OrderStatus::FILLED, 'amount' => 0.05, 'price' => 2000],
        ['symbol' => 'ETH', 'side' => OrderSide::SELL, 'status' => OrderStatus::CANCELLED, 'amount' => 0.05, 'price' => 2000],
    ]);

    Sanctum::actingAs($user);

    $this->get(route('api.orders', ['symbol' => 'BTC']))
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.symbol', 'BTC');
});

test('receive only open buy orders by default on filtering by symbol and buy side', function () {
    $user = User::factory()->create([
        'balance' => 15000,
    ]);

    $user->orders()->createMany([
        ['symbol' => 'BTC', 'side' => OrderSide::BUY, 'status' => OrderStatus::OPEN, 'amount' => 0.001, 'price' => 200000, 'updated_at' => now()->subMinutes(2)],
        ['symbol' => 'BTC', 'side' => OrderSide::SELL, 'status' => OrderStatus::OPEN, 'amount' => 0.002, 'price' => 210000, 'updated_at' => now()->subMinutes(2)],
        ['symbol' => 'ETH', 'side' => OrderSide::BUY, 'status' => OrderStatus::OPEN, 'amount' => 0.05, 'price' => 2000, 'updated_at' => now()->subMinutes(1)],
        ['symbol' => 'ETH', 'side' => OrderSide::SELL, 'status' => OrderStatus::FILLED, 'amount' => 0.05, 'price' => 2000],
        ['symbol' => 'ETH', 'side' => OrderSide::SELL, 'status' => OrderStatus::CANCELLED, 'amount' => 0.05, 'price' => 2000],
    ]);

    Sanctum::actingAs($user);

    $this->get(route('api.orders', ['symbol' => 'BTC', 'side' => 'buy']))
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.symbol', 'BTC')
        ->assertJsonPath('orders.0.side', 'buy')
        ->assertJsonPath('orders.0.amount', 0.001);
});

test('receive only open sell orders by default on filtering by symbol and sell side', function () {
    $user = User::factory()->create([
        'balance' => 15000,
    ]);

    $user->orders()->createMany([
        ['symbol' => 'BTC', 'side' => OrderSide::BUY, 'status' => OrderStatus::OPEN, 'amount' => 0.001, 'price' => 200000, 'updated_at' => now()->subMinutes(2)],
        ['symbol' => 'BTC', 'side' => OrderSide::SELL, 'status' => OrderStatus::OPEN, 'amount' => 0.002, 'price' => 210000, 'updated_at' => now()->subMinutes(2)],
        ['symbol' => 'ETH', 'side' => OrderSide::BUY, 'status' => OrderStatus::OPEN, 'amount' => 0.05, 'price' => 2000, 'updated_at' => now()->subMinutes(1)],
        ['symbol' => 'ETH', 'side' => OrderSide::SELL, 'status' => OrderStatus::FILLED, 'amount' => 0.05, 'price' => 2000],
        ['symbol' => 'ETH', 'side' => OrderSide::SELL, 'status' => OrderStatus::CANCELLED, 'amount' => 0.05, 'price' => 2000],
    ]);

    Sanctum::actingAs($user);

    $this->get(route('api.orders', ['symbol' => 'BTC', 'side' => 'sell']))
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.symbol', 'BTC')
        ->assertJsonPath('orders.0.side', 'sell')
        ->assertJsonPath('orders.0.amount', 0.002);
});
