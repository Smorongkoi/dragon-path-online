<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'user_id',
        'browser_token',
        'name',
        'level',
        'class_id',
        'element',
        'exp',
        'hp',
        'max_hp',
        'mp',
        'max_mp',
        'atk',
        'def',
        'atk_stat',
        'agi',
        'vit',
        'luk',
        'int_stat',
        'pvp_wins',
        'pvp_losses',
        'pvp_rating',
        'bot_wins',
        'bot_losses',
        'bot_rating',
        'last_seen_at',
        'pvp_queue_at',
        'pvp_match',
        'inventory',
        'class_history',
    ];

    protected $casts = [
        'inventory' => 'array',
        'class_history' => 'array',
        'last_seen_at' => 'datetime',
        'pvp_queue_at' => 'datetime',
        'pvp_match' => 'array',
    ];
}
