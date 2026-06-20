<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ===== 标签导航 ===== --}}
        <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
            <button wire:click="switchTab('official')"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
                        {{ $activeTab === 'official'
                            ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                官方市场
            </button>
            <button wire:click="switchTab('community')"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
                        {{ $activeTab === 'community'
                            ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                社区插件
            </button>
            <button wire:click="switchTab('installed')"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
                        {{ $activeTab === 'installed'
                            ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                已安装
            </button>
        </div>

        {{-- ===== 环境自检失败：降级横幅 (D-12-02) ===== --}}
        @if ($envCheckOk === false)
            <div class="rounded-xl bg-yellow-50 ring-1 ring-yellow-200 dark:bg-yellow-950 dark:ring-yellow-800 p-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-600 dark:text-yellow-400" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200">后台安装不可用</p>
                        <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                            当前环境不支持后台安装。请在终端手动执行以下命令：
                        </p>
                        <div class="mt-2 rounded-md bg-gray-100 dark:bg-gray-800 px-3 py-2">
                            <code class="block text-xs font-mono text-gray-800 dark:text-gray-200">
                                composer require &lt;vendor/package&gt;
                            </code>
                        </div>
                        @if (!empty($envCheckIssues))
                            <ul class="mt-2 space-y-0.5">
                                @foreach ($envCheckIssues as $issue)
                                    <li class="text-xs text-yellow-700 dark:text-yellow-300">· {{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== 官方市场标签 ===== --}}
        @if ($activeTab === 'official')
            @if (empty($entries))
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-content p-6 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">暂无市场数据，请检查网络连接后刷新。</p>
                    </div>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($entries as $entry)
                        @php
                            $sourceLabels = [
                                'official_trusted' => [
                                    'label' => '官方可信',
                                    'color' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                ],
                                'official_listed' => [
                                    'label' => '官方收录',
                                    'color' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                ],
                                'community' => [
                                    'label' => '社区',
                                    'color' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                ],
                            ];
                            $sourceMeta = $sourceLabels[$entry['source'] ?? 'community'] ?? $sourceLabels['community'];
                            $compatStatus = $entry['compatibility_status'] ?? 'unknown';
                            $compatMap = [
                                'compatible'   => ['label' => '兼容 Filament 5', 'color' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'],
                                'incompatible' => ['label' => '版本不兼容', 'color' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'],
                                'unknown'      => ['label' => '兼容性未知', 'color' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'],
                            ];
                            $compatMeta = $compatMap[$compatStatus] ?? $compatMap['unknown'];
                        @endphp
                        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="fi-section-content p-5 space-y-3">
                                {{-- 插件名称 + 来源徽章 --}}
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                        {{ $entry['display_name'] ?? $entry['slug'] ?? $entry['name'] ?? '' }}
                                    </h3>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $sourceMeta['color'] }}">
                                        {{ $sourceMeta['label'] }}
                                    </span>
                                </div>

                                {{-- 兼容性徽章 --}}
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $compatMeta['color'] }}">
                                    @if ($compatStatus === 'compatible')
                                        <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                                    @elseif ($compatStatus === 'incompatible')
                                        <x-heroicon-o-x-circle class="h-3.5 w-3.5" />
                                    @else
                                        <x-heroicon-o-question-mark-circle class="h-3.5 w-3.5" />
                                    @endif
                                    {{ $compatMeta['label'] }}
                                </span>

                                {{-- 摘要 --}}
                                @if (!empty($entry['summary']))
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $entry['summary'] }}</p>
                                @endif

                                {{-- 版本约束 --}}
                                @if (!empty($entry['version']))
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        版本约束：<code class="font-mono">{{ $entry['version'] }}</code>
                                    </p>
                                @endif

                                {{-- 安装区块 --}}
                                <div class="border-t border-gray-100 dark:border-gray-800 pt-3">
                                    @if ($envCheckOk && $compatStatus !== 'incompatible')
                                        @php $pkgName = $entry['package_name'] ?? $entry['slug'] ?? ''; @endphp
                                        <x-filament::button
                                            wire:click="installPlugin('{{ $pkgName }}')"
                                            color="primary"
                                            size="sm"
                                            icon="heroicon-o-arrow-down-tray">
                                            安装插件
                                        </x-filament::button>
                                    @elseif ($envCheckOk && $compatStatus === 'incompatible')
                                        {{-- 不兼容时隐藏安装按钮 (D-12-15) --}}
                                    @else
                                        {{-- 降级：可复制的 composer require 命令 --}}
                                        @php $pkgName = $entry['package_name'] ?? $entry['slug'] ?? ''; @endphp
                                        <div class="rounded-md bg-gray-100 dark:bg-gray-800 px-3 py-1.5">
                                            <code class="block text-xs font-mono text-gray-800 dark:text-gray-200 break-all">
                                                composer require {{ $pkgName }}
                                            </code>
                                        </div>
                                    @endif

                                    {{-- 文档链接 (CR-04: scheme 白名单校验) --}}
                                    @php $docUrl = $entry['documentation_url'] ?? ''; @endphp
                                    @if (!empty($docUrl) && preg_match('#^https?://#i', $docUrl))
                                        <a href="{{ $docUrl }}" target="_blank" rel="noopener noreferrer"
                                           class="mt-2 inline-block text-xs text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                            查看文档 →
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- ===== 社区插件标签 ===== --}}
        @if ($activeTab === 'community')
            {{-- 社区未审核提示 --}}
            <div class="rounded-xl bg-blue-50 ring-1 ring-blue-200 dark:bg-blue-950 dark:ring-blue-800 p-4">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-globe-alt class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        以下插件来自 Packagist 社区，<strong>未经官方审核</strong>，安装前请自行评估安全风险。
                    </p>
                </div>
            </div>

            @if (empty($communityResults))
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-content p-6 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">未找到相关插件，请尝试其他关键词。</p>
                        <div class="mt-3">
                            <x-filament::button wire:click="loadCommunity" color="gray" size="sm">
                                加载社区插件
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($communityResults as $item)
                        @php
                            $compatStatus = $item['compatibility_status'] ?? 'unknown';
                            $compatMap = [
                                'compatible'   => ['label' => '兼容 Filament 5', 'color' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'],
                                'incompatible' => ['label' => '版本不兼容', 'color' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'],
                                'unknown'      => ['label' => '兼容性未知', 'color' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'],
                            ];
                            $compatMeta = $compatMap[$compatStatus] ?? $compatMap['unknown'];
                        @endphp
                        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="fi-section-content p-5 space-y-3">
                                {{-- 插件名称 + 社区徽章 --}}
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                        {{ $item['name'] }}
                                    </h3>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        <x-heroicon-o-globe-alt class="h-3 w-3" />
                                        社区未审核
                                    </span>
                                </div>

                                {{-- 兼容性徽章 --}}
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $compatMeta['color'] }}">
                                    @if ($compatStatus === 'compatible')
                                        <x-heroicon-o-check-circle class="h-3.5 w-3.5" />
                                    @elseif ($compatStatus === 'incompatible')
                                        <x-heroicon-o-x-circle class="h-3.5 w-3.5" />
                                    @else
                                        <x-heroicon-o-question-mark-circle class="h-3.5 w-3.5" />
                                    @endif
                                    {{ $compatMeta['label'] }}
                                </span>

                                {{-- 描述 --}}
                                @if (!empty($item['description']))
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $item['description'] }}</p>
                                @endif

                                {{-- Stars / Downloads --}}
                                <div class="flex items-center gap-4 text-xs text-gray-400 dark:text-gray-500">
                                    @if (!empty($item['favers']))
                                        <span class="flex items-center gap-1">
                                            <x-heroicon-o-star class="h-3.5 w-3.5" />
                                            {{ number_format($item['favers']) }}
                                        </span>
                                    @endif
                                    @if (!empty($item['downloads']))
                                        <span>{{ number_format($item['downloads']) }} 次下载</span>
                                    @endif
                                </div>

                                {{-- 安装区块 --}}
                                <div class="border-t border-gray-100 dark:border-gray-800 pt-3">
                                    @if ($envCheckOk && $compatStatus !== 'incompatible')
                                        {{-- 社区插件安装按钮（CR-02 修复：直接调用 Livewire 方法，含社区风险提示） --}}
                                        <x-filament::button
                                            wire:click="installCommunityPlugin('{{ $item['name'] }}')"
                                            color="warning"
                                            size="sm"
                                            icon="heroicon-o-arrow-down-tray">
                                            安装插件
                                        </x-filament::button>
                                        @if ($compatStatus === 'unknown')
                                            <p class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
                                                兼容性未知：该插件未声明 Filament 版本约束，安装前请自行确认兼容性。
                                            </p>
                                        @endif
                                    @elseif ($compatStatus === 'incompatible')
                                        <p class="text-xs text-red-600 dark:text-red-400">
                                            版本不兼容：该插件要求 Filament {{ $item['filament_constraint'] ?? '?' }}，当前版本不兼容。
                                        </p>
                                    @else
                                        {{-- 降级 --}}
                                        <div class="rounded-md bg-gray-100 dark:bg-gray-800 px-3 py-1.5">
                                            <code class="block text-xs font-mono text-gray-800 dark:text-gray-200 break-all">
                                                composer require {{ $item['name'] }}
                                            </code>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- ===== 已安装标签 ===== --}}
        @if ($activeTab === 'installed')
            @php
                $installedPlugins = \FilamentAdmin\Models\Plugin::orderBy('name')->get();
            @endphp
            @if ($installedPlugins->isEmpty())
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-content p-6 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            未发现已安装的 Filament 插件。执行「扫描已安装插件」或通过 composer require 安装后重试。
                        </p>
                        <div class="mt-3">
                            <x-filament::button
                                wire:click="scanInstalledPlugins()"
                                color="gray"
                                size="sm"
                                icon="heroicon-o-magnifying-glass">
                                扫描已安装插件
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($installedPlugins as $plugin)
                        @php
                            $statusMap = [
                                'pending' => ['label' => '待安装', 'color' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
                                'running' => ['label' => '安装中', 'color' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'],
                                'done'    => ['label' => '已安装', 'color' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'],
                                'failed'  => ['label' => '安装失败', 'color' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'],
                            ];
                            $statusMeta = $statusMap[$plugin->init_status ?? 'pending'] ?? $statusMap['pending'];
                        @endphp
                        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="fi-section-content p-5 space-y-3">
                                {{-- 插件名称 + 状态徽章 --}}
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                        {{ $plugin->name }}
                                    </h3>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusMeta['color'] }}">
                                        {{ $statusMeta['label'] }}
                                    </span>
                                </div>

                                {{-- 包名 --}}
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $plugin->package_name }}</p>

                                {{-- 版本 --}}
                                @if ($plugin->installed_version)
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        版本：<code class="font-mono">{{ $plugin->installed_version }}</code>
                                    </p>
                                @endif

                                {{-- 安装进度日志（wire:poll 轮询中时显示）--}}
                                @if ($pollingPluginId === $plugin->id && $plugin->init_status === 'running')
                                    <div wire:poll.2000ms="checkInstallStatus" class="mt-2">
                                        @if (!empty($installLogs))
                                            <div class="rounded-lg bg-gray-900 p-4 max-h-48 overflow-y-auto">
                                                @foreach ($installLogs as $line)
                                                    <p class="text-sm font-mono text-green-400">{{ $line }}</p>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-sm text-gray-400 dark:text-gray-500">正在等待安装开始…</p>
                                        @endif
                                    </div>
                                @endif

                                {{-- 操作按钮 --}}
                                <div class="border-t border-gray-100 dark:border-gray-800 pt-3 flex gap-2">
                                    @if ($plugin->init_status === 'failed')
                                        <x-filament::button
                                            wire:click="retryInstall({{ $plugin->id }})"
                                            color="warning"
                                            size="sm">
                                            重试安装
                                        </x-filament::button>
                                    @endif
                                    <x-filament::button
                                        wire:click="uninstallPlugin({{ $plugin->id }})"
                                        color="danger"
                                        size="sm"
                                        icon="heroicon-o-trash">
                                        卸载
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    </div>
</x-filament-panels::page>
