<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 插件基本信息 --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content p-6">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $this->record->name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->record->package_name }}
                </p>
                @if ($this->record->description)
                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        {{ $this->record->description }}
                    </p>
                @endif
            </div>
        </div>

        {{-- 安装插件区块（D-06-15：仅展示 composer require 命令，无一键安装按钮）--}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content p-6">
                <h3 class="text-base font-medium text-gray-950 dark:text-white">安装插件</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    通过 Composer 在终端安装，安装完成后请使用「扫描已安装插件」同步到后台。
                </p>

                {{-- composer require 安装命令（供复制）--}}
                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                        安装命令（在项目根目录执行）
                    </label>
                    <div class="flex items-center gap-2 rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-2">
                        <code class="flex-1 text-sm font-mono text-gray-800 dark:text-gray-200">
                            composer require {{ $this->record->package_name }}
                        </code>
                    </div>
                </div>

                {{-- 文档链接 --}}
                @php
                    $entry = collect(config('official-market.entries', []))
                        ->firstWhere('package_name', $this->record->package_name);
                    $docUrl = $entry['documentation_url'] ?? null;
                @endphp
                @if ($docUrl)
                    <div class="mt-3">
                        <a href="{{ $docUrl }}" target="_blank" rel="noopener noreferrer"
                           class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400">
                            查看文档 →
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- 初始化进度区块（仅方案型插件显示）--}}
        @if ($this->record->kind === 'solution_plugin')
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content p-6">
                    <h3 class="text-base font-medium text-gray-950 dark:text-white">初始化进度</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        初始化将执行数据库迁移、资源发布和数据填充。
                    </p>

                    {{-- wire:poll.2000ms 实时轮询进度（PLUGIN-04）--}}
                    <div wire:poll.2000ms="refreshInitProgress" class="mt-4">
                        @if (!empty($initLogs))
                            <div class="rounded-lg bg-gray-900 p-4 max-h-48 overflow-y-auto">
                                @foreach ($initLogs as $line)
                                    <p class="text-sm font-mono text-green-400">{{ $line }}</p>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 dark:text-gray-500">暂无进度日志。</p>
                        @endif

                        {{-- 初始化失败时显示「重试初始化」按钮（D-06-11）--}}
                        @if ($initStatus === 'failed')
                            <div class="mt-4">
                                <x-filament::button wire:click="initialize" color="warning">
                                    重试初始化
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
