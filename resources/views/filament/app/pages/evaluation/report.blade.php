{{-- resources/views/filament/app/pages/evaluation/report.blade.php --}}
<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    {{ $this->table }}
</x-filament-panels::page>
