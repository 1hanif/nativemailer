<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\EmailResource;
use App\Support\PreviewLinks;
use Filament\Resources\Pages\ViewRecord;
use Native\Desktop\Facades\Shell;

class ViewEmail extends ViewRecord
{
    protected static string $resource = EmailResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->markAsRead();
    }

    /**
     * Called from the preview when a link is clicked in the desktop app
     * (see PreviewLinks); opens it in the system browser.
     */
    public function openLink(string $url): void
    {
        if (PreviewLinks::isOpenable($url)) {
            Shell::openExternal($url);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
