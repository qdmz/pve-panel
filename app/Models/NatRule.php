<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vm_id
 * @property int $node_id
 * @property string $local_ip
 * @property int $local_port
 * @property int $public_port
 * @property string $protocol
 * @property string|null $description
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class NatRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'vm_id',
        'node_id',
        'local_ip',
        'local_port',
        'public_port',
        'protocol',
        'description',
        'status',
    ];

    protected $casts = [
        'local_port' => 'integer',
        'public_port' => 'integer',
    ];

    public function vm(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class, 'vm_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
