<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Preserve fractional master prices while keeping whole rupiah values compatible. */
class ProductPrice implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): int|float
    {
        $number = (float) $value;

        return $number === (float) (int) $number ? (int) $number : $number;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
