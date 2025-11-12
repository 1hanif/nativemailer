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
                TextEntry::make('subject')
                    ->placeholder('-'),
                TextEntry::make('received_at')
                    ->dateTime(),
                TextEntry::make('body_html')
                    ->placeholder('-')
                    ->view('filament.email-html-view')
                    ->columnSpanFull(),
                // TextEntry::make('attachments')
                //     ->placeholder('-'),
            ]);
    }
}
