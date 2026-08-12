<?php

namespace Filamentboot\FilamentbootSite\Console\Commands;

use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Illuminate\Console\Command;

/**
 * 导入行政区划
 *
 * **包里不带区划数据，路径由宿主给。** 区划是站点数据不是包的行为：
 * 装这个包的可能是个只做一个省的公司，也可能压根不在中国。
 *
 * ## 输入格式：嵌套表示从属，`level` 显式声明层级
 *
 * ```json
 * [
 *   {"code": "420000", "level": 1, "name": "湖北省", "short_name": "湖北", "slug": "hubei",
 *    "children": [
 *      {"code": "420100", "level": 2, "name": "武汉市", "short_name": "武汉", "slug": "wuhan",
 *       "children": [{"code": "420102", "level": 3, "name": "江岸区"}]},
 *      {"code": "429004", "level": 3, "name": "仙桃市"}
 *    ]}
 * ]
 * ```
 *
 * ⚠️ **`level` 必须写出来，不能用嵌套深度推。** 上面最后那条就是原因：仙桃是
 * 省直辖县级市，直接挂在湖北省下——深度是 2，层级却是 3。直辖市更彻底：
 * 北京的 16 个区全部直接挂在北京市（省级）下。**「谁的下级」和「第几级」
 * 在中国的行政区划里本来就不是一回事**，硬用深度表达就要造一堆占位节点。
 *
 * 约束只有一条：子节点的 level 必须大于父节点的。
 *
 * 第 1、2 级必须有 slug（它们要出现在 URL 里），第 3 级不要求——它不建页。
 *
 * ## 命令不做拼音转换，slug 必须随数据给
 *
 * 这不是省事，是因为**自动转一定错**：`陕西` 与 `山西` 的拼音都是 shanxi，
 * 机器分不开；地名多音字（长治 / 朝阳 / 漯河 / 昌都 / 阿勒泰…）主流拼音库
 * 也读不对。slug 是 URL 的一段，**改一次就是一条死链**，属于要人工确认的数据。
 *
 * ## 幂等，而且不删任何东西
 *
 * 按 `code` upsert，重复跑没有副作用。**文件里没有、库里有的记录一条都不删**——
 * 区划撤并时那些记录底下很可能挂着城市页，删掉就是把内容一起带走。
 * 命令只把它们**报出来**，删不删是人的决定。
 *
 * `sort` 同理：只在新建时写 0，更新时不碰。那是留给人工插队的列。
 */
class ImportRegionsCommand extends Command
{
    /** @var string */
    protected $signature = 'filamentboot-site:import-regions
                            {--file= : 区划 JSON 文件路径（三层嵌套，见类注释）}
                            {--dry-run : 只校验并报告，不写库}';

    /** @var string */
    protected $description = '从 JSON 文件导入行政区划（省 / 地 / 县三级）';

    /** 单次 upsert 的行数 */
    protected const CHUNK = 500;

    /**
     * 校验中收集到的问题，非空则整个导入不执行
     *
     * @var list<string>
     */
    protected array $errors = [];

    /**
     * 扁平化后的待写入行，键为 code
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $rows = [];

    /**
     * (parent_code, slug) 去重表，值为已占用该组合的 code
     *
     * @var array<string, string>
     */
    protected array $slugSeen = [];

    public function handle(): int
    {
        // ⚠️ 必须先清空：Artisan 命令在容器里是**单例**，同一个进程里跑第二次
        // 拿到的是同一个实例。不清空的话第二次会把第一次的 rows 当成「重复代码」，
        // 报一堆「出现了不止一次」然后整个失败——而这个命令天生就是要重复跑的。
        $this->errors   = [];
        $this->rows     = [];
        $this->slugSeen = [];

        $path = (string) $this->option('file');

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            $this->error('--file 必须指向一个可读的 JSON 文件。');

            return self::FAILURE;
        }

        $raw = json_decode((string) file_get_contents($path), true);

        if (! is_array($raw)) {
            $this->error('文件不是合法 JSON，或顶层不是数组。');

            return self::FAILURE;
        }

        foreach ($raw as $node) {
            $this->collect($node, 0, '');
        }

        if ($this->errors !== []) {
            $this->error(sprintf('校验未通过，共 %d 个问题，一条都没写库：', count($this->errors)));

            foreach (array_slice($this->errors, 0, 20) as $message) {
                $this->line('  '.$message);
            }

            if (count($this->errors) > 20) {
                $this->line(sprintf('  …另有 %d 个', count($this->errors) - 20));
            }

            return self::FAILURE;
        }

        $this->reportCounts();

        if ($this->option('dry-run')) {
            $this->info('试运行，未写库。');

            return self::SUCCESS;
        }

        $this->write();
        $this->reportLeftovers();

