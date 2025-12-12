<?php

namespace App\Http\Controllers\Api;

use App\Actions\PlaceOrder;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Rules\UserAssetRuleForSellOrder;
use App\Rules\UserBalanceRuleForBuyOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

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

    public function store(Request $request, PlaceOrder $placeOrderAction)
    {
        $request->merge([
            'balance' => $request->user()->balance,
        ]);

        $data = $request->validate([
            'symbol' => [
                'required',
                Rule::in(availableSymbols()),
            ],
            'side' => [
                'required',
                Rule::in(OrderSide::values()),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.001',
                Rule::when(strtolower($request->input('side')) === OrderSide::SELL->value, [
                    'required',
                    'numeric',
                    new UserAssetRuleForSellOrder($request),
                ]),
            ],
            'price' => [
                'required',
                'numeric',
                'min:1',
            ],
            'balance' => [
                Rule::when(strtolower($request->input('side')) === OrderSide::BUY->value, [
                    'required',
                    'numeric',
                    new UserBalanceRuleForBuyOrder($request),
                ]),
            ],
        ]);

        $order = $request->user()->orders()->make([
            'symbol' => strtoupper($data['symbol']),
            'side' => OrderSide::tryFrom($data['side']),
            'amount' => $data['amount'],
            'price' => $data['price'],
            'status' => OrderStatus::OPEN,
        ]);

        return response()->json($placeOrderAction->execute($order));
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        // Lock the order for update to prevent
        $lockedOrder = Order::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::OPEN)
            ->lockForUpdate()
            ->first();

        if ($lockedOrder) {
            $lockedOrder->status = OrderStatus::CANCELLED;
            $lockedOrder->save();

            if ($lockedOrder->side === OrderSide::BUY) {
                // Refund the balance to user for buy order
                $totalPrice = $lockedOrder->amount * $lockedOrder->price;
                $fee = $totalPrice * 0.015;
                $refundAmount = round($totalPrice + $fee, 2);

                $user->balance += $refundAmount;
                $user->save();
            } else {
                // Unlock the asset amount for sell order
                $userAsset = $user->assets()
                    ->where('symbol', $lockedOrder->symbol)
                    ->lockForUpdate()
                    ->first();

                $userAsset->locked_amount -= $lockedOrder->amount;
                $userAsset->amount += $lockedOrder->amount;
                $userAsset->save();
            }
        }

        if ($lockedOrder) {
            return response()->json([
                'message' => 'Order cancelled successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Order not found or cannot be cancelled.',
        ], Response::HTTP_FORBIDDEN);
    }
}
