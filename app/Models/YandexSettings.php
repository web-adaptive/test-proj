<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YandexSettings extends Model
{
    protected $fillable = [
        'user_id',
        'yandex_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
