<?php

namespace App\Models;

use App\Support\MimeHeader;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $fillable = [
        'from',
        'to',
        'subject',
        'body_text',
        'body_html',
        'attachments',
        'raw',
        'received_at',
        'is_read',
    ];

    protected $casts = [
        'attachments' => 'array',
        'received_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update(['is_read' => true]);
        }
    }

    /**
     * Decode RFC 2047 encoded-words at display time. Covers rows that
     * were stored before the catcher decoded headers; a no-op for
     * already-decoded values.
     */
    protected function subject(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => MimeHeader::decode($value),
        );
    }
}
