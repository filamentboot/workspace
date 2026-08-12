<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes;

use InvalidArgumentException;

/**
 * 字段类型注册表（批次 5）
 *
 * 与 Cms\Blocks\BlockRegistry 同一设计：既是查找表也是白名单——
 * ContentTypeDefinition 里的 FieldDefinition::$type 只有在此注册过，
 * SyncContentTypesCommand 才会为它生成代码，前台渲染器才会渲染它。
 *
 * 在 SiteServiceProvider 中注册为容器单例，宿主可在自己的 ServiceProvider 里
 * 追加自定义字段类型：app(FieldTypeRegistry::class)->register(new MyFieldType)。
 */
class FieldTypeRegistry
{
    /**
     * 已注册字段类型，键为字段类型 key
     *
     * @var array<string, FieldTypeContract>
     */
    protected array $types = [];

    /**
     * 注册一个字段类型
     *
     * key 必须是小写字母/数字/连字符，与 BlockRegistry::register() 同一约束。
     *
     * @throws InvalidArgumentException key 非法或重复注册
     */
    public function register(FieldTypeContract $type): void
    {
        $key = $type->key();

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key) !== 1) {
            throw new InvalidArgumentException("字段类型 key「{$key}」非法，只允许小写字母、数字与连字符。");
        }

        if (isset($this->types[$key])) {
            throw new InvalidArgumentException("字段类型 key「{$key}」已被注册，不能重复注册。");
        }

        $this->types[$key] = $type;
    }

    /**
     * 批量注册
     *
     * @param  iterable<FieldTypeContract>  $types
     */
    public function registerMany(iterable $types): void
    {
        foreach ($types as $type) {
            $this->register($type);
        }
    }

    /**
     * key 是否已注册
     */
    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * 按 key 取字段类型，未注册时返回 null
     *
     * 返回 null 而不抛异常：前台渲染器遇到历史遗留的未知字段类型应当跳过并
     * 记日志，不能让一个失效字段类型把整页打成 500（同 BlockRegistry::get()）。
     */
    public function get(string $key): ?FieldTypeContract
    {
        return $this->types[$key] ?? null;
    }

    /**
     * 全部已注册字段类型
     *
     * @return array<string, FieldTypeContract>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * 全部已注册 key
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    /**
     * 生成器/后台下拉用的 key => label 映射
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->types as $key => $type) {
            $options[$key] = $type->label();
        }

        return $options;
    }
}
