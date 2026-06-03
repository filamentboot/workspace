<?php

namespace FilamentAdmin\Commands;

use Illuminate\Console\Command;

/**
 * 发布扩展 stub 命令
 *
 * 用户通过此命令生成继承类 stub，用于自定义包内默认行为。
 */
class PublishCommand extends Command
{
    protected $signature = 'filament-admin:publish
                            {--model= : 发布指定模型的 stub}
                            {--resource= : 发布指定 Resource 的 stub}
                            {--all : 发布所有 stub}';

    protected $description = '发布 FilamentAdmin 扩展 stub 到用户项目';

    public function handle(): int
    {
        $this->info('FilamentAdmin stub 发布功能待实现。');
        $this->info('当前版本所有扩展类均使用包内默认实现。');

        return self::SUCCESS;
    }
}
