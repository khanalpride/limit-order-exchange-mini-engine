<?php

use App\Actions\PlaceOrder;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

test('user can cancel their own buy order', function () {
    $this->freezeTime();

    $user = User::factory()->create([
        'balance' => 1000,
    ]);
    $placeOrderAction = new PlaceOrder();
    $placeOrderAction->execute($user->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'amount' => 0.1,
        'price' => 500,
        'status' => OrderStatus::OPEN,
    ]));

    $this->travel(1)->minutes();

    Sanctum::actingAs($user);

    $order = $user->orders()->first();

    $this->post(route('api.orders.destroy', $order->id))
        ->assertOk();

    $this->assertDatabaseHas(Order::class, [
        'id' => $order->id,
        'status' => OrderStatus::CANCELLED,
    ]);
    $this->assertDatabaseCount(Order::class, 1);
});

test('user can cancel their own sell order', function () {
    $this->freezeTime();

    $user = User::factory()->create([
        'balance' => 1000,
    ]);
    $user->assets()->create([
        'symbol' => 'BTC',
        'amount' => 0.5,
    ]);
    $placeOrderAction = new PlaceOrder();
    $placeOrderAction->execute($user->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'amount' => 0.1,
        'price' => 500,
        'status' => OrderStatus::OPEN,
    ]));

    $this->travel(1)->minutes();

    Sanctum::actingAs($user);

    $order = $user->orders()->first();

    $this->post(route('api.orders.destroy', $order->id))
        ->assertOk();

    $this->assertDatabaseHas(Order::class, [
        'id' => $order->id,
        'status' => OrderStatus::CANCELLED,
    ]);
    $this->assertDatabaseCount(Order::class, 1);
});

test('user cannot cancel others order', function () {
    $this->freezeTime();

    $user = User::factory()->create([
        'balance' => 1000,
    ]);

    $otherUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $otherUser->assets()->create([
        'symbol' => 'BTC',
        'amount' => 0.5,
    ]);
    $placeOrderAction = new PlaceOrder();
    $placeOrderAction->execute($otherUser->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'amount' => 0.1,
        'price' => 500,
        'status' => OrderStatus::OPEN,
    ]));

    $this->travel(1)->minutes();

    Sanctum::actingAs($user);

    $order = $otherUser->orders()->first();

    $this->post(route('api.orders.destroy', $order->id))
        ->assertStatus(Response::HTTP_FORBIDDEN);

    $this->assertDatabaseHas(Order::class, [
        'id' => $order->id,
        'status' => OrderStatus::OPEN,
    ]);
    $this->assertDatabaseCount(Order::class, 1);
});
