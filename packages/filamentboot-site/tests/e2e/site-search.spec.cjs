// @ts-check
/**
 * 站内搜索回归（3.5 期 F 段）
 *
 * 三期新增，此前只confirm 过路由可达（curl 200），从没在真实浏览器里点过。
 * 两条路径都要锁：有结果时分组渲染，无结果时给「让顾问帮我找」这个询盘出口
 * ——`SiteFrontController::search()` 的类注释写明了这条兜底的存在意义
 * （零结果的词本身就是内容缺口，不能让访客走到死胡同）。
 *
 * 「智能」这个词命中案例/方案/产品/套餐/资讯/页面六类demo 内容里的大多数——
 * 选它是因为只要 demo 种子还在就稳定命中，不依赖某一条具体记录的标题。
 *
 * ## 针对官方 demo 数据的默认版
 *
 * 「智能」是 decoration 主题演示内容（智能家居装修）的高频词，software 主题
 * 演示内容（企业软件/工作流）几乎不含这个词——跑在 software 主题上时把
 * `q=智能` 换成该主题的高频词（如「自动化」「数据打通」）才能命中结果，
 * 否则「有结果时…」这条会因为查到 0 条而失败（这不是缺陷，是这条断言的前提
 * 本来就依赖具体的 demo 内容）。
 *
 * 运行：
 *   php artisan serve --port=8124
 *   npx playwright test site-search --config=playwright.config.site.cjs
 */
const { test, expect } = require('@playwright/test');

test.describe('站内搜索', () => {
    test('有结果时按类型分组展示，可点进详情', async ({ page }) => {
        const errors = [];
        page.on('pageerror', (e) => errors.push(String(e)));
        page.on('console', (msg) => {
            if (msg.type() === 'error') errors.push(msg.text());
        });

        await page.goto('/search?q=智能');

        await expect(page.locator('#site-search-input')).toHaveValue('智能');
        await expect(page.getByText(/找到 \d+ 条与「智能」相关的内容/)).toBeVisible();

        // 至少一个分组标题、至少一条命中，且命中是可点的详情链接
        const firstHit = page.locator('main a:has(h3)').first();
        await expect(firstHit).toBeVisible();
        await expect(firstHit).toHaveAttribute('href', /^https?:\/\//);

        expect(errors, `控制台/页面报错：${errors.join('; ')}`).toEqual([]);
    });

    test('无结果时给询盘出口而不是死胡同', async ({ page }) => {
        await page.goto('/search?q=zzzznonexistentqueryxyz');

        await expect(page.getByText('没有找到与「zzzznonexistentqueryxyz」相关的内容')).toBeVisible();

        const cta = page.locator('[data-contact-trigger="search-empty"]');
        await expect(cta).toBeVisible();

        await cta.click();

        const panel = page.locator('#contact-panel');
        await expect(panel).toBeVisible({ timeout: 5000 });
        await expect(panel).toHaveAttribute('aria-modal', 'true');
    });

    test('空词提示输入，不报错也不当无结果处理', async ({ page }) => {
        const errors = [];
        page.on('pageerror', (e) => errors.push(String(e)));

        await page.goto('/search');

        await expect(page.getByText('输入关键词开始搜索。')).toBeVisible();
        // 空词不该触发「没有找到」这条空结果文案——两种空态含义不同
        await expect(page.getByText(/没有找到与/)).toHaveCount(0);

        expect(errors).toEqual([]);
    });
});
