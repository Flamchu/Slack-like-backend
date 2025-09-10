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
        'type' => \App\Enums\ChannelType::class,
    ];

    /**
     * Get the team that owns the channel
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the channel
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all messages in this channel
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get recent messages in this channel
     */
    public function recentMessages(): HasMany
    {
        return $this->hasMany(Message::class)
            ->where('parent_id', null) // Only top-level messages
            ->latest()
            ->limit(50);
    }

    /**
     * Scope: Active channels only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Public channels only
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope: Private channels only
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Check if channel is accessible by a user
     */
    public function isAccessibleBy(User $user): bool
    {
        // If channel is public, check if user is team member
        if (!$this->is_private) {
            return $this->team->members()->where('users.id', $user->id)->exists();
        }

        // For private channels, additional logic would be needed
        // This would typically involve a channel_members pivot table
        return $this->team->members()->where('users.id', $user->id)->exists();
    }
}
