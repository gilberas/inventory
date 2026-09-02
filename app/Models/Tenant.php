<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'status', 'config'];

    protected $casts = ['config' => 'array'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function idleTimeoutMinutes(): int
    {
        return (int) data_get($this->config, 'idle_timeout', 30);
    }
}
