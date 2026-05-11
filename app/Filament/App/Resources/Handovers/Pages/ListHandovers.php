<?php

namespace App\Filament\App\Resources\Handovers\Pages;

use App\Filament\App\Resources\Handovers\Actions\HandoverWizardAction;
use App\Filament\App\Resources\Handovers\HandoverResource;
use Filament\Resources\Pages\ListRecords;

class ListHandovers extends ListRecords
{
    protected static string $resource = HandoverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HandoverWizardAction::make('new_handover', [])
                ->label(trans('handover.action.new')),
        ];
    }
}
