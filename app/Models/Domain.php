<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vm_id
 * @property string $domain
 * @property int $target_port
 * @property bool $ssl_enabled
 * @property string $ssl_status
 * @property string|null $ssl_expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'vm_id',
        'domain',
        'target_port',
        'ssl_enabled',
        'ssl_status',
        'ssl_expires_at',
    ];

    protected $casts = [
        'target_port' => 'integer',
        'ssl_enabled' => 'boolean',
        'ssl_expires_at' => 'datetime',
    ];

    public function vm(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class, 'vm_id');
    }

    public function hasActiveSsl(): bool
    {
        return $this->ssl_status === 'active';
    }
}
