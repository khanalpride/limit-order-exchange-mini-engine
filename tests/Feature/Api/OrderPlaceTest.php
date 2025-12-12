<?php

use App\Actions\PlaceOrder;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

test('can place order on available symbols only', function ($data) {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    [$symbol, $passed] = $data;

    $response = $this->post(route('api.orders.store'), [
        'symbol' => $symbol,
    ]);

    if ($passed) {
        $response->assertValid(['symbol']);
    } else {
        $response->assertInvalid(['symbol']);
    }
})->with([
    fn () => ['BTC', true],
    fn () => ['ETH', true],
    fn () => ['XRP', false],
    fn () => ['DOGE', false],
    fn () => ['XAUUSD', false],
]);

test('can place buy or sell order only', function ($data) {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    [$side, $passed] = $data;

    $response = $this->post(route('api.orders.store'), [
        'side' => $side,
    ]);

    if ($passed) {
        $response->assertValid(['side']);
    } else {
        $response->assertInvalid(['side']);
    }
})->with([
    fn () => ['buy', true],
    fn () => ['sell', true],
    fn () => ['short', false],
    fn () => ['etc', false],
    fn () => ['extra', false],
    fn () => [null, false],
]);

test('user need to have asset balance for selling', function ($data) {
    [$symbol, $order, $actual, $passed] = $data;

    $user = User::factory()->create();
    Asset::create([
        'user_id' => $user->id,
        'symbol' => $symbol,
        'amount' => $actual,
    ]);

    Sanctum::actingAs($user);

    $response = $this->post(route('api.orders.store'), [
        'symbol' => $symbol,
        'side' => OrderSide::SELL->value,
        'amount' => $order,
    ]);

    if ($passed) {
        $response->assertValid(['amount']);
    } else {
        $response->assertInvalid(['amount']);
    }
})->with([
    fn () => ['BTC', 0.001, 0.002, true],
    fn () => ['ETH', 0.002, 0.1, true],
    fn () => ['ETH', 0.002, 0.001, false],
]);

test('order price needs to be valid', function ($data) {
    [$price, $passed] = $data;

    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->post(route('api.orders.store'), [
        'price' => $price,
    ]);

    if ($passed) {
        $response->assertValid(['price']);
    } else {
        $response->assertInvalid(['price']);
    }
})->with([
    fn () => [1, true],
    fn () => [2, true],
    fn () => [null, false],
    fn () => ['', false],
    fn () => ['1.5', true],
]);

test('user need to have usd balance for buying', function ($data) {
    [$userBalance, $amount, $price, $passed] = $data;

    $user = User::factory()->create([
        'balance' => $userBalance,
    ]);

    Sanctum::actingAs($user);

    $response = $this->post(route('api.orders.store'), [
        'symbol' => 'BTC',
        'amount' => $amount,
        'price' => $price,
        'side' => OrderSide::BUY->value,
    ]);

    if ($passed) {
        $response->assertValid(['balance']);
    } else {
        $response->assertInvalid(['balance']);
    }
})->with([
    fn () => [100, 0.02, 25, true],
    fn () => [100, 0.02, 500, true],
    fn () => [10, 0.1, 550, false],
    fn () => [1000, 0.02, 550, true],
]);

