<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class OrderNumberService
{
    /**
     * Generate voucher_no and order_id for a Sale or Order model.
     * Format: INV-{id}-{5-digit-random} / ORD-{id}-{5-digit-random}
     *
     * @return array{voucher_no: string, order_id: string}
     */
    public function generate(Model $model): array
    {
        $random5 = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);

        return [
            'voucher_no' => 'INV-'.$model->id.'-'.$random5,
            'order_id' => 'ORD-'.$model->id.'-'.$random5,
        ];
    }
}
