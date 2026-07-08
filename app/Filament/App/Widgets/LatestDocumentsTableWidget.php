<?php

namespace App\Filament\App\Widgets;

use App\Models\Attachment;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestDocumentsTableWidget extends BaseWidget
{
    protected static ?int $sort = -60;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(trans('document.widget.table.heading'))
            ->query(
                Attachment::query()
                    ->where('type', 'document')
                    ->with(['uploadedBy', 'attachable'])
                    ->latest()
            )
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('title')
                    ->label(trans('document.widget.table.title'))
                    ->state(fn (Attachment $record): string => $record->title ?: $record->original_name),
                TextColumn::make('category')
                    ->label(trans('document.widget.table.category'))
                    ->badge(),
                TextColumn::make('attachable_type')
                    ->label(trans('document.widget.table.attached_to'))
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),
                TextColumn::make('uploadedBy.name')
                    ->label(trans('document.widget.table.uploaded_by')),
                TextColumn::make('created_at')
                    ->label(trans('document.widget.table.uploaded_at'))
                    ->dateTime('d.m.Y H:i'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(trans('document.widget.table.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Attachment $record): string => route('attachments.open', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
