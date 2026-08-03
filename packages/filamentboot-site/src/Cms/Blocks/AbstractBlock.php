<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filamentboot\Settings\UploadSettings;
use Illuminate\Support\Facades\Validator;

/**
 * 区块基类（#12）
 *
 * 吃掉各区块重复的默认实现：视图名按 key 推导、payload 校验与默认值填充。
 * 具体区块只需声明 key / label / schema / rules / defaults。
 */
abstract class AbstractBlock implements BlockContract
{
    /**
     * 前台渲染视图名（默认按 key 推导）
     *
     * 具体视图文件属于 #13（区块前台渲染），本阶段只定义契约。
     */
    public function view(): string
    {
        return 'filamentboot-site::blocks.'.$this->key();
    }

    /**
     * payload 校验规则（默认无规则）
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * payload 默认值（默认无字段）
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    /**
     * 上传字段使用的磁盘
     *
     * 与 SiteSettingsPage 同源读 UploadSettings.default_disk，
     * settings 表未迁移时降级到 public（T-10-01-02 防护）。
     */
    protected function defaultDisk(): string
    {
        return rescue(
            fn (): string => app(UploadSettings::class)->default_disk,
            'public',
            report: false,
        );
    }

    /**
     * 校验一段 payload 是否符合本区块规则
     *
     * @param  array<string, mixed>  $data  区块 data 部分
     * @return array<string, list<string>> 字段 => 错误消息列表，空数组表示通过
     */
    public function validate(array $data): array
    {
        $rules = $this->rules();

        if ($rules === []) {
            return [];
        }

        return Validator::make($data, $rules)->errors()->toArray();
    }

    /**
     * 用默认值补齐 payload 缺失的字段
     *
     * 历史 payload 在区块新增字段后会缺键，渲染层不该到处写 ?? 兜底。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withDefaults(array $data): array
    {
        return array_replace_recursive($this->defaults(), $data);
    }
}
