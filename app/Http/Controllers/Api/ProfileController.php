<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class ProfileController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $user->load([
            'assets' => function ($query) {
                $query->orderBy('symbol');
            },
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => $user->balance,
            'assets' => $user->assets->mapWithKeys(function ($asset) {
                return [$asset->symbol => $asset->amount];
            })->toArray(),
        ]);
    }
}