test('buy order matching to sell order', function () {
    $this->freezeTime();

    $buyingUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $sellingUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $sellingUser->assets()->create([
        'symbol' => 'BTC',
        'amount' => 0.5,
    ]);
    $placeOrderAction = new PlaceOrder();
    $placeOrderAction->execute($sellingUser->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'amount' => 0.1,
        'price' => 500,
        'status' => OrderStatus::OPEN,
    ]));

    $sellingUserBtcAsset = $sellingUser->assets()->where('symbol', 'BTC')->first();
    expect($sellingUserBtcAsset->locked_amount)->toBe(0.1);
    expect($sellingUserBtcAsset->amount)->toBe(0.4);

    $this->travel(1)->minutes();

    Sanctum::actingAs($buyingUser);

    $this->post(route('api.orders.store'), [
        'symbol' => 'BTC',
        'side' => OrderSide::BUY->value,
        'amount' => 0.1,
        'price' => 600,
    ])->assertOk();

    $sellingUser->refresh();
    $buyingUser->refresh();
    $sellingUserBtcAsset->refresh();

    expect($sellingUser->balance)->toBe(1000 + (0.1 * 500));
    expect($sellingUserBtcAsset->amount)->toBe(0.4);
    expect($sellingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    expect($buyingUser->balance)->toBe(1000 - (0.1 * 500 * 1.015));
    $buyingUserBtcAsset = $buyingUser->assets()->where('symbol', 'BTC')->first();
    expect($buyingUserBtcAsset->amount)->toBe(0.1);
    expect($buyingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    assertDatabaseMissing(Order::class, [
        'status' => OrderStatus::OPEN,
    ]);
    assertDatabaseMissing(Order::class, [
        'status' => OrderStatus::CANCELLED,
    ]);

    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::FILLED,
        'side' => OrderSide::BUY,
        'price' => 600,
    ]);
    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::FILLED,
        'side' => OrderSide::SELL,
        'price' => 500,
    ]);
});

test('sell order matching to buy order', function () {
    $this->freezeTime();

    $buyingUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $sellingUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $buyingUser->assets()->create([
        'symbol' => 'BTC',
        'amount' => 0,
    ]);
    $sellingUser->assets()->create([
        'symbol' => 'BTC',
        'amount' => 0.5,
    ]);
    $placeOrderAction = new PlaceOrder();
    $placeOrderAction->execute($buyingUser->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'amount' => 0.1,
        'price' => 600,
        'status' => OrderStatus::OPEN,
    ]));

    $buyingUser->refresh();

    expect($buyingUser->balance)->toBe(1000 - (0.1 * 600 * 1.015));
    $buyingUserBtcAsset = $buyingUser->assets()->where('symbol', 'BTC')->first();
    expect($buyingUserBtcAsset->amount)->toBeBetween(0, 0);
    expect($buyingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    $this->travel(1)->minutes();

    Sanctum::actingAs($sellingUser);

    $this->post(route('api.orders.store'), [
        'symbol' => 'BTC',
        'side' => OrderSide::SELL->value,
        'amount' => 0.1,
        'price' => 500,
    ])->assertOk();

    $sellingUser->refresh();
    $buyingUser->refresh();

    $sellingUserBtcAsset = $sellingUser->assets()->where('symbol', 'BTC')->first();

    expect($sellingUser->balance)->toBe(1000 + (0.1 * 600));
    expect($sellingUserBtcAsset->amount)->toBe(0.4);
    expect($sellingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    expect($buyingUser->balance)->toBe(1000 - (0.1 * 600 * 1.015));
    $buyingUserBtcAsset = $buyingUser->assets()->where('symbol', 'BTC')->first();
    expect($buyingUserBtcAsset->amount)->toBe(0.1);
    expect($buyingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    assertDatabaseMissing(Order::class, [
        'status' => OrderStatus::OPEN,
    ]);
    assertDatabaseMissing(Order::class, [
        'status' => OrderStatus::CANCELLED,
    ]);

    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::FILLED,
        'side' => OrderSide::BUY,
        'price' => 600,
    ]);
    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::FILLED,
        'side' => OrderSide::SELL,
        'price' => 500,
    ]);
});

