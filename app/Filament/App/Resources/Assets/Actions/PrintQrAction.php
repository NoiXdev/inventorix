<?php

namespace App\Filament\App\Resources\Assets\Actions;

use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class PrintQrAction
{
    public static function single(string $name = 'print_qr_single'): Action
    {
        return Action::make($name)
            ->label('QR drucken')
            ->icon(Heroicon::OutlinedQrCode)
            ->action(function (Asset $record, $livewire) {
                $livewire->dispatch(
                    'qr-print:open',
                    items: [self::buildItem($record)],
                );
            });
    }

    public static function bulk(string $name = 'print_qr_bulk'): BulkAction
    {
        return BulkAction::make($name)
            ->label('QR drucken')
            ->icon(Heroicon::OutlinedQrCode)
            ->action(function (Collection $records, $livewire) {
                $livewire->dispatch(
                    'qr-print:open',
                    items: $records->map(fn (Asset $a) => self::buildItem($a))->values()->all(),
                );
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return array{uuid: string, metadata?: array{modelName: string, serial: string}}
     */
    private static function buildItem(Asset $asset): array
    {
        $modelName = $asset->model?->name;
        $serial = $asset->serial_number;

        $item = ['uuid' => $asset->id];
        if ($modelName !== null && $serial !== null && $serial !== '') {
            $item['metadata'] = [
                'modelName' => $modelName,
                'serial' => $serial,
            ];
        }
        return $item;
    }
}
