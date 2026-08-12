<?php

namespace Filamentboot\Filament\Resources\Plugins\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\Filament\Resources\Plugins\PluginResource;
use Illuminate\Support\Facades\Artisan;

/**
 * 已安装插件列表页
 *
 * 提供「扫描已安装插件」按钮，调用 plugin:scan 命令同步 vendor 目录。
 */
class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('扫描已安装插件')
                ->action(function (): void {
                    Artisan::call('plugin:scan');
                    Notification::make()
                        ->title('扫描完成')
                        ->success()
                        ->send();
                })
                // 退出面板级 databaseTransactions()：Artisan::call('plugin:scan') 属外部副作用
                ->databaseTransaction(false)
                ->requiresConfirmation()
                ->authorize('update_plugin'),
        ];
    }
}
