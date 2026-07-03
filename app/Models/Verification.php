<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $real_name
 * @property string $id_type
 * @property string $id_number
 * @property string $id_front_photo
 * @property string $id_back_photo
 * @property string $status
 * @property int|null $reviewer_id
 * @property string|null $review_note
 * @property string|null $reviewed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Verification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'real_name',
        'id_type',
        'id_number',
        'id_front_photo',
        'id_back_photo',
        'status',
        'reviewer_id',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(int $reviewerId, ?string $note = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewer_id' => $reviewerId,
            'review_note' => $note,
            'reviewed_at' => now(),
        ]);

        $this->user()->update(['verification_status' => 'verified']);
    }

    public function reject(int $reviewerId, ?string $note = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewer_id' => $reviewerId,
            'review_note' => $note,
            'reviewed_at' => now(),
        ]);

        $this->user()->update(['verification_status' => 'rejected']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
