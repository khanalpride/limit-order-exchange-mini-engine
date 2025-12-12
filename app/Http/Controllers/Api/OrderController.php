<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;

class OrderController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $symbol = null;
        if ($request->has('symbol')) {
            $symbol = strtoupper($request->query('symbol'));
        }

        $side = null;
        if (
            $request->has('side')
            && in_array(strtolower($request->query('side')), OrderSide::values())
        ) {
            $side = OrderSide::tryFrom(strtolower($request->query('side')));
        }

        $status = OrderStatus::OPEN;
        if (
            $request->has('status')
            && in_array(intval($request->query('status')), OrderStatus::values())
        ) {
            $status = OrderStatus::tryFrom($request->query('status'));
        }

        $orders = $user->orders()
            ->when($symbol, function ($query) use ($symbol) {
                $query->where('symbol', $symbol);
            })
            ->when($side, function ($query) use ($side) {
                $query->where('side', $side);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }
}
