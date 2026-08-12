<?php

namespace Filamentboot\FilamentbootSite\Cms\Revisions;

use Filamentboot\FilamentbootSite\Cms\Models\SiteRevision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Revisionable 接口里两个通用方法的默认实现（批次 1.5c）
 *
 * revisions()/revisionPayload() 对全部内容类型逻辑完全一致（多态关联、
 * 枚举与日期转标量），真正因类型而异的只有字段清单——那三个静态方法
 * 仍需宿主模型自己声明（见 Revisionable 接口）。
 */
trait HasRevisions
{
    /**
     * @return MorphMany<SiteRevision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(SiteRevision::class, 'revisionable')->latest('id');
    }

    /**
     * status 存 enum 的标量值、日期存格式化字符串：payload 是 JSON，
     * 存对象实例会在反序列化后变成字符串，两次读出来类型不一致。
     *
     * @return array<string, mixed>
     */
    public function revisionPayload(): array
    {
        $payload = [];

        foreach (static::revisionTrackedFields() as $field) {
            $value = $this->getAttribute($field);

            $payload[$field] = match (true) {
                $value instanceof \BackedEnum        => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default                              => $value,
            };
        }

        return $payload;
    }
}
