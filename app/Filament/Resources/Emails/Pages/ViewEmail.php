<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\EmailResource;
use App\Models\Setting;
use App\Services\EmailReleaser;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewEmail extends ViewRecord
{
    protected static string $resource = EmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('release')
                ->label('Release')
                ->icon('heroicon-o-paper-airplane')
                ->modalHeading('Release email')
                ->modalDescription('Forwards the original raw message, unchanged, through a real SMTP server. Relay settings are remembered.')
                ->modalSubmitActionLabel('Send')
                ->fillForm(fn (): array => [
                    'host' => Setting::get('relay_host', ''),
                    'port' => (int) Setting::get('relay_port', 587),
                    'encryption' => Setting::get('relay_encryption', 'auto'),
                    'username' => Setting::get('relay_username', ''),
                    'password' => Setting::get('relay_password', ''),
                    'recipients' => array_values(array_filter(array_map('trim', explode(',', (string) $this->getRecord()->to)))),
                ])
                ->schema([
                    TextInput::make('host')
                        ->label('SMTP host')
                        ->placeholder('smtp.gmail.com')
                        ->required(),
                    TextInput::make('port')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(65535),
                    Select::make('encryption')
                        ->options([
                            'auto' => 'None / STARTTLS when offered (587)',
                            'ssl' => 'Implicit SSL/TLS (465)',
                        ])
                        ->required(),
                    TextInput::make('username')
                        ->autocomplete(false),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->autocomplete(false)
                        ->helperText('Stored locally in the app database so you don\'t retype it. Use an app password, not your main one.'),
                    TagsInput::make('recipients')
                        ->label('Deliver to')
                        ->placeholder('Add recipient address')
                        ->required()
                        ->helperText('Envelope recipients — defaults to the original To addresses.'),
                ])
                ->action(function (array $data): void {
                    // Remember relay settings for next time
                    Setting::set('relay_host', $data['host']);
                    Setting::set('relay_port', $data['port']);
                    Setting::set('relay_encryption', $data['encryption']);
                    Setting::set('relay_username', $data['username'] ?? '');
                    Setting::set('relay_password', $data['password'] ?? '');

                    try {
                        app(EmailReleaser::class)->release(
                            email: $this->getRecord(),
                            host: $data['host'],
                            port: (int) $data['port'],
                            encryption: $data['encryption'],
                            username: $data['username'] ?? null,
                            password: $data['password'] ?? null,
                            recipients: $data['recipients'] ?? [],
                        );
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Release failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Email released')
                        ->body('Delivered to ' . implode(', ', $data['recipients']) . ' via ' . $data['host'])
                        ->success()
                        ->send();
                }),
        ];
    }
}
