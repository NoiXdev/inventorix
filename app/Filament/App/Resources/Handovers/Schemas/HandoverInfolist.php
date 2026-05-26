<?php

namespace App\Filament\App\Resources\Handovers\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HandoverInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(trans('handover.view.recipient_section'))->schema([
                TextEntry::make('recipient_name')->label(trans('handover.recipient.name')),
                TextEntry::make('recipient_email')->label(trans('handover.recipient.email')),
                TextEntry::make('recipient_kind')->badge(),
                TextEntry::make('type')->badge(),
            ])->columns(2),

            Section::make(trans('handover.view.details_section'))->schema([
                TextEntry::make('accessories')->label(trans('handover.form.accessories')),
                TextEntry::make('condition_notes')->label(trans('handover.form.condition_notes')),
                TextEntry::make('terms_text')->label(trans('handover.form.terms_header')),
            ])->columns(1),

            Section::make(trans('handover.view.assets_section'))->schema([
                RepeatableEntry::make('assets')->schema([
                    TextEntry::make('model.name'),
                    TextEntry::make('serial_number'),
                    TextEntry::make('pivot.state_from'),
                    TextEntry::make('pivot.state_to'),
                ])->columns(4),
            ]),

            Section::make(trans('handover.view.signature_section'))->schema([
                ImageEntry::make('signature_path')
                    ->disk(fn () => config('handover.disk'))
                    ->height(160)
                    ->visibility('private'),
            ]),

            Section::make(trans('handover.view.meta_section'))->schema([
                TextEntry::make('signed_at')->dateTime(),
                TextEntry::make('createdBy.name')->label(trans('handover.pdf.handed_by')),
                TextEntry::make('signature_ip')->label(trans('handover.pdf.signed_ip')),
                TextEntry::make('email_sent_at')->dateTime(),
            ])->columns(2),
        ]);
    }
}
