<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property int $cpu
 * @property int $memory
 * @property int $disk
 * @property int $bandwidth
 * @property int $traffic
 * @property float $monthly_price
 * @property float $yearly_price
 * @property array $node_ids
 * @property array $template_ids
 * @property string $status
 * @property int $sort_order
 * @property int $stock
 * @property array|null $features
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string|null $deleted_at
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'cpu',
        'memory',
        'disk',
        'bandwidth',
        'traffic',
        'monthly_price',
        'yearly_price',
        'node_ids',
        'template_ids',
        'status',
        'sort_order',
        'stock',
        'features',
    ];

    protected $casts = [
        'cpu' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'bandwidth' => 'integer',
        'traffic' => 'integer',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'node_ids' => 'array',
        'template_ids' => 'array',
        'sort_order' => 'integer',
        'stock' => 'integer',
        'features' => 'array',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasStock(): bool
    {
        return $this->stock === -1 || $this->stock > 0;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getPriceForCycle(string $cycle): float
    {
        return $cycle === 'yearly' ? $this->yearly_price : $this->monthly_price;
    }
}
