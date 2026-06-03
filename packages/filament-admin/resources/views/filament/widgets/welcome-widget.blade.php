<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-4">
            <div>
                <h2 class="text-xl font-bold tracking-tight">
                    欢迎回来，{{ $this->getAdminNickname() }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    欢迎使用 FilamentAdmin 后台管理系统
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
