<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'tenant_id', 'branch_id',
        'name', 'email', 'password',
        'status', 'last_login_at',
        'failed_login_count', 'locked_until',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'last_login_at'      => 'datetime',
            'locked_until'       => 'datetime',
            'password'           => 'hashed',
        ];
    }

    // ── Relationships ─────────────────────────────────────────

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /** branch_id points to the warehouse the user is assigned to (branch = warehouse for MVP) */
    public function branch()
    {
        return $this->belongsTo(Warehouse::class, 'branch_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ── Auth-lock helpers (§5.1 logic surface, column added here) ──

    public function isLocked(): bool
    {
        return $this->locked_until !== null && now()->lessThan($this->locked_until);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function incrementFailedLogins(): void
    {
        $this->increment('failed_login_count');
        if ($this->fresh()->failed_login_count >= 5) {
            $this->update(['locked_until' => now()->addMinutes(15)]);
        }
    }

    public function clearLoginAttempts(): void
    {
        $this->update(['failed_login_count' => 0, 'locked_until' => null]);
    }
}
