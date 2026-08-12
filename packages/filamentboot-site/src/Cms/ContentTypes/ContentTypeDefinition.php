<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes;

use Illuminate\Support\Str;

/**
 * 内容类型声明（批次 5，YZNCMS 式物理列）
 *
 * 纯数据对象：开发者写一份声明（字段清单 + 落点），交给
 * SyncContentTypesCommand 生成迁移/Model/Resource/Policy 四个真实文件，
 * 不是运行时无审查 DDL——生成之后是普通可编辑的 Laravel 代码，
 * 与本声明脱钩（改声明不会让已生成的文件跟着变，需要重新生成）。
 *
 * $module 决定生成代码落在 src/Modules/{$module}/ 下，与现有八类硬编码内容
 * （Modules/Corporate/Cases 等）同一目录规范，新内容类型不会自成一套结构。
 */
final class ContentTypeDefinition
{
    /**
     * @param  string  $key  内容类型唯一标识，snake_case 单数，如 'friend_link'
     * @param  string  $label  单数显示名，如 '友情链接'
     * @param  string  $pluralLabel  列表页标题用的显示名
     * @param  string  $table  数据库表名，如 'site_friend_links'
     * @param  string  $module  生成代码落点，相对 src/Modules/，如 'Corporate/FriendLinks'
     * @param  list<FieldDefinition>  $fields  字段清单
     * @param  bool  $sortable  是否含 sort 列并在后台列表启用拖拽排序
     * @param  bool  $softDeletes  是否启用软删除
     * @param  string  $navigationGroup  Filament 后台导航分组
     * @param  string  $navigationIcon  Filament 后台导航图标
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $pluralLabel,
        public readonly string $table,
        public readonly string $module,
        public readonly array $fields,
        public readonly bool $sortable = false,
        public readonly bool $softDeletes = false,
        public readonly string $navigationGroup = '官网管理',
        public readonly string $navigationIcon = 'heroicon-o-rectangle-stack',
    ) {}

    /**
     * Model 类名（PascalCase 单数），如 'FriendLink'
     */
    public function modelName(): string
    {
        return Str::studly($this->key);
    }

    /**
     * Model 复数类名，用于 List{Plural} Page 类名，如 'FriendLinks'
     */
    public function pluralModelName(): string
    {
        return Str::pluralStudly($this->modelName());
    }

    /**
     * 生成代码的基础命名空间，如 Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks
     */
    public function baseNamespace(): string
    {
        return 'Filamentboot\\FilamentbootSite\\Modules\\'.str_replace('/', '\\', $this->module);
    }

    public function modelClass(): string
    {
        return $this->baseNamespace().'\\Models\\'.$this->modelName();
    }

    public function resourceClass(): string
    {
        return $this->baseNamespace().'\\Filament\\'.$this->modelName().'Resource';
    }

    public function policyClass(): string
    {
        return $this->baseNamespace().'\\Policies\\'.$this->modelName().'Policy';
    }

    /**
     * 按字段 key 查找字段声明，未声明时返回 null
     */
    public function field(string $key): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }
}
