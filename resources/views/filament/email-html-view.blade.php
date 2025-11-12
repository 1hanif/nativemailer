<x-filament-panels::page>



    <div class="email-preview-container">
        <iframe srcdoc="{{ $this->getRecord()->body_html }}" class="email-iframe" sandbox="allow-same-origin"
            title="Email Preview"></iframe>
    </div>

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
    </style>
</x-filament-panels::page>
