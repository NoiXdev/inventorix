<x-filament-panels::page>
    <form wire:submit="create">
        {{ $this->form }}

        <div class="py-4">
            <x-filament::button type="submit">Generate</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
