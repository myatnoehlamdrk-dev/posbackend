<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'shop_id',
        'name',
        'email',
        'type',
        'password',
        'phone',
        'social',
        'image',
        'image_delete_url',
        'role',
        'address',
        'status',
        'nrc_no',
        'billing_way',
        'date_of_birth',
        'gender',
        'active_status',
        'is_verified',
        'failed_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'active_status' => 'boolean',
            'is_verified' => 'boolean',
            'failed_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** True while a credential-stuffing lockout is still in force. */
    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /** Minutes remaining on the lockout, rounded up. Zero when not locked. */
    public function lockoutMinutesRemaining(): int
    {
        if (! $this->isLockedOut()) {
            return 0;
        }

        return (int) ceil(now()->diffInMinutes($this->locked_until, false));
    }
}
