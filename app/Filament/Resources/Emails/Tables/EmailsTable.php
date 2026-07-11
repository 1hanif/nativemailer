<?php

namespace App\Filament\Resources\Emails\Tables;

use App\Models\Email;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class EmailsTable
{
    public static function configure(Table $table): Table
    {
        $unreadWeight = fn (Email $record) => $record->is_read ? FontWeight::Normal : FontWeight::Bold;

        return $table
            ->poll('5s')
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('is_read')
                    ->label('')
                    ->badge()
                    ->state(fn (Email $record): ?string => $record->is_read ? null : 'New')
                    ->color('primary'),
                TextColumn::make('from')
                    ->weight($unreadWeight)
                    ->searchable(),
                TextColumn::make('to')
                    ->weight($unreadWeight)
                    ->searchable(),
                TextColumn::make('subject')
                    ->weight($unreadWeight)
                    ->searchable(),
                TextColumn::make('received_at')
                    ->weight($unreadWeight)
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_read')
                    ->label('Read status')
                    ->trueLabel('Read')
                    ->falseLabel('Unread'),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAsRead')
                        ->label('Mark as read')
                        ->icon('heroicon-o-envelope-open')
                        ->action(fn (Collection $records) => $records->each->markAsRead())
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markAsUnread')
                        ->label('Mark as unread')
                        ->icon('heroicon-o-envelope')
                        ->action(fn (Collection $records) => $records->each(
                            fn (Email $record) => $record->update(['is_read' => false])
                        ))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
