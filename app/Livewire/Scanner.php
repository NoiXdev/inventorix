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
        ray($this->serialNumber);
        $this->validate();

        $existingAsset = Asset::find($this->serialNumber);

        if ($existingAsset) {
            return to_route("filament.app.resources.assets.edit", $existingAsset);
        } else {
            return to_route("filament.app.resources.assets.create", [
                'forceId' => $this->serialNumber,
            ]);
        }
    }
}
