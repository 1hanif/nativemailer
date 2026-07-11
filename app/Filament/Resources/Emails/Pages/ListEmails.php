<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\EmailResource;
use App\Models\Setting;
use App\Services\SmtpCatcher;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Native\Desktop\Facades\ChildProcess;

class ListEmails extends ListRecords
{
    protected static string $resource = EmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('smtpSettings')
                ->label('SMTP Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->modalHeading('SMTP Catcher Settings')
                ->modalDescription(fn (): string => self::catcherStatus())
                ->modalSubmitActionLabel('Save & restart catcher')
                ->fillForm(fn (): array => ['port' => SmtpCatcher::port()])
                ->schema([
                    TextInput::make('port')
                        ->label('SMTP port')
                        ->numeric()
                        ->required()
                        ->minValue(1024)
                        ->maxValue(65535)
                        ->helperText(
                            'Apps that send mail here must use this as MAIL_PORT. '
                                . 'Saving restarts the catcher (~2s downtime); mail sent during the restart is refused, not queued.'
                        ),
                ])
                ->action(function (array $data): void {
                    $newPort = (int) $data['port'];
                    $currentPort = SmtpCatcher::port();

                    // Reject a port something else is already listening on.
                    // (The current port is legitimately "in use" — by the catcher.)
                    if ($newPort !== $currentPort && self::portInUse($newPort)) {
                        Notification::make()
                            ->title("Port {$newPort} is already in use")
                            ->body('Another process is listening on it. Pick a different port.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Setting::set('smtp_port', $newPort);
                    ChildProcess::restart('smtp-catcher');

                    Notification::make()
                        ->title("SMTP catcher restarting on port {$newPort}")
                        ->body($newPort !== $currentPort
                            ? "Update MAIL_PORT={$newPort} in every app that sends mail here — they still point at {$currentPort}."
                            : 'Port unchanged; catcher restarted.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function catcherStatus(): string
    {
        $port = SmtpCatcher::port();

        return self::portInUse($port)
            ? "🟢 Catcher is listening on 127.0.0.1:{$port}"
            : "🔴 Nothing is listening on 127.0.0.1:{$port} — the catcher may be down or still restarting.";
    }

    private static function portInUse(int $port): bool
    {
        $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
        if ($conn !== false) {
            fclose($conn);

            return true;
        }

        return false;
    }
}
