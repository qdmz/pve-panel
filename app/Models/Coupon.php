<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $type
 * @property string $code
 * @property string $discount_type
 * @property float $discount_value
 * @property float $min_order_amount
 * @property int $max_uses
 * @property int $used_count
 * @property int $per_user_limit
 * @property string|null $expires_at
 * @property array|null $applicable_products
 * @property string $status
 * @property float|null $balance
 * @property int|null $used_by
 * @property int|null $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'per_user_limit',
        'expires_at',
        'applicable_products',
        'status',
        'balance',
        'used_by',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'per_user_limit' => 'integer',
        'expires_at' => 'datetime',
        'applicable_products' => 'array',
        'balance' => 'decimal:2',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->max_uses > 0 && $this->used_count >= $this->max_uses) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public function calculateDiscount(float $orderAmount): float
    {
        if ($orderAmount < $this->min_order_amount) {
            return 0;
        }

        if ($this->discount_type === 'percentage') {
            return $orderAmount * ($this->discount_value / 100);
        }

        return min($this->discount_value, $orderAmount);
    }
}
