<?php

namespace App\Models;

use App\Support\MimeHeader;
use App\Support\PreviewLinks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    use MassPrunable;

    /** Columns the inbox table needs; skips the heavy body and raw columns */
    public const LIST_COLUMNS = ['id', 'from', 'to', 'subject', 'received_at', 'is_read'];

    protected $fillable = [
        'from',
        'to',
        'cc',
        'bcc',
        'subject',
        'body_text',
        'body_html',
        'raw',
        'received_at',
        'is_read',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    /**
     * Emails past the retention settings: older than retention_days,
     * or beyond the newest retention_max_emails. Both are off when
     * empty/zero. Runs via `model:prune` (scheduled in routes/console.php);
     * attachments go with them through the foreign-key cascade.
     */
    public function prunable(): Builder
    {
        $days = (int) Setting::get('retention_days', 0);
        $max = (int) Setting::get('retention_max_emails', 0);

        if ($days <= 0 && $max <= 0) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where(function (Builder $query) use ($days, $max) {
            if ($days > 0) {
                $query->orWhere('received_at', '<', now()->subDays($days));
            }
            if ($max > 0) {
                $query->orWhereNotIn('id', static::query()->select('id')->latest('received_at')->latest('id')->limit($max));
            }
        });
    }

    /**
     * Apply the retention settings immediately (the scheduled
     * `model:prune` does the same hourly). Returns the number deleted.
     */
    public static function pruneNow(): int
    {
        return (new static)->pruneAll();
    }

    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->update(['is_read' => true]);
        }
    }

    /**
     * HTML for the preview iframe: inline images embedded, the email's
     * own scripts blocked, and link clicks handed to the parent page
     * (see PreviewLinks).
     */
    public function previewHtml(): ?string
    {
        $html = $this->htmlWithInlineImages();

        return blank($html) ? $html : PreviewLinks::prepare($html);
    }

    /**
     * The HTML body with cid: references (inline images) replaced by
     * data: URIs of the matching attachments.
     *
     * Embedded rather than linked on purpose: the preview iframe is
     * sandboxed (opaque origin), and Chromium's Local Network Access
     * rules block such frames from requesting 127.0.0.1, which is where
     * the attachment route lives. Only referenced images are embedded;
     * other attachments are still served by AttachmentController.
     */
    public function htmlWithInlineImages(): ?string
    {
        if (blank($this->body_html) || ! preg_match_all('/cid:([^"\'\s)>]+)/i', $this->body_html, $m)) {
            return $this->body_html;
        }

        $cids = array_unique(array_map(fn ($cid) => strtolower(rawurldecode($cid)), $m[1]));

        $byCid = $this->attachments()
            ->whereNotNull('content_id')
            ->get()
            ->filter(fn (EmailAttachment $att) => in_array(strtolower($att->content_id), $cids, true))
            ->mapWithKeys(fn (EmailAttachment $att) => [
                strtolower($att->content_id) => 'data:'.$att->content_type.';base64,'.base64_encode($att->content),
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
