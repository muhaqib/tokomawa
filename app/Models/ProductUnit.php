<?php

namespace App\Models;

use App\Casts\ProductPrice;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductUnit extends Pivot
{
    public $incrementing = true;

    protected $table = 'product_units';

    protected function casts(): array
    {
        return [
            'master_data' => 'array',
            'is_base' => 'boolean',
            'conversion_factor' => 'decimal:4',
            'buy_price' => ProductPrice::class,
            'sell_price' => ProductPrice::class,
        ];
    }
}
