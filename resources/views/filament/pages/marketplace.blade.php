<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 页面说明：区分「浏览官方市场」与「扫描已安装插件」--}}
        <div class="fi-section rounded-xl bg-blue-50 ring-1 ring-blue-200 dark:bg-blue-950 dark:ring-blue-800">
            <div class="fi-section-content p-4">
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    <strong>浏览官方市场</strong>：此页面展示远程官方市场收录的插件，供您浏览和获取安装命令。
                    安装插件请在终端执行下方 <code class="font-mono">composer require</code> 命令，
                    安装完成后请前往<strong>「已安装插件」→「扫描已安装插件」</strong>同步到后台。
                </p>
            </div>
        </div>

        {{-- 市场插件列表 --}}
        @if (empty($entries))
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content p-6 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">暂无市场数据，请检查网络连接后刷新。</p>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($entries as $entry)
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="fi-section-content p-5 space-y-3">
                            {{-- 插件名称 + 来源徽章 --}}
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $entry['display_name'] ?? $entry['slug'] }}
                                </h3>
                                @php
                                    $sourceLabels = [
                                        'official_trusted' => ['label' => '官方可信', 'color' => 'bg-green-100 text-green-700'],
                                        'official_listed'  => ['label' => '官方收录', 'color' => 'bg-blue-100 text-blue-700'],
                                        'community'        => ['label' => '社区', 'color' => 'bg-gray-100 text-gray-700'],
                                    ];
                                    $sourceMeta = $sourceLabels[$entry['source'] ?? 'community'] ?? $sourceLabels['community'];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $sourceMeta['color'] }}">
                                    {{ $sourceMeta['label'] }}
                                </span>
                            </div>

                            {{-- 摘要 --}}
                            @if (!empty($entry['summary']))
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $entry['summary'] }}
                                </p>
                            @endif

                            {{-- 版本 --}}
                            @if (!empty($entry['version']))
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    版本约束：<code class="font-mono">{{ $entry['version'] }}</code>
                                </p>
                            @endif

                            {{-- 安装插件：composer require 命令（D-06-15，无一键安装按钮）--}}
                            <div class="border-t border-gray-100 dark:border-gray-800 pt-3">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    安装命令
                                </label>
                                <div class="rounded-md bg-gray-100 dark:bg-gray-800 px-3 py-1.5">
                                    @php
                                        $installCmd = $entry['installation']['command']
                                            ?? ('composer require ' . $entry['package_name']);
                                    @endphp
                                    <code class="block text-xs font-mono text-gray-800 dark:text-gray-200 break-all">
                                        {{ $installCmd }}
                                    </code>
                                </div>

                                {{-- 文档链接 --}}
                                @if (!empty($entry['documentation_url']))
                                    <a href="{{ $entry['documentation_url'] }}" target="_blank" rel="noopener noreferrer"
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
    </div>
</x-filament-panels::page>
