<?php

namespace Filamentboot\FilamentbootSite\Services;

use Filamentboot\FilamentbootSite\Settings\SiteSettings;

/**
 * 站点发布前健康检查
 *
 * 检查官网对外发布所必需、但缺失时不会报错只会「静默少一块」的配置项：
 * 联系方式、备案、隐私链接、默认 SEO、OG 图。
 *
 * 线上审查发现页脚渲染出「联系我们」标题却没有任何联系方式、
 * 列表页 meta description 为空、og:image 指向 404，
 * 根因都是这些项从未配置而后台也不告警。本服务把它们变成可见的待办。
 */
class SiteHealthCheck
{
    /**
     * 必填项定义：设置字段 => 人类可读名称
     *
     * @var array<string, string>
     */
    protected const REQUIRED_FIELDS = [
        'phone'                      => '联系电话',
        'address_zh'                 => '公司地址',
        'icp_number'                 => 'ICP 备案号',
        'privacy_url'                => '隐私政策链接',
        'seo_default_title_zh'       => '默认页面标题',
        'seo_default_description_zh' => '默认页面描述',
        'og_default_image'           => '默认 Open Graph 图',
        'logo'                       => '公司 LOGO',
    ];

    /**
     * 返回缺失的配置项
     *
     * settings 表未迁移时返回空数组，不在后台抛异常。
     *
     * @return array<string, string> 字段名 => 人类可读名称
     */
    public function missing(): array
    {
        $settings = $this->settings();

        if ($settings === null) {
            return [];
        }

        // 明确决定不填的字段不计入缺失，见 config 的 health.optional_fields 那段注释。
        $optional = (array) config('filamentboot-site.health.optional_fields', []);

        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field => $label) {
            if (in_array($field, $optional, true)) {
                continue;
            }

            $value = $settings->{$field} ?? null;

            if ($value === null || trim((string) $value) === '') {
                $missing[$field] = $label;
            }
        }

        return $missing;
    }

    /**
     * 站点配置是否完整
     */
    public function passes(): bool
    {
        return $this->missing() === [];
    }

    /**
     * 缺失项数量
     */
    public function missingCount(): int
    {
        return count($this->missing());
    }

    /**
     * 供后台展示的一句话摘要
     */
    public function summary(): string
    {
        $missing = $this->missing();

        if ($missing === []) {
            return '站点信息已配置完整，可以对外发布。';
        }

        return '发布前还需补齐 '.count($missing).' 项：'.implode('、', $missing);
    }

    /**
     * 解析设置实例（settings 表未迁移时返回 null）
     */
    protected function settings(): ?SiteSettings
    {
        try {
            return app(SiteSettings::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
