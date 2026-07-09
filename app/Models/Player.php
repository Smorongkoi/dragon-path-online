<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'browser_token',
        'name',
        'level',
        'class_id',
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
        'inventory',
        'class_history',
    ];

    protected $casts = [
        'inventory' => 'array',
        'class_history' => 'array',
    ];
}
