<x-filament-widgets::widget>
    <x-filament::section heading="快速上手">
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                完成以下步骤，开始使用 FilamentAdmin：
            </p>
            <ol class="space-y-2 text-sm">
                <li class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4 text-success-500" />
                    <span>安装完成，后台运行正常</span>
                </li>
                <li class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-user-plus" class="h-4 w-4 text-gray-400" />
                    <a href="/admin/admin-users/create" class="text-primary-600 hover:underline">创建第一个管理员账号</a>
                </li>
                <li class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-shield-check" class="h-4 w-4 text-gray-400" />
                    <a href="/admin/shield/roles" class="text-primary-600 hover:underline">配置角色与权限</a>
                </li>
                <li class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-bars-3" class="h-4 w-4 text-gray-400" />
                    <a href="/admin/menus" class="text-primary-600 hover:underline">自定义导航菜单</a>
                </li>
            </ol>
            <div class="pt-2">
                <x-filament::button wire:click="dismiss" color="gray" size="sm">
                    我已了解，关闭引导
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
