<?php

namespace App\Livewire;

use App\Models\Asset;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Scanner extends Component
{
    #[Validate('nullable|uuid')]
    public $serialNumber;

    public function render()
    {
        return view('livewire.scanner');
    }

    public function change()
    {
        $this->validate();

        $existingAsset = Asset::find($this->serialNumber);
        if ($existingAsset) {
            $this->redirectRoute('filament.app.resources.assets.edit', ['record' => $existingAsset], navigate: false);
        } else {
            $this->redirectRoute('filament.app.resources.assets.create', ['forceId' => $this->serialNumber], navigate: false);
        }
    }
}
