<?php

namespace App\Filament\Pages;

use App\Enums\QrCodeGeneratorType;
use App\Models\Asset;
use Filament\Actions\Concerns\HasForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\BasePage;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Ramsey\Uuid\Uuid;

class QrCodeGenerator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.qr-code-generator';

    public ?array $data = [
        'amount' => 20,
        'type' => QrCodeGeneratorType::TXT
    ];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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

    public function create()
    {
        $amount = $this->form->getState()['amount'];
        $type = $this->form->getState()['type'];

        return to_route('qg', [
            'amount' => $amount,
            'type' => $type,
        ]);
    }
}
