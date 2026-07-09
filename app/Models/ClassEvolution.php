<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassEvolution extends Model
{
    protected $fillable = [
        'from_class_id',
        'to_class_id',
        'required_level',
        'choice_order',
    ];
}
