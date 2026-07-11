{{-- Rendered inside the infolist — must NOT wrap in <x-filament-panels::page>,
     that duplicates the page header/breadcrumbs --}}
<div>
    @php
        $email = $this->getRecord();
    @endphp

    @if (filled($email->body_html))
        <div class="email-preview-container">
            {{-- Fully sandboxed: no scripts, no same-origin access — email HTML is untrusted --}}
            <iframe srcdoc="{{ $email->body_html }}" class="email-iframe" sandbox=""
                title="Email Preview"></iframe>
        </div>
    @elseif (filled($email->body_text))
        <div class="email-preview-container">
            <pre class="email-text">{{ $email->body_text }}</pre>
        </div>
    @else
        <div class="email-preview-empty">
            This email has no content.
        </div>
    @endif

    <style>
        .email-preview-container {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            overflow: hidden;
            background: white;
        }

        .email-iframe {
            width: 100%;
            height: 600px;
            border: none;
            display: block;
        }

        .email-text {
            margin: 0;
            padding: 1.5rem;
            min-height: 200px;
            max-height: 600px;
            overflow: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.875rem;
            line-height: 1.6;
            color: #1f2937;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .email-preview-empty {
            padding: 2rem;
            text-align: center;
            color: #9ca3af;
            border: 1px dashed #e5e7eb;
            border-radius: 0.5rem;
        }

        @media (prefers-color-scheme: dark) {
            .email-preview-empty {
                border-color: #374151;
                color: #6b7280;
            }
        }
    </style>
</div>
