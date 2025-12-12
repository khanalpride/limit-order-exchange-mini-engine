<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

class UserAssetRuleForSellOrder implements ValidationRule
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
        $symbol = strtoupper($this->request->input('symbol'));
        $amount = $this->request->float('amount');

        $currentUserHasAssetAmount = $this->request->user()
            ->assets()
            ->where('symbol', $symbol)
            ->where('amount', '>=', $amount)
            ->exists();

        if (! $currentUserHasAssetAmount) {
            $fail('Insufficient asset amount to place this sell order.');
        }
    }
}
