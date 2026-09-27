<?php

namespace App\Filament\Resources\Emails\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EmailInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('from')
                    ->placeholder('-'),
                TextEntry::make('to')
                    ->placeholder('-'),
                TextEntry::make('cc')
                    ->label('CC')
                    ->visible(fn ($record) => filled($record->cc)),
                TextEntry::make('bcc')
                    ->label('BCC')
                    ->helperText('Envelope recipients not listed in To or CC')
                    ->visible(fn ($record) => filled($record->bcc)),
                TextEntry::make('subject')
                    ->placeholder('-'),
                TextEntry::make('received_at')
                    ->dateTime(),
                TextEntry::make('body_html')
                    ->hiddenLabel()
                    ->view('filament.email-html-view')
                    ->columnSpanFull(),
            ]);
    }
}
