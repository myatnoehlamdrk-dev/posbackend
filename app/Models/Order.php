<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class order extends Model
{
    protected $fillable = [
    'user_id',
    'c_id',
    'invoice_no',
    'total_amount',
    'payment'
    ];
}
