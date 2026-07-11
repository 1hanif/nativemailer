<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\EmailResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmail extends ViewRecord
{
    protected static string $resource = EmailResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->markAsRead();
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