        return self::SUCCESS;
    }

    /**
     * 递归收集一个节点及其子节点
     *
     * @param  mixed  $node  待校验的节点，形状不保证
     * @param  int  $parentLevel  上级层级，顶层传 0
     */
    protected function collect(mixed $node, int $parentLevel, string $parentCode): void
    {
        if (! is_array($node)) {
            $this->errors[] = '有一个节点不是对象。';

            return;
        }

        $code      = trim((string) ($node['code'] ?? ''));
        $name      = trim((string) ($node['name'] ?? ''));
        $shortName = trim((string) ($node['short_name'] ?? ''));
        $slug      = trim((string) ($node['slug'] ?? ''));
        $level     = (int) ($node['level'] ?? 0);

        if (preg_match('/^\d{6}$/', $code) !== 1) {
            $this->errors[] = sprintf('代码 %s 不是 6 位数字。', $code !== '' ? $code : '(空)');

            return;
        }

        if ($name === '') {
            $this->errors[] = sprintf('%s 没有名称。', $code);

            return;
        }

        if (isset($this->rows[$code])) {
            $this->errors[] = sprintf('代码 %s 出现了不止一次。', $code);

            return;
        }

        if (! in_array($level, [SiteRegion::LEVEL_PROVINCE, SiteRegion::LEVEL_CITY, SiteRegion::LEVEL_COUNTY], true)) {
            $this->errors[] = sprintf('%s（%s）的 level 必须是 1 / 2 / 3。', $code, $name);

            return;
        }

        // 唯一的层级约束：比上级低就行，不要求正好差一级。
        // 省直辖县级市（level 3 挂在 level 1 下）与直辖市的区都属于这种情况
        if ($level <= $parentLevel) {
            $this->errors[] = sprintf('%s（%s）是第 %d 级，不能挂在第 %d 级下面。', $code, $name, $level, $parentLevel);

            return;
        }

        if ($slug !== '' && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1) {
            $this->errors[] = sprintf('%s（%s）的 slug「%s」含非法字符，只允许小写字母、数字与连字符。', $code, $name, $slug);

            return;
        }

        // 省级与地级要出现在 URL 里，没有 slug 就永远访问不到
        if ($slug === '' && $level !== SiteRegion::LEVEL_COUNTY) {
            $this->errors[] = sprintf('%s（%s）是第 %d 级，必须有 slug。', $code, $name, $level);

            return;
        }

        if ($slug !== '') {
            $pair = $parentCode.'/'.$slug;

            if (isset($this->slugSeen[$pair])) {
                $this->errors[] = sprintf(
                    '%s（%s）与 %s 在同一个上级下用了同一个 slug「%s」。',
                    $code,
                    $name,
                    $this->slugSeen[$pair],
                    $slug
                );

                return;
            }

            $this->slugSeen[$pair] = $code;
        }

        $this->rows[$code] = [
            'code'        => $code,
            'parent_code' => $parentCode,
            'level'       => $level,
            'name'        => $name,
            'short_name'  => $shortName !== '' ? $shortName : null,
            'slug'        => $slug !== '' ? $slug : null,
            'sort'        => 0,
        ];

        $children = $node['children'] ?? [];

        if ($children === [] || ! is_array($children)) {
            return;
        }

        foreach ($children as $child) {
            $this->collect($child, $level, $code);
        }
    }

    /**
     * 报告本次文件里的层级构成
     */
    protected function reportCounts(): void
    {
        $byLevel = [];

        foreach ($this->rows as $row) {
            $level           = (int) $row['level'];
            $byLevel[$level] = ($byLevel[$level] ?? 0) + 1;
        }

        $this->info(sprintf(
            '文件解析完成：省级 %d、地级 %d、县级 %d，合计 %d。',
            $byLevel[SiteRegion::LEVEL_PROVINCE] ?? 0,
            $byLevel[SiteRegion::LEVEL_CITY] ?? 0,
            $byLevel[SiteRegion::LEVEL_COUNTY] ?? 0,
            count($this->rows)
        ));
    }

    /**
     * 分批 upsert
     *
     * 更新列里**没有 sort**：那是留给人工插队的，重新导入不该把它推平。
     * `created_at` 同理由 Eloquent 排除在更新之外。
     */
    protected function write(): void
    {
        $existing = SiteRegion::query()->pluck('code')->flip();

        $created = 0;

        foreach ($this->rows as $code => $row) {
            if (! $existing->has($code)) {
                $created++;
            }
        }

        foreach (array_chunk(array_values($this->rows), self::CHUNK) as $chunk) {
            SiteRegion::query()->upsert(
                $chunk,
                ['code'],
                ['parent_code', 'level', 'name', 'short_name', 'slug']
            );
        }

        $this->info(sprintf('写库完成：新增 %d、更新 %d。', $created, count($this->rows) - $created));
    }

    /**
     * 报告文件里没有、但库里还在的记录
     *
     * **一条都不删**（见类注释），只把风险摆出来。挂着城市页的那些单独列，
     * 因为它们意味着有页面会拼不出 URL。
     */
    protected function reportLeftovers(): void
    {
        $stale = SiteRegion::query()
            ->whereNotIn('code', array_keys($this->rows))
            ->ordered()
            ->get(['code', 'name', 'level']);

        if ($stale->isEmpty()) {
            return;
        }

        $this->warn(sprintf('库里有 %d 条本次文件里没有的区划，**没有删除**：', $stale->count()));

        foreach ($stale->take(10) as $region) {
            $this->line(sprintf('  %s %s（第 %d 级）', $region->code, $region->name, $region->level));
        }

        if ($stale->count() > 10) {
            $this->line(sprintf('  …另有 %d 条', $stale->count() - 10));
        }

        $orphanPages = SiteCityPage::query()
            ->whereIn('region_code', $stale->pluck('code')->all())
            ->count();

        if ($orphanPages > 0) {
            $this->error(sprintf(
                '其中 %d 条底下挂着城市页——这些页面拼不出 URL，会从列表与站点地图里消失。请人工处理。',
                $orphanPages
            ));
        }
    }
}
