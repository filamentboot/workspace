<?php

namespace Filamentboot\FilamentbootSite\Console\Commands;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\ContentTypeDefinition;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\ContentTypeRegistry;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypeRegistry;
use Filamentboot\FilamentbootSite\Services\ContentTypeStubGenerator;
use Illuminate\Console\Command;

/**
 * 可配置内容类型代码生成命令（批次 5，YZNCMS 式物理列）
 *
 * 读 ContentTypeRegistry 里已注册的声明，生成迁移/Model/Resource（含 3 个
 * Page）/Policy 五个真实文件，供开发者 review 后 `php artisan migrate`——
 * 不做运行时无审查 DDL，生成后的文件与声明脱钩，独立存在。
 *
 * 默认跳过已存在的文件（避免覆盖开发者生成后做过的手工修改），
 * --force 才会覆盖，与 filamentboot 主包四个 make:* 命令的 D-02 约定一致。
 */
class SyncContentTypesCommand extends Command
{
    protected $signature = 'filamentboot-site:content-type:sync
        {key? : 内容类型 key，缺省时同步全部已注册内容类型}
        {--force : 覆盖已存在的文件}';

    protected $description = '按 ContentTypeDefinition 声明生成迁移/Model/Resource/Policy';

    public function __construct(
        protected ContentTypeRegistry $contentTypes,
        protected FieldTypeRegistry $fieldTypes,
        protected ContentTypeStubGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string|null $key */
        $key = $this->argument('key');

        if ($key !== null) {
            $definition = $this->contentTypes->get($key);

            if ($definition === null) {
                $this->components->error("内容类型「{$key}」未注册。已注册：".implode('、', $this->contentTypes->keys()));

                return self::FAILURE;
            }

            $definitions = [$definition];
        } else {
            $definitions = array_values($this->contentTypes->all());
        }

        if ($definitions === []) {
            $this->components->warn('没有已注册的内容类型，无事可做。');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        foreach ($definitions as $definition) {
            $this->syncOne($definition, $force);
        }

        return self::SUCCESS;
    }

    private function syncOne(ContentTypeDefinition $definition, bool $force): void
    {
        $this->components->info("同步内容类型「{$definition->key}」（{$definition->label}）");

        $this->writeGenerated(
            $this->generator->migrationPath($definition),
            $this->generator->buildMigrationContent($definition, $this->fieldTypes),
            $force,
        );

        $this->writeGenerated(
            $this->generator->modelPath($definition),
            $this->generator->buildModelContent($definition, $this->fieldTypes),
            $force,
        );

        $this->writeGenerated(
            $this->generator->resourcePath($definition),
            $this->generator->buildResourceContent($definition, $this->fieldTypes),
            $force,
        );

        $pagesDir = $this->generator->resourcePagesDirectory($definition);
        $plural   = $definition->pluralModelName();
        $model    = $definition->modelName();

        $this->writeGenerated($pagesDir."/List{$plural}.php", $this->generator->buildListPageContent($definition), $force);
        $this->writeGenerated($pagesDir."/Create{$model}.php", $this->generator->buildCreatePageContent($definition), $force);
        $this->writeGenerated($pagesDir."/Edit{$model}.php", $this->generator->buildEditPageContent($definition), $force);

        $this->writeGenerated(
            $this->generator->policyPath($definition),
            $this->generator->buildPolicyContent($definition),
            $force,
        );
    }

    private function writeGenerated(string $path, string $content, bool $force): void
    {
        $written  = $this->generator->writeFile($path, $content, $force);
        $relative = str_replace($this->generator->packageRoot().'/', '', $path);

        $this->components->twoColumnDetail($relative, $written ? '<fg=green>已生成</>' : '<fg=gray>已存在，跳过</>');
    }
}
