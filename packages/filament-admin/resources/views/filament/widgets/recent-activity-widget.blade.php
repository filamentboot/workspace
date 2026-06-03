<x-filament-widgets::widget>
    <x-filament::section heading="最近操作">
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($this->getRecentActivities() as $activity)
                <div class="flex items-center justify-between py-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ $activity->causer?->nickname ?? $activity->causer?->account ?? '系统' }}</span>
                        <span class="text-gray-500">{{ $activity->description }}</span>
                    </div>
                    <span class="text-xs text-gray-400">{{ $activity->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-400">暂无操作记录</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
