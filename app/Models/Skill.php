<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'class_id',
        'name',
        'damage',
        'mana_cost',
        'cooldown',
        'description',
        'animation_key',
    ];
}
