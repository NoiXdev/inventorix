<?php

namespace App\Filament\App\Resources\Handovers\Pages;

use App\Filament\App\Resources\Handovers\HandoverResource;
use App\Jobs\GenerateHandoverPdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewHandover extends ViewRecord
{
    protected static string $resource = HandoverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_pdf')
                ->label(trans('handover.notification.pdf_failed'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn (): bool => $this->record->pdf_path === null)
                ->action(function (): void {
                    GenerateHandoverPdf::dispatch($this->record->id);
                    Notification::make()
                        ->success()
                        ->title(trans('handover.notification.success'))
                        ->send();
                }),
        ];
    }
}
