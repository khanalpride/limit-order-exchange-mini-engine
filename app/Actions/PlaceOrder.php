<?php

namespace App\Actions;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Models\Order;
use App\Models\User;

class PlaceOrder
{
    public function execute(Order $order)
    {
        if ($order->side === OrderSide::BUY) {
            $placedOrder = $this->buyOrder($order);
        } else {
            $placedOrder = $this->sellOrder($order);
        }

        return [
            'message' => 'Order placed successfully',
            'order' => $placedOrder,
        ];
    }

    private function buyOrder(Order $order)
    {
        $user = User::query()
            ->lockForUpdate()
            ->find($order->user_id);

        $order->save();

        $savedBuyOrder = Order::query()
            ->lockForUpdate()
            ->find($order->id);

        $matchingSellOrder = $this->getMatchingSellOrder(
            $user,
            $savedBuyOrder
        );

        // default matching rate is the buy order price
        $matchingRate = $order->price;

        if ($matchingSellOrder) {
            // update matching rate to the sell order price
            $matchingRate = $matchingSellOrder->price;

            $matchingSellOrderUser = User::query()
                ->lockForUpdate()
                ->find($matchingSellOrder->user_id);

            $this->performTrade(
                $savedBuyOrder,
                $matchingSellOrder,
                $user,
                $matchingSellOrderUser,
                $matchingRate
            );
        }

        $totalPrice = $order->amount * $matchingRate;
        $fee = $totalPrice * 0.015;
        $deduction = round($totalPrice + $fee, 2);

        $user->balance -= $deduction;
        $user->save();

        return $order;
    }

    private function sellOrder(Order $order)
    {
        $user = User::query()
            ->lockForUpdate()
            ->find($order->user_id);

        // lock the asset amount
        $sellUserAsset = $user->assets()
            ->where('symbol', $order->symbol)
            ->lockForUpdate()
            ->first();
        $sellUserAsset->locked_amount += $order->amount;
        $sellUserAsset->amount -= $order->amount;
        $sellUserAsset->save();

        $order->save();

        $savedSellOrder = Order::query()
            ->lockForUpdate()
            ->find($order->id);

        $matchingBuyOrder = $this->getMatchingBuyOrder(
            $user,
            $savedSellOrder
        );

        if ($matchingBuyOrder) {
            $matchingBuyOrderUser = User::query()
                ->lockForUpdate()
                ->find($matchingBuyOrder->user_id);

            $this->performTrade(
                $matchingBuyOrder,
                $savedSellOrder,
                $matchingBuyOrderUser,
                $user,
                $matchingBuyOrder->price
            );
        }

        return $order;
    }

    private function getMatchingBuyOrder(User $user, Order $order)
    {
        return Order::query()
            ->where('symbol', $order->symbol)
            ->where('side', OrderSide::BUY)
            ->where('price', '>=', $order->price)
            ->where('status', OrderStatus::OPEN)
            ->where('user_id', '!=', $user->id)
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();
    }

    private function getMatchingSellOrder(User $user, Order $order)
    {
        return Order::query()
            ->where('symbol', $order->symbol)
            ->where('side', OrderSide::SELL)
            ->where('price', '<=', $order->price)
            ->where('status', OrderStatus::OPEN)
            ->where('user_id', '!=', $user->id)
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();
    }

    private function performTrade(
        Order $buyOrder,
        Order $sellOrder,
        User $buyer,
        User $seller,
        $matchingRate
    ) {
        // update seller usd balance
        $seller->balance += round($sellOrder->amount * $matchingRate, 2);
        $seller->save();

        // update buyer user asset balance
        $buyerAsset = $buyer->assets()
            ->where('symbol', $buyOrder->symbol)
            ->lockForUpdate()
            ->first();

        if ($buyerAsset) {
            $buyerAsset->amount += $buyOrder->amount;
            $buyerAsset->save();
        } else {
            $buyer->assets()->create([
                'symbol' => $buyOrder->symbol,
                'amount' => $buyOrder->amount,
            ]);
        }

        // update seller user asset lock balance
        $seller->assets()
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->decrement('locked_amount', $sellOrder->amount);

        // mark both orders filled
        $buyOrder->status = OrderStatus::FILLED;
        $buyOrder->save();

        $sellOrder->status = OrderStatus::FILLED;
        $sellOrder->save();

        OrderMatched::dispatch($buyOrder);
        OrderMatched::dispatch($sellOrder);

        // insert to trades table
    }
}
