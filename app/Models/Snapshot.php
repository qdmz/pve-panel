<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vm_id
 * @property string $snapshot_id
 * @property string $name
 * @property string|null $description
 * @property int $size
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Snapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'vm_id',
        'snapshot_id',
        'name',
        'description',
        'size',
        'status',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function vm(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class, 'vm_id');
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
