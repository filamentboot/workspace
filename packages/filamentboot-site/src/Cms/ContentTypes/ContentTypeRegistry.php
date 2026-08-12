<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes;

use InvalidArgumentException;

/**
 * 内容类型注册表（批次 5）
 *
 * 与 FieldTypeRegistry / Cms\Blocks\BlockRegistry 同一设计：查找表 + 白名单。
 * SyncContentTypesCommand 与前台通用渲染器都只认已注册的 ContentTypeDefinition，
 * 未注册的 key 一律视为不存在（返回 null，不抛异常，理由同 BlockRegistry::get()）。
 *
 * 在 SiteServiceProvider 中注册为容器单例，宿主/包在自己的 ServiceProvider 里
 * 追加声明：app(ContentTypeRegistry::class)->register(new ContentTypeDefinition(...))。
 */
class ContentTypeRegistry
{
    /**
     * 已注册内容类型，键为 ContentTypeDefinition::$key
     *
     * @var array<string, ContentTypeDefinition>
     */
    protected array $definitions = [];

    /**
     * 注册一个内容类型声明
     *
     * key 必须是小写字母/数字/下划线（snake_case，与数据表/类名派生规则匹配），
     * 与 BlockRegistry/FieldTypeRegistry 的 kebab-case 校验刻意不同——那两者的
     * key 会直接出现在 URL/视图名里，这里的 key 只用来派生表名与类名。
     *
     * @throws InvalidArgumentException key 非法或重复注册
     */
    public function register(ContentTypeDefinition $definition): void
    {
        $key = $definition->key;

        if (preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $key) !== 1) {
            throw new InvalidArgumentException("内容类型 key「{$key}」非法，只允许小写字母、数字与下划线。");
        }

        if (isset($this->definitions[$key])) {
            throw new InvalidArgumentException("内容类型 key「{$key}」已被注册，不能重复注册。");
        }

        $this->definitions[$key] = $definition;
    }

    /**
     * 批量注册
     *
     * @param  iterable<ContentTypeDefinition>  $definitions
     */
    public function registerMany(iterable $definitions): void
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    /**
     * key 是否已注册
     */
    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * 按 key 取内容类型声明，未注册时返回 null
     */
    public function get(string $key): ?ContentTypeDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /**
     * 全部已注册内容类型声明
     *
     * @return array<string, ContentTypeDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * 全部已注册 key
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->definitions);
    }
}
