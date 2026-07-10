<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Monster extends Model
{
    protected $fillable = [
        'family_key',
        'evolution_stage',
        'evolves_from_id',
        'name',
        'level',
        'hp',
        'atk',
        'def',
        'exp_reward',
        'sprite_key',
    ];
}
