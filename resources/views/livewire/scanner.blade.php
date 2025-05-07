<div>
    <x-filament::modal id="scanner-modal" width="3xl" close-event-name="scanner-modal-close">
        <x-slot name="trigger">
            <x-filament::button color="success" id="scanner-start-button" >
                Scanner benutzen
            </x-filament::button>
        </x-slot>
        <x-slot name="heading">
            Scan
        </x-slot>

        <video id="scanner-area"></video>

        <x-filament::input.wrapper :valid="!$errors->has('serialNumber')">
            <x-filament::input class="border-gray-900" id="scanner-code" placeholder="Seriennummer eingeben" autofocus wire:model="serialNumber" wire:input.debounce.500ms="change" />
        </x-filament::input.wrapper>
    </x-filament::modal>

    <x-filament::modal id="scanner-modal" width="3xl" close-event-name="scanner-modal-close">
        <x-slot name="trigger">
            <x-filament::button color="success" id="hand-scanner-start-button">
                Hand Scanner benutzen
            </x-filament::button>
        </x-slot>
        <x-slot name="heading">
            Scan
        </x-slot>
        <x-filament::input.wrapper :valid="!$errors->has('serialNumber')">
            <x-filament::input class="border-gray-900" id="scanner-code" placeholder="Seriennummer eingeben" autofocus wire:model="serialNumber" wire:input.debounce.500ms="change" />
        </x-filament::input.wrapper>
    </x-filament::modal>
</div>
