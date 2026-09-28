<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Events\EmailReceived;
use App\Filament\Resources\Emails\EmailResource;
use App\Models\Email;
use App\Models\Setting;
use App\Services\CatcherStatus;
use App\Services\ReleaseEmail;
use App\Services\SmtpCatcher;
use App\Services\UnreadBadge;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
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
                    'relay_host' => ReleaseEmail::settings()['host'],
                    'relay_port' => ReleaseEmail::settings()['port'],
                    'relay_encryption' => ReleaseEmail::settings()['encryption'],
                    'relay_username' => ReleaseEmail::settings()['username'],
                    'relay_from' => ReleaseEmail::settings()['from'],
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
                    Section::make('Release relay')
                        ->description('A real SMTP server used by "Release" to forward a captured email to a real inbox. Leave the host empty to turn Release off.')
                        ->columns(2)
                        ->collapsed(fn (): bool => ! ReleaseEmail::isConfigured())
                        ->schema([
                            TextInput::make('relay_host')->label('Host')->placeholder('smtp.example.com'),
                            TextInput::make('relay_port')->label('Port')->numeric()->minValue(1)->maxValue(65535)->default(587),
                            Select::make('relay_encryption')
                                ->label('Encryption')
                                ->options([
                                    ReleaseEmail::ENCRYPTION_STARTTLS => 'STARTTLS (usually port 587)',
                                    ReleaseEmail::ENCRYPTION_TLS => 'TLS (usually port 465)',
                                    ReleaseEmail::ENCRYPTION_NONE => 'None',
                                ])
                                ->default(ReleaseEmail::ENCRYPTION_STARTTLS)
                                ->selectablePlaceholder(false),
                            TextInput::make('relay_from')
                                ->label('Envelope sender')
                                ->email()
                                ->placeholder('Original sender')
                                ->helperText('Some relays only accept mail from verified addresses.'),
                            TextInput::make('relay_username')->label('Username')->autocomplete('off'),
                            TextInput::make('relay_password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password')
                                ->placeholder(fn (): string => Setting::get('relay_password') ? 'Saved (leave blank to keep)' : ''),
                        ]),
                ])
                ->action(function (array $data): void {
                    $newPort = (int) $data['port'];
                    $currentPort = SmtpCatcher::port();

                    // Reject a port something else is already listening on.
                    // (The current port is legitimately "in use" — by the catcher.)
                    if ($newPort !== $currentPort && CatcherStatus::portInUse($newPort)) {
                        Notification::make()
                            ->title("Port {$newPort} is already in use")
                            ->body('Another process is listening on it. Pick a different port.')
                            ->danger()
                            ->send();

                        return;
                    }

                    ReleaseEmail::saveSettings($data);
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
                    UnreadBadge::sync();

                    // Hand the freed pages back to the OS; SQLite keeps them otherwise.
                    // VACUUM can't run inside a transaction (e.g. under tests).
                    if (DB::transactionLevel() === 0) {
                        DB::statement('VACUUM');
                    }

                    Notification::make()->title("Deleted {$count} emails")->success()->send();
                }),
        ];
    }

    /** Always-visible catcher status under the page title */
    public function getSubheading(): string|Htmlable|null
    {
        $status = CatcherStatus::check();
        $color = match ($status['state']) {
            CatcherStatus::RUNNING => 'success',
            CatcherStatus::PORT_TAKEN => 'warning',
            default => 'danger',
        };

        return new HtmlString(
            '<span class="catcher-status" data-state="'.e($status['state']).'" style="display:inline-flex;align-items:center;gap:.5rem">'
            .'<span style="width:.55rem;height:.55rem;border-radius:9999px;background:var(--'.$color.'-500)"></span>'
            .e($status['message'])
            .'</span>'
        );
    }

    private static function catcherStatus(): string
    {
        return CatcherStatus::check()['message'];
    }
}
