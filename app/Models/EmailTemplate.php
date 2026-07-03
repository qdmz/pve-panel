<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $subject
 * @property string $content
 * @property array|null $variables
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'subject',
        'content',
        'variables',
        'status',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function getByType(string $type): ?self
    {
        return Cache::remember("email_template.{$type}", 3600, function () use ($type) {
            return static::where('type', $type)->where('status', 'active')->first();
        });
    }

    public function compileContent(array $data): string
    {
        $content = $this->content;

        foreach ($data as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
            $content = str_replace("{ {$key} }", $value, $content);
        }

        return $content;
    }

    public function compileSubject(array $data): string
    {
        $subject = $this->subject;

        foreach ($data as $key => $value) {
            $subject = str_replace("{{$key}}", $value, $subject);
            $subject = str_replace("{ {$key} }", $value, $subject);
        }

        return $subject;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
