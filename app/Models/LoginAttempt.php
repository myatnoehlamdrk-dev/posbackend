<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'device_name',
        'successful',
        'failure_reason',
    ];

    protected $casts = [
        'successful' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}