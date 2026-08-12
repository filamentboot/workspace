<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 富文本字段类型（批次 5）
 *
 * 存 LONGTEXT 列的原始 HTML，表单用 RichEditor。⚠️ 与 Cms\Blocks\RichContentBlock
 * 同理，这是本系统唯一允许落地 HTML 的字段类型——生成的 Model 需要在保存前
 * 净化，SyncContentTypesCommand 为含此字段类型的内容类型生成的 Model 会带上
 * RichText::purify() 调用（见生成器实现），字段类型本身不做净化（字段类型
 * 只描述列/表单/展示，不持有 Eloquent 生命周期钩子）。
 */
class RichTextFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'rich-text';
    }

    public function label(): string
    {
        return '富文本';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $stmt = "\$table->longText('{$field->key}')";

        if (! $field->required) {
            $stmt .= '->nullable()';
        }

        return $stmt.'->comment('.$this->quote($field->label).');';
    }

    public function formComponentExpression(FieldDefinition $field): string
    {
        $expr = "RichEditor::make('{$field->key}')->label(".$this->quote($field->label).')';

        if ($field->required) {
            $expr .= '->required()';
        }

        return $expr;
    }

    public function formComponentImports(): array
    {
        return ['Filament\\Forms\\Components\\RichEditor'];
    }

    public function rules(FieldDefinition $field): array
    {
        return [$field->required ? 'required' : 'nullable', 'string'];
    }
}
