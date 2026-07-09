<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Monster extends Model
{
    protected $fillable = [
        'name',
        'level',
        'hp',
        'atk',
        'def',
        'exp_reward',
        'sprite_key',
    ];
}
