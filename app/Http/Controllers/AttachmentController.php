<?php

namespace App\Http\Controllers;

use App\Models\EmailAttachment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    /**
     * Serve an attachment's bytes. Images render inline (previews, cid:
     * images in the HTML view); everything else, or ?download=1, is sent
     * as a download. Attachments are untrusted, so the response is
     * sandboxed: an HTML or SVG file can never run script in the app.
     */
    public function show(Request $request, EmailAttachment $attachment): Response
    {
        $inline = $attachment->isImage() && !$request->boolean('download');

        return response($attachment->content, 200, [
            'Content-Type' => $attachment->content_type,
            'Content-Length' => strlen($attachment->content),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $attachment->name,
                // ASCII fallback for the legacy filename= parameter
                preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $attachment->name) ?: 'attachment'
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
