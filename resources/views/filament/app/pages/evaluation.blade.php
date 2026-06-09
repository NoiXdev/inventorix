<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->reports as $report)
            <a
                href="{{ $report['url'] }}"
                class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 hover:shadow-md dark:border-white/10 dark:bg-gray-900"
            >
                <x-filament::icon :icon="$report['icon']" class="h-7 w-7 text-primary-500" />
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ $report['label'] }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $report['description'] }}
                </p>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
