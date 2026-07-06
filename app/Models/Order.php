<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $order_no
 * @property int $user_id
 * @property int $product_id
 * @property int|null $vm_id
 * @property float $amount
 * @property float $discount
 * @property int|null $coupon_id
 * @property string $billing_cycle
 * @property string $payment_method
 * @property string $payment_status
 * @property string|null $transaction_id
 * @property string|null $paid_at
 * @property string|null $status_note
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no',
        'user_id',
        'product_id',
        'vm_id',
        'amount',
        'discount',
        'coupon_id',
        'billing_cycle',
        'payment_method',
        'payment_status',
        'transaction_id',
        'paid_at',
        'status_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vm(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class, 'vm_id');
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class, 'vm_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public static function generateOrderNo(): string
    {
        return date('YmdHis') . strtoupper(substr(md5(uniqid()), 0, 10));
    }
}
