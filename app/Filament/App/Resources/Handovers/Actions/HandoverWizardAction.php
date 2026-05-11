<?php

namespace App\Filament\App\Resources\Handovers\Actions;

use App\DataObjects\HandoverData;
use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Asset;
use App\Services\HandoverService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\View as ViewField;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class HandoverWizardAction
{
    /**
     * @param array<int, string>|callable $assetIds  asset IDs to pre-fill, or closure returning them
     */
    public static function make(string $name, array|callable $assetIds = []): Action
    {
        return Action::make($name)
            ->label(trans('handover.action.handover'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->modalWidth('5xl')
            ->fillForm(function () use ($assetIds): array {
                $ids = is_callable($assetIds) ? $assetIds() : $assetIds;
                return [
                    'asset_ids' => array_values(array_filter($ids)),
                    'type' => HandoverType::ISSUE->value,
                    'recipient_kind' => RecipientKind::INTERNAL->value,
                    'terms_text' => (string) config('handover.terms'),
                    'signature_png' => '',
                ];
            })
            ->steps([
                self::stepType(),
                self::stepRecipient(),
                self::stepDetails(),
                self::stepSign(),
            ])
            ->action(function (array $data): void {
                self::commit($data);
            });
    }

    protected static function stepType(): Step
    {
        return Step::make(trans('handover.wizard.step.type'))
            ->schema([
                Radio::make('type')
                    ->options(collect(HandoverType::cases())->mapWithKeys(
                        fn (HandoverType $t) => [$t->value => $t->getLabel()]
                    )->all())
                    ->required()
                    ->live(),

                Select::make('asset_ids')
                    ->label(trans('handover.list.column.asset_count'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(function (callable $get): array {
                        $type = HandoverType::tryFrom((string) $get('type'));
                        if ($type === null) {
                            return [];
                        }
                        $allowed = array_map(fn ($s) => $s->value, $type->allowedStateFrom());
                        return Asset::query()
                            ->whereIn('state', $allowed)
                            ->with('model')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn (Asset $a) => [
                                $a->id => trim((optional($a->model)->name ?? '') . ' — ' . ($a->serial_number ?? $a->id)),
                            ])
                            ->all();
                    })
                    ->required()
                    ->rules(['array', 'min:1']),
            ]);
    }

    protected static function stepRecipient(): Step
    {
        return Step::make(trans('handover.wizard.step.recipient'))
            ->schema([]); // filled in next task
    }

    protected static function stepDetails(): Step
    {
        return Step::make(trans('handover.wizard.step.details'))
            ->schema([]);
    }

    protected static function stepSign(): Step
    {
        return Step::make(trans('handover.wizard.step.sign'))
            ->schema([]);
    }

    /** @param array<string, mixed> $data */
    protected static function commit(array $data): void
    {
        // filled in Task 18
    }
}