test('buy order matching to multiple sell orders', function () {
    $this->freezeTime();

    $buyingUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $sellingUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $sellingUser->assets()->create([
        'symbol' => 'BTC',
        'amount' => 0.5,
    ]);
    $placeOrderAction = new PlaceOrder();
    $placeOrderAction->execute($sellingUser->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'amount' => 0.1,
        'price' => 500,
        'status' => OrderStatus::OPEN,
    ]));

    $this->travel(1)->minutes();

    $placeOrderAction->execute($sellingUser->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'amount' => 0.1,
        'price' => 550,
        'status' => OrderStatus::OPEN,
    ]));

    $sellingUserBtcAsset = $sellingUser->assets()->where('symbol', 'BTC')->first();
    expect($sellingUserBtcAsset->locked_amount)->toBe(0.2);
    expect($sellingUserBtcAsset->amount)->toBe(0.3);

    $this->travel(1)->minutes();

    Sanctum::actingAs($buyingUser);

    $this->post(route('api.orders.store'), [
        'symbol' => 'BTC',
        'side' => OrderSide::BUY->value,
        'amount' => 0.1,
        'price' => 600,
    ])->assertOk();

    $sellingUser->refresh();
    $buyingUser->refresh();
    $sellingUserBtcAsset->refresh();

    expect($sellingUser->balance)->toBe(1000 + (0.1 * 500));
    expect($sellingUserBtcAsset->amount)->toBe(0.3);
    expect($sellingUserBtcAsset->locked_amount)->toBe(0.1);

    expect($buyingUser->balance)->toBe(1000 - (0.1 * 500 * 1.015));
    $buyingUserBtcAsset = $buyingUser->assets()->where('symbol', 'BTC')->first();
    expect($buyingUserBtcAsset->amount)->toBe(0.1);
    expect($buyingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    assertDatabaseMissing(Order::class, [
        'status' => OrderStatus::CANCELLED,
    ]);

    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::FILLED,
        'side' => OrderSide::BUY,
        'price' => 600,
    ]);
    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::FILLED,
        'side' => OrderSide::SELL,
        'price' => 500,
    ]);
    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::OPEN,
        'side' => OrderSide::SELL,
        'price' => 550,
    ]);
});

test('sell order matching to multiple buy orders', function () {
    $this->freezeTime();

    $buyingUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $sellingUser = User::factory()->create([
        'balance' => 1000,
    ]);
    $buyingUser->assets()->create([
        'symbol' => 'BTC',
        'amount' => 0,
    ]);
    $sellingUser->assets()->create([
        'symbol' => 'BTC',
        'amount' => 0.5,
    ]);
    $placeOrderAction = new PlaceOrder();
    $placeOrderAction->execute($buyingUser->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'amount' => 0.1,
        'price' => 600,
        'status' => OrderStatus::OPEN,
    ]));
    $this->travel(1)->minutes();
    $placeOrderAction->execute($buyingUser->orders()->make([
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'amount' => 0.1,
        'price' => 650,
        'status' => OrderStatus::OPEN,
    ]));

    $buyingUser->refresh();

    $buyOrderValue = round((0.1 * 600 * 1.015) + (0.1 * 650 * 1.015), 2);
    expect($buyingUser->balance)->toBe(1000 - $buyOrderValue);
    $buyingUserBtcAsset = $buyingUser->assets()->where('symbol', 'BTC')->first();
    expect($buyingUserBtcAsset->amount)->toBeBetween(0, 0);
    expect($buyingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    $this->travel(1)->minutes();

    Sanctum::actingAs($sellingUser);

    $this->post(route('api.orders.store'), [
        'symbol' => 'BTC',
        'side' => OrderSide::SELL->value,
        'amount' => 0.1,
        'price' => 500,
    ])->assertOk();

    $sellingUser->refresh();
    $buyingUser->refresh();

    $sellingUserBtcAsset = $sellingUser->assets()->where('symbol', 'BTC')->first();

    expect($sellingUser->balance)->toBe(1000 + round(0.1 * 600, 2));
    expect($sellingUserBtcAsset->amount)->toBe(0.4);
    expect($sellingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    $matchedTrade = round(0.1 * 600 * 1.015, 2);
    $lockedTrade = round(0.1 * 650 * 1.015, 2);
    expect($buyingUser->balance)->toBe(1000 - $matchedTrade - $lockedTrade);
    $buyingUserBtcAsset = $buyingUser->assets()->where('symbol', 'BTC')->first();
    expect($buyingUserBtcAsset->amount)->toBe(0.1);
    expect($buyingUserBtcAsset->locked_amount)->toBeBetween(0, 0);

    assertDatabaseMissing(Order::class, [
        'status' => OrderStatus::CANCELLED,
    ]);

    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::FILLED,
        'side' => OrderSide::SELL,
        'price' => 500,
    ]);
    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::FILLED,
        'side' => OrderSide::BUY,
        'price' => 600,
    ]);
    assertDatabaseHas(Order::class, [
        'status' => OrderStatus::OPEN,
        'side' => OrderSide::BUY,
        'price' => 650,
    ]);
});
