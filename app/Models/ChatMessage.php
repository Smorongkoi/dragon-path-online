<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'player_id',
        'message',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
