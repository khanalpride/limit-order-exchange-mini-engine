<?php

namespace App\Concerns;

trait HasBackedEnum
{
    public static function values()
    {
        return collect(static::cases())->map(fn ($case) => $case->value)->toArray();
    }
}
