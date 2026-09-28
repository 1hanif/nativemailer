<?php

namespace App\Filament\Resources\Emails\Tables;

use App\Models\Email;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EmailsTable
{
    public static function configure(Table $table): Table
    {
        $unreadWeight = fn (Email $record) => $record->is_read ? FontWeight::Normal : FontWeight::Bold;

        return $table
            // Live refresh comes from the EmailReceived event (see ListEmails);
            // this slow poll is only a fallback, e.g. in a plain browser
            ->poll('30s')
            // Skip body/raw columns: the list only needs headers
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->select(Email::LIST_COLUMNS)
                ->withExists('attachments'))
            ->defaultSort('received_at', 'desc')
            ->searchPlaceholder('Search people, subject or body')
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
                    ->searchable(['to', 'cc', 'bcc']),
                TextColumn::make('subject')
                    ->weight($unreadWeight)
                    // Also matches the message body, not just the subject line
                    ->searchable(['subject', 'body_text', 'body_html']),
                IconColumn::make('attachments_exists')
                    ->label('')
                    // null (not false) when there are none: a false state makes
                    // IconColumn draw its boolean "no" icon
                    ->state(fn (Email $record): ?bool => $record->attachments_exists ?: null)
                    ->icon('heroicon-o-paper-clip')
                    ->tooltip(fn (Email $record): ?string => $record->attachments_exists ? 'Has attachments' : null)
                    ->color('gray'),
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
