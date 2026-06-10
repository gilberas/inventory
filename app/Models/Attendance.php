<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends TenantModel
{
    protected $table = 'attendance';

    protected $fillable = [
        'tenant_id', 'employee_id', 'date', 'clock_in', 'clock_out', 'status', 'note',
    ];

    protected $casts = [
        'date'      => 'date',
        'clock_in'  => 'datetime',
        'clock_out' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
