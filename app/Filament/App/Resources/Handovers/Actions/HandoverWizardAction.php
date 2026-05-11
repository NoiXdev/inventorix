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
            ->schema([
                Radio::make('recipient_kind')
                    ->options(collect(RecipientKind::cases())->mapWithKeys(
                        fn (RecipientKind $k) => [$k->value => $k->getLabel()]
                    )->all())
                    ->required()
                    ->live(),

                Select::make('recipient_user_id')
                    ->label(trans('handover.recipient.select_user'))
                    ->options(fn () => \App\Models\User::query()
                        ->where('login_enabled', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->visible(fn (callable $get) => $get('recipient_kind') === RecipientKind::INTERNAL->value)
                    ->live()
                    ->afterStateUpdated(function (callable $set, ?string $state): void {
                        if ($state === null) {
                            return;
                        }
                        $u = \App\Models\User::find($state);
                        if ($u) {
                            $set('recipient_name', (string) $u->name);
                            $set('recipient_email', (string) $u->email);
                        }
                    }),

                TextInput::make('recipient_name')
                    ->label(trans('handover.recipient.name'))
                    ->maxLength(255)
                    ->required(),

                TextInput::make('recipient_email')
                    ->label(trans('handover.recipient.email'))
                    ->email()
                    ->maxLength(255),
            ]);
    }

    protected static function stepDetails(): Step
    {
        return Step::make(trans('handover.wizard.step.details'))
            ->schema([
                Textarea::make('accessories')
                    ->label(trans('handover.form.accessories'))
                    ->placeholder(trans('handover.form.accessories_placeholder'))
                    ->maxLength(2000)
                    ->rows(3),

                Textarea::make('condition_notes')
                    ->label(trans('handover.form.condition_notes'))
                    ->placeholder(trans('handover.form.condition_notes_placeholder'))
                    ->maxLength(2000)
                    ->rows(3),

                Placeholder::make('terms_preview')
                    ->label(trans('handover.form.terms_header'))
                    ->content(fn (callable $get): string => (string) $get('terms_text')),

                Hidden::make('terms_text'),
            ]);
    }

    protected static function stepSign(): Step
    {
        return Step::make(trans('handover.wizard.step.sign'))
            ->schema([
                Placeholder::make('confirm_recipient')
                    ->label(trans('handover.recipient.name'))
                    ->content(fn (callable $get): string => (string) $get('recipient_name')),

                ViewField::make('signature_png')
                    ->view('components.handover.signature-pad', [
                        'width'  => config('handover.signature.width'),
                        'height' => config('handover.signature.height'),
                    ])
                    ->required()
                    ->rules(['required', 'string', 'min:1']),
            ]);
    }

    /** @param array<string, mixed> $data */
    protected static function commit(array $data): void
    {
        $type = HandoverType::from((string) $data['type']);
        $recipientKind = RecipientKind::from((string) $data['recipient_kind']);

        $dataObj = new HandoverData(
            type: $type,
            recipientKind: $recipientKind,
            recipientUserId: $recipientKind === RecipientKind::INTERNAL ? (string) $data['recipient_user_id'] : null,
            recipientName: (string) $data['recipient_name'],
            recipientEmail: ! empty($data['recipient_email']) ? (string) $data['recipient_email'] : null,
            assetIds: array_values((array) $data['asset_ids']),
            accessories: ! empty($data['accessories']) ? (string) $data['accessories'] : null,
            conditionNotes: ! empty($data['condition_notes']) ? (string) $data['condition_notes'] : null,
            termsText: (string) $data['terms_text'],
            signaturePngBase64: (string) $data['signature_png'],
            signatureIp: request()->ip(),
            signatureUserAgent: substr((string) request()->userAgent(), 0, 512),
            createdById: (string) auth()->id(),
        );

        try {
            app(HandoverService::class)->commit($dataObj);
        } catch (\App\Exceptions\HandoverStateConflictException $e) {
            Notification::make()
                ->danger()
                ->title(trans('handover.notification.state_conflict'))
                ->send();
            return;
        }

        Notification::make()
            ->success()
            ->title(trans('handover.notification.success'))
            ->send();
    }
}
