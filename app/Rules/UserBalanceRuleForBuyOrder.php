<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

class UserBalanceRuleForBuyOrder implements ValidationRule
{
    public function __construct(protected Request $request)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $price = $this->request->float('price');
        $amount = $this->request->float('amount');
        $totalCost = ($price * $amount) * 1.015; // Including 1.5% fee

        if ($value < $totalCost) {
            $fail('Insufficient balance to place this buy order.');
        }
    }
}
