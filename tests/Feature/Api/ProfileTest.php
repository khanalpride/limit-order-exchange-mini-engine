<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('sends status ok on profile route', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->get(route('api.profile'))
        ->assertStatus(200);
});

test('receive actual user balance in usd', function () {
    $user = User::factory()->create([
        'balance' => 1500.75,
    ]);

    Sanctum::actingAs($user);

    $this->get(route('api.profile'))
        ->assertExactJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => '1500.75',
            'assets' => [],
        ]);
});

test('receive user assets balance', function () {
    $user = User::factory()->create([
        'balance' => 1400.15,
    ]);

    $user->assets()->createMany([
        ['symbol' => 'BTC', 'amount' => 0.5],
        ['symbol' => 'ETH', 'amount' => 10],
    ]);

    Sanctum::actingAs($user);

    $this->get(route('api.profile'))
        ->assertExactJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => '1400.15',
            'assets' => [
                'BTC' => '0.50',
                'ETH' => '10.00',
            ],
        ]);
});
