<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes;

use Filamentboot\FilamentbootSite\Cms\ContentTypes\AbstractFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldDefinition;

/**
 * 日期时间字段类型（批次 5）
 *
 * 统一用 TIMESTAMP 列 + DateTimePicker——广告位的生效时间窗（starts_at/
 * ends_at）与 SiteBanner 现有写法同形状，只到日期精度的场景按业务自己
 * 在展示层截断，不额外拆一个 DateOnly 类型。
 */
class DateFieldType extends AbstractFieldType
{
    public function key(): string
    {
        return 'date';
    }

    public function label(): string
    {
        return '日期时间';
    }

    public function migrationColumn(FieldDefinition $field): string
    {
        $stmt = "\$table->timestamp('{$field->key}')->nullable()";

        return $stmt.'->comment('.$this->quote($field->label).');';
    }

    public function modelCast(FieldDefinition $field): ?string
    {
        return $this->quote($field->key).' => '.$this->quote('datetime');
    }

    public function formComponentExpression(FieldDefinition $field): string
    {
        $expr = "DateTimePicker::make('{$field->key}')->label(".$this->quote($field->label).')';

        if ($field->required) {
            $expr .= '->required()';
        }

        return $expr;
    }

    public function formComponentImports(): array
    {
        return ['Filament\\Forms\\Components\\DateTimePicker'];
    }

    public function rules(FieldDefinition $field): array
    {
        return [$field->required ? 'required' : 'nullable', 'date'];
    }
}
