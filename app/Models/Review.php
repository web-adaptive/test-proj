<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'yandex_settings_id',
        'date',
        'branch',
        'reviewer_name',
        'reviewer_phone',
        'rating',
        'text',
        'external_id',
    ];

    protected $casts = [
        'date' => 'datetime',
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function yandexSettings()
    {
        return $this->belongsTo(YandexSettings::class);
    }
}
