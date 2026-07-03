<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property int $node_id
 * @property int|null $product_id
 * @property int|null $order_id
 * @property string $vm_id
 * @property string $name
 * @property string $type
 * @property int $cpu
 * @property int $memory
 * @property int $disk
 * @property int $bandwidth
 * @property int $traffic_limit
 * @property int $traffic_used
 * @property string|null $ip
 * @property string|null $os_template
 * @property string|null $root_password
 * @property string $status
 * @property string $expires_at
 * @property string|null $last_renewed_at
 * @property string|null $next_due_date
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string|null $deleted_at
 */
class VirtualMachine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'node_id',
        'product_id',
        'order_id',
        'vm_id',
        'name',
        'type',
        'cpu',
        'memory',
        'disk',
        'bandwidth',
        'traffic_limit',
        'traffic_used',
        'ip',
        'ipv6_address',
        'nat_ipv4',
        'os_template',
        'root_password',
        'status',
        'expires_at',
        'last_renewed_at',
        'next_due_date',
        'notes',
    ];

    protected $casts = [
        'cpu' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'bandwidth' => 'integer',
        'traffic_limit' => 'integer',
        'traffic_used' => 'integer',
        'expires_at' => 'datetime',
        'last_renewed_at' => 'datetime',
        'next_due_date' => 'date',
        'product_id' => 'integer',
        'order_id' => 'integer',
    ];

    protected $hidden = [
        'root_password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function natRules(): HasMany
    {
        return $this->hasMany(NatRule::class, 'vm_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'vm_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class, 'vm_id');
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeExpiring($query, int $days = 7)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }
}
