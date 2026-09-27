<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\EmailResource;
use App\Services\ReleaseEmail;
use App\Support\PreviewLinks;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Native\Desktop\Facades\Shell;
use Throwable;

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
            Action::make('release')
                ->label('Release')
                ->icon('heroicon-o-paper-airplane')
                ->modalHeading('Release to a real inbox')
                ->modalDescription(fn (): string => ReleaseEmail::isConfigured()
                    ? 'Forwards the original message unchanged through '.ReleaseEmail::settings()['host'].'.'
                    : 'Set up a release relay in Settings on the inbox page first.')
                ->modalSubmitActionLabel('Send')
                ->modalSubmitAction(fn (Action $action) => $action->disabled(! ReleaseEmail::isConfigured()))
                ->schema([
                    TextInput::make('recipients')
                        ->label('Send to')
                        ->placeholder('you@example.com, teammate@example.com')
                        ->required()
                        ->rule(fn () => function (string $attribute, $value, $fail) {
                            $parts = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $value)));
                            if (count($parts) === 0 || count(ReleaseEmail::parseRecipients((string) $value)) !== count(array_unique($parts))) {
                                $fail('Enter one or more valid email addresses, separated by commas.');
                            }
                        }),
                ])
                ->action(function (array $data): void {
                    if (! ReleaseEmail::isConfigured()) {
                        Notification::make()->title('No release relay configured')->body('Add one in Settings on the inbox page.')->warning()->send();

                        return;
                    }

                    $recipients = ReleaseEmail::parseRecipients($data['recipients']);

                    try {
                        ReleaseEmail::send($this->getRecord(), $recipients);
                    } catch (Throwable $e) {
                        Notification::make()->title('Release failed')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    Notification::make()->title('Released to '.implode(', ', $recipients))->success()->send();
                }),
        ];
    }
}
