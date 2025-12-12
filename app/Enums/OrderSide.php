<?php

namespace App\Enums;

use App\Concerns\HasBackedEnum;

enum OrderSide: string
{
    use HasBackedEnum;

    case BUY = 'buy';
    case SELL = 'sell';
}
