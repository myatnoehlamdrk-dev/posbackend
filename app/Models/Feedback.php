<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    public const TYPE_COMMENT = 'comment';

    public const TYPE_SUGGESTION = 'suggestion';

    public const TYPE_BUG_REPORT = 'bug_report';

    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'shop_id',
        'type',
        'message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
