<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseLocation extends Model
{
    protected $table = 'warehouse_locations';

    protected $fillable = [
        'warehouse_id',
        'aisle',
        'shelf',
        'bin',
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Auto-generate label from aisle/shelf/bin
    protected static function booted(): void
    {
        static::saving(function (WarehouseLocation $location) {
            $location->label = implode('-', array_filter([
                $location->aisle,
                $location->shelf,
                $location->bin,
            ]));
        });
    }
}
