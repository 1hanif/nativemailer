<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Events\EmailReceived;
use App\Filament\Resources\Emails\EmailResource;
use App\Models\Email;
use App\Models\Setting;
use App\Services\SmtpCatcher;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Native\Desktop\Facades\ChildProcess;

class ListEmails extends ListRecords
{
    protected static string $resource = EmailResource::class;

    /**
     * Re-render the table as soon as the SMTP catcher reports a new email.
     * NativePHP's EventWatcher broadcasts EmailReceived to every window,
     * where it arrives as a Livewire event named with a leading backslash.
     */
    #[On('native:\\'.EmailReceived::class)]
    public function onEmailReceived(): void
    {
        // Empty on purpose: receiving the event triggers a re-render.
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->modalHeading('Settings')
                ->modalDescription(fn (): string => self::catcherStatus())
                ->modalSubmitActionLabel('Save')
                ->fillForm(fn (): array => [
                    'port' => SmtpCatcher::port(),
                    'retention_days' => (int) Setting::get('retention_days', 0) ?: null,
                    'retention_max_emails' => (int) Setting::get('retention_max_emails', 0) ?: null,
                ])
                ->schema([
                    Section::make('SMTP catcher')->schema([
                        TextInput::make('port')
                            ->label('SMTP port')
                            ->numeric()
                            ->required()
                            ->minValue(1024)
                            ->maxValue(65535)
                            ->helperText(
                                'Apps that send mail here must use this as MAIL_PORT. '
                                    .'Changing it restarts the catcher (~2s downtime); mail sent during the restart is refused, not queued.'
                            ),
                    ]),
                    Section::make('Retention')
                        ->description('Older emails are deleted automatically every hour. Leave both empty to keep everything.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('retention_days')
                                ->label('Delete emails older than')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('days'),
                            TextInput::make('retention_max_emails')
                                ->label('Keep at most')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('emails'),
                        ]),
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

                    Setting::set('retention_days', (int) ($data['retention_days'] ?? 0));
                    Setting::set('retention_max_emails', (int) ($data['retention_max_emails'] ?? 0));
                    $pruned = Email::pruneNow();

                    $body = $pruned > 0 ? "Deleted {$pruned} emails outside the retention limits." : null;

                    if ($newPort !== $currentPort) {
                        Setting::set('smtp_port', $newPort);
                        ChildProcess::restart('smtp-catcher');

                        Notification::make()
                            ->title("SMTP catcher restarting on port {$newPort}")
                            ->body(trim("Update MAIL_PORT={$newPort} in every app that sends mail here — they still point at {$currentPort}. ".$body))
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Settings saved')->body($body)->success()->send();
                }),

            Action::make('deleteAll')
                ->label('Delete all')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete all emails?')
                ->modalDescription('Every captured email and attachment will be permanently deleted.')
                ->modalSubmitActionLabel('Delete all')
                ->hidden(fn (): bool => ! Email::query()->exists())
                ->action(function (): void {
                    $count = Email::query()->delete(); // attachments cascade

                    // Hand the freed pages back to the OS; SQLite keeps them otherwise.
                    // VACUUM can't run inside a transaction (e.g. under tests).
                    if (DB::transactionLevel() === 0) {
                        DB::statement('VACUUM');
                    }

                    Notification::make()->title("Deleted {$count} emails")->success()->send();
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
