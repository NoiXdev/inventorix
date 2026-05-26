<x-filament-panels::page>
    <form wire:submit="create">
        {{ $this->form }}

        <div class="py-4 flex gap-2">
            <x-filament::button type="submit">TXT herunterladen</x-filament::button>
            <x-filament::button type="button" color="gray" wire:click.prevent="print">Drucken</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
