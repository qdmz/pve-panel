<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $auth_type
 * @property string|null $api_token
 * @property string|null $username
 * @property string|null $password
 * @property string $virtualization
 * @property string $status
 * @property bool $nat_enabled
 * @property int|null $nat_start_port
 * @property int|null $nat_end_port
 * @property string|null $nat_network
 * @property string $bridge
 * @property string $storage
 * @property int $cpu_total
 * @property int $memory_total
 * @property int $disk_total
 * @property int $cpu_used
 * @property int $memory_used
 * @property int $disk_used
 * @property string|null $last_sync_at
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Node extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'auth_type',
        'api_token',
        'username',
        'password',
        'virtualization',
        'status',
        'nat_enabled',
        'nat_start_port',
        'nat_end_port',
        'nat_network',
        'bridge',
        'ipv6_bridge',
        'storage',
        'cpu_total',
        'memory_total',
        'disk_total',
        'cpu_used',
        'memory_used',
        'disk_used',
        'last_sync_at',
        'metadata',
    ];

    protected $casts = [
        'port' => 'integer',
        'nat_enabled' => 'boolean',
        'nat_start_port' => 'integer',
        'nat_end_port' => 'integer',
        'cpu_total' => 'integer',
        'memory_total' => 'integer',
        'disk_total' => 'integer',
        'cpu_used' => 'integer',
        'memory_used' => 'integer',
        'disk_used' => 'integer',
        'last_sync_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'api_token',
        'password',
    ];

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    public function natRules(): HasMany
    {
        return $this->hasMany(NatRule::class);
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function supportsKvm(): bool
    {
        return $this->virtualization === 'kvm' || $this->virtualization === 'both';
    }

    public function supportsLxc(): bool
    {
        return $this->virtualization === 'lxc' || $this->virtualization === 'both';
    }
}
