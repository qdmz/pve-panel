<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $filename
 * @property string $path
 * @property int $size
 * @property string $type
 * @property string $status
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'path',
        'size',
        'type',
        'status',
        'notes',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
