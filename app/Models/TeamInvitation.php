<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'email',
        'invited_by',
        'token',
        'expires_at',
        'accepted_at',
        'is_used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    // todo: add relationships
}
