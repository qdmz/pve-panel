<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'pool_id',
        'ip_address',
        'mac_address',
        'vm_id',
        'status',
        'allocated_at',
    ];

    protected $casts = [
        'pool_id' => 'integer',
        'vm_id'   => 'integer',
        'allocated_at' => 'datetime',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(IpPool::class);
    }
}
