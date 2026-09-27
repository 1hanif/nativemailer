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
        'cc',
        'bcc',
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
     * The HTML body with cid: references (inline images) swapped for
     * data: URIs of the matching attachments, ready for the preview.
     */
    public function htmlWithInlineImages(): ?string
    {
        if (blank($this->body_html)) {
            return $this->body_html;
        }

        $byCid = collect($this->attachments ?? [])
            ->filter(fn (array $att) => !empty($att['content_id']) && !empty($att['content']))
            ->mapWithKeys(fn (array $att) => [
                strtolower($att['content_id']) => "data:{$att['content_type']};base64,{$att['content']}",
            ]);

        if ($byCid->isEmpty()) {
            return $this->body_html;
        }

        return preg_replace_callback(
            '/cid:([^"\'\s)>]+)/i',
            fn (array $m) => $byCid[strtolower(rawurldecode($m[1]))] ?? $m[0],
            $this->body_html
        );
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
