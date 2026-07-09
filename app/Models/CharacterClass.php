<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterClass extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'milestone_level',
        'hp_bonus',
        'mp_bonus',
        'atk_bonus',
        'def_bonus',
        'ability_name',
        'ability_description',
        'sprite_key',
    ];
}
