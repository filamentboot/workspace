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
     * 上传字段使用的磁盘（供前台视图解析图片 URL，#13）
     *
     * 视图拿到的 image 字段只是磁盘内的相对路径，必须知道磁盘名才能
     * Storage::disk($disk)->url()。表单侧与渲染侧走同一个方法，
     * 后台改了默认磁盘不会出现「上传到 A、前台从 B 读」的错位。
     */
    public function disk(): string
    {
        return $this->defaultDisk();
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
     * 结构化数据默认实现：不产出任何节点
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function structuredData(array $data): ?array
    {
        return null;
    }

    /**
     * 净化默认实现：原样返回，不做任何改写
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        return $data;
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
