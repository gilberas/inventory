<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Branch extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        parent::booted();

        // Auto-create one "Main Warehouse" whenever a branch is created
        static::created(function (Branch $branch) {
            $baseCode = 'WH-' . Str::upper(Str::substr($branch->code ?? Str::substr($branch->name, 0, 6), 0, 8)) . '-01';

            $warehouse            = new Warehouse([
                'branch_id'  => $branch->id,
                'name'       => 'Main Warehouse',
                'code'       => $baseCode,
                'is_default' => true,
                'is_active'  => true,
            ]);
            // Bypass fillable: set tenant_id directly so it persists regardless of auth state
            $warehouse->tenant_id = $branch->tenant_id;
            $warehouse->save();
        });
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
