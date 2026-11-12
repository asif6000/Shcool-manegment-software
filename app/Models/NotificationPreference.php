<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    public const CATEGORIES = [
        'general',
        'attendance',
        'fees',
        'exams',
        'notices',
        'homework',
        'classwork',
        'routine',
        'admission',
    ];

    public const CHANNELS = [
        'webpush',
        'expo',
    ];

    protected $fillable = [
        'user_id',
        'category',
        'channel',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
