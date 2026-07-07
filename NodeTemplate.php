<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeTemplate extends Model
{
    protected $fillable = [
        'node_id',
        'template_id',
        'name',
        'type',
        'format',
        'size',
        'description',
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function getLabel(): string
    {
        $typeLabel = $this->type === 'lxc' ? '[LXC]' : '[KVM]';
        return "{$typeLabel} {$this->name}";
    }
}
