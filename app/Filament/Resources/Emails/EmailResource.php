<?php

namespace App\Filament\Resources\Emails;

use App\Filament\Resources\Emails\Pages\CreateEmail;
use App\Filament\Resources\Emails\Pages\EditEmail;
use App\Filament\Resources\Emails\Pages\ListEmails;
use App\Filament\Resources\Emails\Pages\ViewEmail;
use App\Filament\Resources\Emails\Schemas\EmailForm;
use App\Filament\Resources\Emails\Schemas\EmailInfolist;
use App\Filament\Resources\Emails\Tables\EmailsTable;
use App\Models\Email;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'from';

    public static function form(Schema $schema): Schema
    {
        return EmailForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmailInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmails::route('/'),
            'create' => CreateEmail::route('/create'),
            'view' => ViewEmail::route('/{record}'),
            'edit' => EditEmail::route('/{record}/edit'),
        ];
    }
}
