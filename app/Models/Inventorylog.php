<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventorylog extends Model
{
protected $table = 'inventory_logs';
protected $fillable = [
    'product_id',
    'user_id',
    'change_amount',
    
    'reason'
];
}
