<div class="flex items-center gap-3">
    <x-filament::modal id="scanner-modal" width="3xl" close-event-name="scanner-modal-close">
        <x-slot name="trigger">
            <x-filament::button color="success" id="scanner-start-button" >
                Scanner benutzen
            </x-filament::button>
        </x-slot>
        <x-slot name="heading">
            Scan
        </x-slot>

        <div id="scanner-container"
             data-error-permission="{{ trans('scanner.error.permission_denied') }}"
             data-error-not-found="{{ trans('scanner.error.not_found') }}"
             data-error-generic="{{ trans('scanner.error.generic') }}">
            <video id="scanner-area"></video>
            <p id="scanner-error"
               role="alert"
               class="hidden mt-2 text-sm text-danger-600 dark:text-danger-400"></p>
        </div>

        <x-filament::input.wrapper :valid="!$errors->has('serialNumber')">
            <x-filament::input class="border-gray-900" id="scanner-code" placeholder="Seriennummer eingeben" autofocus wire:model="serialNumber" wire:input.debounce.500ms="change" />
        </x-filament::input.wrapper>
    </x-filament::modal>
</div>
