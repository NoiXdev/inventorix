<?php

namespace App\Filament\App\Resources\Assets\Pages;

use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Resources\Handovers\Actions\HandoverWizardAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HandoverWizardAction::make(
                'handover_header',
                fn () => [$this->record->id],
            ),
            DeleteAction::make(),
        ];
    }
}
