<?php

namespace App\Filament\App\Pages;

use App\Enums\QrCodeGeneratorType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;
use Livewire\Features\SupportRedirects\Redirector;

class QrCodeGenerator extends Page implements HasForms
{
    use InteractsWithSchemas;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.app.pages.qr-code-generator';

    public ?array $data = [
        'amount' => 20,
        'type' => QrCodeGeneratorType::TXT,
    ];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('Anzahl')
                    ->numeric()
                    ->required(),

                Select::make('type')
                    ->label('Output Type')
                    ->options(QrCodeGeneratorType::class)
                    ->enum(QrCodeGeneratorType::class)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function create(): Redirector
    {
        $amount = $this->form->getState()['amount'];
        $type = $this->form->getState()['type'];

        return to_route('qg', [
            'amount' => $amount,
            'type' => $type,
        ]);
    }
}
