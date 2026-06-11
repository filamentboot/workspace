<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FilamentAdmin — Laravel 13 + Filament 5 后台基础平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

    <!-- 顶部导航 -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl font-bold text-indigo-600">FilamentAdmin</span>
                <span class="px-2 py-0.5 text-xs bg-indigo-100 text-indigo-700 rounded-full font-medium">v0.5.0</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="https://github.com/laravel-stack/filament-admin" class="text-gray-500 hover:text-gray-700 text-sm">GitHub</a>
                <a href="https://demo.xitongapp.com" target="_blank" rel="noopener" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">查看演示</a>
            </div>
        </div>
    </nav>

    <!-- 第一块：项目定位 -->
    <section class="bg-gradient-to-br from-indigo-600 to-indigo-800 text-white py-20">
        <div class="max-w-6xl mx-auto px-6 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-6">
                Laravel 13 + Filament 5 后台基础平台
            </h1>
            <p class="text-xl text-indigo-100 mb-4 max-w-3xl mx-auto">
                FilamentAdmin 是对标 FastAdmin / laravel-admin 的 Composer 包，通过
                <code class="bg-indigo-700 px-2 py-0.5 rounded text-white font-mono">composer require</code>
                即可获得含认证、权限、菜单、操作日志、部门数据权限的完整后台底座。
            </p>
            <p class="text-indigo-200 text-sm mb-10">
                对标 <strong class="text-white">siubie/kaido-kit</strong>（Filament 3.x 国外同路线）和 <strong class="text-white">FastAdmin</strong>（ThinkPHP 国内同路线）
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="https://demo.xitongapp.com" target="_blank" rel="noopener"
                   class="px-8 py-3 bg-white text-indigo-600 rounded-lg font-semibold hover:bg-indigo-50 transition text-lg">
                    立即体验演示站
                </a>
                <a href="https://packagist.org/packages/laravelstack/filament-admin" target="_blank" rel="noopener"
                   class="px-8 py-3 border-2 border-white text-white rounded-lg font-semibold hover:bg-white hover:text-indigo-600 transition text-lg">
                    Packagist 主页
                </a>
            </div>
        </div>
    </section>

    <!-- 第二块：功能清单 -->
    <section class="py-16 bg-white">
        <div class="max-w-6xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-center text-gray-800 mb-12">核心功能</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                <div class="p-6 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">认证系统</h3>
                    <p class="text-gray-500 text-sm">支持账号名 / 邮箱双模式登录，Sanctum API Token，双因素认证（2FA），用户模拟登录（Impersonation）</p>
                </div>

                <div class="p-6 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">权限管理</h3>
                    <p class="text-gray-500 text-sm">基于 RBAC 的角色权限体系，集成 Spatie Permission + Filament Shield，Gate::before 超级管理员，部门数据权限 5 种范围</p>
                </div>

                <div class="p-6 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">菜单管理</h3>
                    <p class="text-gray-500 text-sm">数据库驱动的动态菜单，树形结构，AdminNavigationBuilder 按权限自动构建后台导航</p>
                </div>

                <div class="p-6 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">操作日志</h3>
                    <p class="text-gray-500 text-sm">全自动 Observer + ActivityLogger 审计，记录模型变更前后快照，登录日志独立追踪</p>
                </div>

                <div class="p-6 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">部门数据权限</h3>
                    <p class="text-gray-500 text-sm">树形部门结构，支持全部 / 本部门 / 本部门及下级 / 仅本人 / 指定部门五种数据权限范围</p>
                </div>

                <div class="p-6 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">插件市场</h3>
                    <p class="text-gray-500 text-sm">内置官方插件市场，支持第三方扩展安装与管理，PluginManager 统一生命周期调度</p>
                </div>

            </div>
        </div>
    </section>

    <!-- 第三块：安装指引 -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-3xl mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">快速开始</h2>
            <p class="text-gray-500 mb-8">在干净的 Laravel 13 项目中运行以下命令即可安装</p>

            <div class="bg-gray-900 rounded-xl p-6 text-left shadow-lg mb-6">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-gray-400 text-xs font-mono">终端</span>
                    <span class="text-gray-500 text-xs">Shell</span>
                </div>
                <pre class="text-green-400 font-mono text-sm overflow-x-auto"><code>composer require laravelstack/filament-admin</code></pre>
            </div>

            <div class="bg-gray-900 rounded-xl p-6 text-left shadow-lg mb-8">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-gray-400 text-xs font-mono">安装后初始化</span>
                    <span class="text-gray-500 text-xs">Shell</span>
                </div>
                <pre class="text-green-400 font-mono text-sm overflow-x-auto"><code>php artisan vendor:publish --tag=filament-admin-config
php artisan vendor:publish --tag=filament-admin-migrations
php artisan migrate
php artisan db:seed --class=FilamentAdmin\\Database\\Seeders\\SuperAdminSeeder</code></pre>
            </div>

            <p class="text-gray-400 text-sm">
                需要 PHP 8.3+、Laravel 13.x、Filament 5.x。详细安装文档见
                <a href="https://github.com/laravel-stack/filament-admin/wiki/installation" class="text-indigo-600 hover:underline">安装 Wiki</a>。
            </p>
        </div>
    </section>

    <!-- 第四块：演示站链接 -->
    <section class="py-16 bg-white">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">在线演示</h2>
            <p class="text-gray-500 mb-8">演示站已部署全部 v0.5.0 功能，可直接体验</p>

            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-100 rounded-2xl p-8 inline-block">
                <div class="text-lg font-semibold text-gray-800 mb-2">
                    <a href="https://demo.xitongapp.com" target="_blank" rel="noopener"
                       class="text-indigo-600 hover:underline text-2xl">
                        demo.xitongapp.com
                    </a>
                </div>
                <div class="text-gray-500 text-sm space-y-1">
                    <p>演示账号：<code class="bg-gray-100 px-2 py-0.5 rounded text-gray-700">demo@example.com</code></p>
                    <p>演示密码：<code class="bg-gray-100 px-2 py-0.5 rounded text-gray-700">demo123</code></p>
                    <p class="text-xs text-gray-400 mt-2">注意：演示站写操作已屏蔽，仅供浏览</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 页脚 -->
    <footer class="bg-gray-900 text-gray-400 py-8">
        <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between text-sm">
            <div class="mb-4 md:mb-0">
                <span class="font-semibold text-white">FilamentAdmin</span> — MIT License
            </div>
            <div class="flex items-center space-x-6">
                <a href="https://github.com/laravel-stack/filament-admin" class="hover:text-white transition">GitHub</a>
                <a href="https://packagist.org/packages/laravelstack/filament-admin" class="hover:text-white transition">Packagist</a>
                <a href="https://demo.xitongapp.com" class="hover:text-white transition">演示站</a>
                <a href="mailto:security@xitongapp.com" class="hover:text-white transition">安全报告</a>
            </div>
        </div>
    </footer>

</body>
</html>
