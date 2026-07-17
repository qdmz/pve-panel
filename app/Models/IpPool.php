<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpPool extends Model
{
    use HasFactory;

    protected $fillable = [
        'node_id',
        'type',
        'subnet',
        'gateway',
        'bridge',
        'dhcp_range_start',
        'dhcp_range_end',
        'description',
    ];

    protected $casts = [
        'node_id' => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(IpAddress::class, 'pool_id');
    }
}
