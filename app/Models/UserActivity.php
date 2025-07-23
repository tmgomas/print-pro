<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    /**
     * Disable updated_at timestamp since we only track creation
     */
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that owns the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by action type
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for recent activities
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted metadata
     */
    public function getFormattedMetadataAttribute(): string
    {
        if (empty($this->metadata)) {
            return '';
        }

        $formatted = [];
        foreach ($this->metadata as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $formatted[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }

        return implode(', ', $formatted);
    }

    /**
     * Get activity icon based on action type
     */
    public function getIconAttribute(): string
    {
        $icons = [
            'user_login' => 'log-in',
            'user_logout' => 'log-out',
            'user_registered' => 'user-plus',
            'user_created' => 'user-plus',
            'user_updated' => 'user-check',
            'user_deleted' => 'user-x',
            'password_changed' => 'key',
            'password_reset_requested' => 'unlock',
            'password_reset_completed' => 'check-circle',
            'email_verified' => 'mail-check',
            'status_changed' => 'toggle-left',
        ];

        return $icons[$this->action] ?? 'activity';
    }

    /**
     * Get activity color based on action type
     */
    public function getColorAttribute(): string
    {
        $colors = [
            'user_login' => 'green',
            'user_logout' => 'gray',
            'user_registered' => 'blue',
            'user_created' => 'blue',
            'user_updated' => 'yellow',
            'user_deleted' => 'red',
            'password_changed' => 'purple',
            'password_reset_requested' => 'orange',
            'password_reset_completed' => 'green',
            'email_verified' => 'green',
            'status_changed' => 'blue',
        ];

        return $colors[$this->action] ?? 'gray';
    }
}