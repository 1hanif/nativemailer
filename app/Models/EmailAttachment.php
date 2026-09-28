<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    protected $fillable = [
        'email_id',
        'name',
        'content_type',
        'size',
        'content_id',
        'inline',
        'content',
    ];

    /** Never load the file bytes unless asked for explicitly */
    protected $hidden = ['content'];

    protected $casts = [
        'inline' => 'boolean',
        'size' => 'integer',
    ];

    /** Every column except the file bytes */
    public function scopeWithoutContent(Builder $query): void
    {
        $query->select(['id', 'email_id', 'name', 'content_type', 'size', 'content_id', 'inline']);
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->content_type, 'image/');
    }

    public function url(): string
    {
        return route('attachments.show', $this);
    }
}
