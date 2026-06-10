<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends TenantModel
{
    protected $fillable = ['tenant_id', 'name', 'start_time', 'end_time'];

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }
}
