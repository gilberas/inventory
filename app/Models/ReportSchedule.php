<?php

namespace App\Models;

class ReportSchedule extends TenantModel
{
    protected $table = 'report_schedules';

    protected $fillable = [
        'user_id',
        'report_type',
        'params',
        'frequency',
        'email',
        'last_sent_at',
        'is_active',
    ];

    protected $casts = [
        'params'       => 'array',
        'last_sent_at' => 'datetime',
        'is_active'    => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if (is_null($this->last_sent_at)) {
            return true;
        }
        return match ($this->frequency) {
            'daily'   => $this->last_sent_at->lt(now()->startOfDay()),
            'weekly'  => $this->last_sent_at->lt(now()->startOfWeek()),
            'monthly' => $this->last_sent_at->lt(now()->startOfMonth()),
            default   => false,
        };
    }
}
