<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'description',
        'type',
        'is_private',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_active' => 'boolean',
    ];

    // todo: add relationships
}
