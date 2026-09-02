<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends TenantModel
{
    use SoftDeletes;

    protected $table = 'brands';

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
