<?php

namespace Filamentboot\FilamentbootSite\Cms\ContentTypes;

/**
 * 字段类型契约（批次 5，YZNCMS 式物理列）
 *
 * 与 Cms\Blocks\BlockContract 同一设计：一个字段类型 = 迁移列定义 + Model
 * cast + 后台表单组件 + 校验规则 + 前台展示局部视图，四类消费方（生成器/Model/
 * Resource/前台渲染）都只认 FieldTypeRegistry + 本契约，不出现按 type 字符串
 * 分支的 switch-case。新增一种字段类型只需要新增一个实现类并注册，不用回头
 * 改生成器或渲染器。
 *
 * migrationColumn()/formComponentExpression() 返回的是**PHP 源码字符串**而不是
 * 运行时对象——这两处消费方是 SyncContentTypesCommand 生成的迁移/Resource 文件，
 * 生成的文件是一次性快照，之后独立存在、可被开发者手改，不会因为字段类型的
 * 实现变了而让已生成的文件跟着改变行为（迁移尤其不能这样：迁移是历史记录，
 * 必须在生成的那一刻起就冻结）。renderView() 则是真正的运行时接口，供前台
 * 通用渲染器按字段类型分发展示局部，这一侧允许字段类型的展示逻辑持续演进。
 */
interface FieldTypeContract
{
    /**
     * 字段类型唯一标识
     *
     * 对应 ContentTypes 声明里 FieldDefinition::$type，只允许小写字母、数字与连字符。
     */
    public function key(): string;

    /**
     * 后台/生成器提示用的显示名称
     */
    public function label(): string;

    /**
     * 生成迁移文件用：本字段对应的 Blueprint 列定义语句（含末尾分号）
     *
     * 例如 "$table->string('title', 255)->nullable()->comment('标题');"。
     * 由 SyncContentTypesCommand 原样嵌入迁移文件的 up()，字符串里的字段名/
     * 长度/默认值均已由实现类从 $field 取值拼好，生成器不再解析。
     */
    public function migrationColumn(FieldDefinition $field): string;

    /**
     * 生成 Model 用：casts() 数组的一个条目（不含末尾逗号），不需要 cast 时返回 null
     *
     * 例如 "'is_enabled' => 'boolean'"。
     */
    public function modelCast(FieldDefinition $field): ?string;

    /**
     * 生成 Resource 表单用：Filament 表单组件构造表达式（不含末尾逗号）
     *
     * 例如 "TextInput::make('title')->label('标题')->required()->maxLength(255)"。
     */
    public function formComponentExpression(FieldDefinition $field): string;

    /**
     * formComponentExpression() 用到的完全限定类名列表，供生成器拼 use 语句
     *
     * @return list<class-string>
     */
    public function formComponentImports(): array;

    /**
     * Laravel 校验规则
     *
     * @return list<string>
     */
    public function rules(FieldDefinition $field): array;

    /**
     * 前台展示局部视图名（相对 filamentboot-site::cms.fields. 前缀的 key）
     *
     * 通用渲染器按此解析 `filamentboot-site::cms.fields.{renderView()}`，
     * 视图缺失时渲染器跳过并记日志（同 BlockRenderer 的降级方式），不抛异常。
     */
    public function renderView(): string;
}
