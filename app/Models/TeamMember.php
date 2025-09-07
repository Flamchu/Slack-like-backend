<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'joined_at',
        'invited_by',
        'is_active',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // todo: add relationships
}
