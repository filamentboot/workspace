<?php

namespace Filamentboot\FilamentbootSite\Services;

use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Illuminate\Support\Facades\Schema;

/**
 * 转化来源标识的中文名解析
 *
 * `site_contact_messages.source` 存的是 CTA 的入口标识。原来只有一张
 * config 里的**全等映射表**（`sources`），够用是因为当时来源都是页面类型级的
 * 十来个固定值。
 *
 * **记录级来源出现之后这个办法就不够了。** 套餐详情页用 `pkg-{slug}`，
 * 城市页用 `city-{区划代码}`——后者有三百多个值，逐条登记进 config 既
 * 不现实，也会在每次发布新城市时留下一个「忘了加就退化成英文」的坑。
 *
 * 所以补一层**前缀映射**（`source_prefixes`）：前缀命中就渲染成
 * 「前缀名 · 后缀」，后缀能查到真名的再翻译一道。查表顺序是
 * 全等 → 前缀 → 原样返回，三步都不命中也永远不会是空白。
 *
 * ## 为什么城市页的后缀是区划代码而不是 slug
 *
 * 因为它要在**历史数据**里长期成立。slug 是可以改的（改 URL、改拼音写法），
 * 改完之后库里那些 `city-wuhan` 就再也对不上任何一行区划；而区划代码不会变
 * ——`SiteRegion` 的类注释里那句「重新导入时 id 可能变、code 不会」说的就是
 * 这件事。代价是解析不出来时看到的是 `city-420100` 而非拼音，
 * 但那是异常路径，不该拿它去换正常路径的正确性。
 *
 * ## 区划名只查一次
 *
 * 后台列表一页 25 行，逐行去查一次区划表就是 25 条查询。
 * 本类**按单例注册**（见 SiteServiceProvider::register），
 * 区划名表在一次请求里最多加载一次，且只在真的出现城市来源时才加载。
 *
 * 有意**不进应用缓存**：区划表只在跑导入命令时变，而缓存要跟着失效就得
 * 在命令里记得清——那是一个必然会被忘掉的步骤，换来的只是省掉一次
 * 三百多行的窄查询。
 */
class ContactSourceLabel
{
    /**
     * 城市页来源前缀
     *
     * 前台在 `city/show.blade.php` 里拼这个前缀，本类在这里解析它。
     * 两边都引用这个常量，改的时候不会只改一边。
     */
    public const CITY_PREFIX = 'city-';

    /**
     * 区划代码 => 展示名，null 表示还没加载过
     *
     * @var array<string, string>|null
     */
    protected ?array $regionNames = null;

    /**
     * 解析一个来源标识的中文名
     *
     * 永远返回非空字符串：全等表、前缀表都不命中时原样返回，
     * 后台宁可显示 `some-new-cta` 也不能显示空白。
     */
    public function label(string $source): string
    {
        /** @var array<string, string> $exact */
        $exact = config('filamentboot-site.contact.sources', []);

        if (isset($exact[$source])) {
            return $exact[$source];
        }

        /** @var array<string, string> $prefixes */
        $prefixes = config('filamentboot-site.contact.source_prefixes', []);

        foreach ($prefixes as $prefix => $label) {
            $suffix = $this->suffixAfter($source, (string) $prefix);

            if ($suffix === null) {
                continue;
            }

            return $label.' · '.$this->translateSuffix((string) $prefix, $suffix);
        }

        return $source;
    }

    /**
     * 取出前缀之后的部分，不匹配或后缀为空时返回 null
     *
     * 后缀为空也当作不匹配：`city-` 这样一个光杆前缀渲染成「城市页 · 」
     * 比原样显示更难看懂。
     */
    protected function suffixAfter(string $source, string $prefix): ?string
    {
        if ($prefix === '' || ! str_starts_with($source, $prefix)) {
            return null;
        }

        $suffix = substr($source, strlen($prefix));

        return $suffix !== '' ? $suffix : null;
    }

    /**
     * 把后缀翻译成人看得懂的名字
     *
     * 目前只有城市页能翻译——它的后缀是区划代码，站上有一张全表可查。
     * 套餐那类后缀是 slug，逐条登记在 config 的全等表里更直接，
     * 走不到这里。
     */
    protected function translateSuffix(string $prefix, string $suffix): string
    {
        if ($prefix !== self::CITY_PREFIX) {
            return $suffix;
        }

        return $this->regionNames()[$suffix] ?? $suffix;
    }

    /**
     * 区划代码 => 展示名（省级与地级，县级不建页所以不需要）
     *
     * 表没迁移时返回空表：本类会被后台列表调用，而后台在迁移跑完之前
     * 也该打得开。
     *
     * @return array<string, string>
     */
    protected function regionNames(): array
    {
        if ($this->regionNames !== null) {
            return $this->regionNames;
        }

        if (! Schema::hasTable('site_regions')) {
            return $this->regionNames = [];
        }

        return $this->regionNames = SiteRegion::query()
            ->where('level', '<=', SiteRegion::LEVEL_CITY)
            ->get(['code', 'name', 'short_name'])
            ->mapWithKeys(fn (SiteRegion $region): array => [$region->code => $region->displayName()])
            ->all();
    }
}
