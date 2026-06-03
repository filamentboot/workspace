<x-filament-widgets::widget>
    <x-filament::section heading="常用功能">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($this->getActions() as $action)
                <a href="{{ $action['url'] }}"
                   class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    <x-filament::icon :icon="$action['icon']" class="h-6 w-6 text-primary-500" />
                    <span class="text-sm font-medium">{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
