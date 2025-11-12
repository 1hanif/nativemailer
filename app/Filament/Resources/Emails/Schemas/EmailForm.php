<?php

namespace App\Filament\Resources\Emails\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EmailForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('from'),
                TextInput::make('to'),
                TextInput::make('subject'),
                Textarea::make('body_text')
                    ->columnSpanFull(),
                Textarea::make('body_html')
                    ->columnSpanFull(),
                Textarea::make('attachments')
                    ->columnSpanFull(),
                Textarea::make('raw')
                    ->columnSpanFull(),
                DateTimePicker::make('received_at')
                    ->required(),
            ]);
    }
}
