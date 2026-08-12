<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 图片字段类型（批次 5）
 *
 * 刻意不接入 Spatie Media Library：媒体库要求 Model implements HasMedia +
 * 逐字段 registerMediaCollections()，生成器要为「一个内容类型有几个图片字段」
 * 动态拼装这段注册代码，复杂度远超本字段类型的价值——本系统面向的验收场景
 * （友链 logo、广告位图片）都是单图，存磁盘相对路径这一列字符串已经够用。
 * 需要图集/多尺寸转换的内容类型仍应手写 Model（如现有 SiteBanner/SiteCase）。
 *
 * 固定使用 public 磁盘：与 UploadSettings::default_disk 的动态解析是运行时
 * 关注点，生成的文件不应该在生成那一刻就烧入一个可能会变的配置值。
 */
class ImageFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return '图片';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $stmt = "\$table->string('{$field->key}', 255)->nullable()";

        return $stmt.'->comment('.$this->quote($field->label).');';
    }

    public function formComponentExpression(FieldDefinition $field): string
    {
        $expr = "FileUpload::make('{$field->key}')->label(".$this->quote($field->label).")->image()->disk('public')";

        if ($field->required) {
            $expr .= '->required()';
        }

        return $expr;
    }

    public function formComponentImports(): array
    {
        return ['Filament\\Forms\\Components\\FileUpload'];
    }

    public function rules(FieldDefinition $field): array
    {
        return [$field->required ? 'required' : 'nullable', 'string'];
    }
}
