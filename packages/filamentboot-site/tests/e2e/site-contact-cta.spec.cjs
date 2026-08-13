// @ts-check
/**
 * 官网咨询 CTA 回归用例
 *
 * 线上 P0：桌面导航与移动菜单的「预约咨询」按钮只 remove 一个不存在的 hidden class，
 * 而询盘面板由 floating-contact 自己的 Alpine 作用域控制，点击后毫无反应。
 * 修复后所有 CTA 统一调用 $store.contactPanel.show()，本文件逐个入口回归。
 *
 * 2026-08-08（3.5 期）修了一批**用例自己过时**导致的常红：首页首屏版式在二期 B1
 * 由 hero 换成 banner-hero（trigger 名跟着变），移动端固定 CTA 在 2.5 期由悬浮气泡
 * 换成底部操作条（气泡改成 `hidden sm:inline-flex`）。用例没跟着改，于是 5 条一直红。
 * **常红的用例等于没有用例**——它训练人忽略红色，下次真出问题也看不见。
 *
 * 运行：
 *   php artisan serve --port=8123
 *   npx playwright test site-contact-cta --config=playwright.config.site.cjs
 */
const { test, expect } = require('@playwright/test');

/** 询盘面板选择器 */
const PANEL = '#contact-panel';

/**
 * 断言询盘面板已打开
 *
 * @param {import('@playwright/test').Page} page
 */
async function expectPanelOpen(page) {
    const panel = page.locator(PANEL);
    await expect(panel).toBeVisible({ timeout: 5000 });
    await expect(panel).toHaveAttribute('aria-modal', 'true');
}

/**
 * 断言询盘面板处于关闭状态
 *
 * @param {import('@playwright/test').Page} page
 */
async function expectPanelClosed(page) {
    await expect(page.locator(PANEL)).toBeHidden();
}

test.describe('咨询 CTA 统一打开询盘面板', () => {
    test('悬浮按钮可打开面板', async ({ page }, testInfo) => {
        // 悬浮气泡是 `hidden sm:inline-flex`——sm 以下由底部操作条接手（2.5 期），
        // 移动视口下它本来就不该存在。移动端的等价入口见下一条。
        test.skip(testInfo.project.name !== 'desktop', 'sm 以下由移动端底部操作条接手');

        await page.goto('/');
        await expectPanelClosed(page);

        await page.locator('[data-contact-trigger="floating"]').click();
        await expectPanelOpen(page);
    });

    test('移动端底部操作条的咨询入口可打开面板', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile', '底部操作条仅在 sm 以下出现');

        await page.goto('/');
        await expectPanelClosed(page);

        await page.locator('[data-contact-trigger="mobile-bar"]').click();
        await expectPanelOpen(page);
    });

    test('首屏 CTA 可打开面板', async ({ page }) => {
        await page.goto('/');

        // 首屏版式取决于有没有生效中的幻灯片：有就是 banner-hero（trigger=banner），
        // 没有才回落 hero 组件（trigger=hero）。二期 B1 起本站一直走前者，
        // 但包要对两种情况都成立，所以这里两个都收。
        //
        // software 主题（五期批次 4c）的 hero 组件不挂咨询触发器——它走
        // 快速开始/在线演示/GitHub 三个跳转按钮，不是装修业务那套线索表单，
        // 联系入口仍在导航栏/页脚/详情页 CTA（本文件其余用例覆盖）。
        // 没配 HOME_TOP 幻灯片时该主题首屏就没有这个 trigger，跳过而不是判红。
        const trigger = page.locator(
            '[data-contact-trigger="banner"], [data-contact-trigger="hero"]'
        ).first();

        test.skip((await trigger.count()) === 0, '当前首屏版式没有咨询 CTA（如 software 主题的静态 hero）');

        await expect(trigger, '首屏应当有一个咨询 CTA').toBeVisible();
        await trigger.click();
        await expectPanelOpen(page);
    });

    test('首页底部 CTA 可打开面板', async ({ page }) => {
        await page.goto('/');
        const trigger = page.locator('[data-contact-trigger="home-cta"]');
        await trigger.scrollIntoViewIfNeeded();
        await trigger.click();
        await expectPanelOpen(page);
    });

    test('桌面主导航 CTA 可打开面板', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', '桌面导航仅在 md 及以上断点可见');

        await page.goto('/');
        await page.locator('[data-contact-trigger="nav-desktop"]').click();
        await expectPanelOpen(page);
    });

    test('移动端菜单 CTA 可打开面板', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile', '移动端抽屉仅在 md 以下断点可见');

        await page.goto('/');
        await page.locator('button[aria-controls="mobile-nav"]').click();

        const trigger = page.locator('[data-contact-trigger="nav-mobile"]');
        await expect(trigger).toBeVisible();
        await trigger.click();
        await expectPanelOpen(page);
    });

    test('方案详情页 CTA 可打开面板', async ({ page }) => {
        await page.goto('/solutions');

        const firstDetail = page.locator('a[href*="/solutions/"]').first();
        test.skip((await firstDetail.count()) === 0, '当前站点没有已发布方案');
        await firstDetail.click();

        const trigger = page.locator('[data-contact-trigger="solution-detail"]');
        await trigger.scrollIntoViewIfNeeded();
        await trigger.click();
        await expectPanelOpen(page);
    });

    test('案例详情页 CTA 可打开面板', async ({ page }) => {
        await page.goto('/cases');

        const firstDetail = page.locator('a[href*="/cases/"]').first();
        test.skip((await firstDetail.count()) === 0, '当前站点没有已发布案例');
        await firstDetail.click();

        const trigger = page.locator('[data-contact-trigger="case-detail"]');
        await trigger.scrollIntoViewIfNeeded();
        await trigger.click();
        await expectPanelOpen(page);
    });

    test('面板可通过关闭按钮与 ESC 关闭', async ({ page }, testInfo) => {
        await page.goto('/');

        // 桌面用悬浮气泡、移动端用底部操作条——两个视口下常驻的咨询入口不是同一个
        const openPanel = testInfo.project.name === 'mobile'
            ? '[data-contact-trigger="mobile-bar"]'
            : '[data-contact-trigger="floating"]';

        await page.locator(openPanel).click();
        await expectPanelOpen(page);

        await page.locator(`${PANEL} button[aria-label="关闭表单"]`).click();
        await expectPanelClosed(page);

        await page.locator(openPanel).click();
        await expectPanelOpen(page);
        await page.keyboard.press('Escape');
        await expectPanelClosed(page);
    });
});

test.describe('固定 CTA 避让', () => {
    /**
     * 移动端固定在底部的是**操作条**，不是悬浮气泡——气泡 `hidden sm:inline-flex`，
     * sm 以下根本不渲染。本用例原来断言气泡在移动端可见，是 2.5 期加操作条之前的
     * 写法，一直红着。改成对当前版式成立的两条：操作条自己贴底、末条内容不被它压住。
     */
    test('移动端底部操作条不遮挡页面末条内容', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile', '底部操作条仅在 sm 以下出现');

        await page.goto('/cases');
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(300);

        const bar = page.locator('nav[aria-label="快捷联系"]');
        const box = await bar.boundingBox();
        expect(box, '底部操作条应可见').not.toBeNull();

        // 贴底：safe-area 之外不该再留缝，也不该被顶出视口
        const viewport = page.viewportSize();
        expect(viewport).not.toBeNull();
        if (box && viewport) {
            expect(Math.abs(viewport.height - (box.y + box.height))).toBeLessThanOrEqual(2);
        }

        // main 底部预留了 padding，末条卡片不应被操作条压住
        const overlapped = await page.evaluate(() => {
            const bar = document.querySelector('nav[aria-label="快捷联系"]');
            const main = document.getElementById('main-content');
            if (!bar || !main) return false;
            const b = bar.getBoundingClientRect();

            return Array.from(main.querySelectorAll('article')).some((card) => {
                const c = card.getBoundingClientRect();

                return !(c.bottom < b.top || c.top > b.bottom || c.right < b.left || c.left > b.right);
            });
        });
        expect(overlapped, '底部操作条不应与内容卡片重叠').toBe(false);
    });

    test('桌面悬浮按钮不遮挡页面末条内容', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', '悬浮气泡仅在 sm 及以上出现');

        await page.goto('/cases');
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(300);

        const button = page.locator('[data-contact-trigger="floating"]');
        const box = await button.boundingBox();
        expect(box, '悬浮按钮应可见').not.toBeNull();

        const viewport = page.viewportSize();
        expect(viewport).not.toBeNull();
        if (box && viewport) {
            expect(viewport.height - (box.y + box.height)).toBeGreaterThanOrEqual(16);
        }

        const overlapped = await page.evaluate(() => {
            const btn = document.querySelector('[data-contact-trigger="floating"]');
            const main = document.getElementById('main-content');
            if (!btn || !main) return false;
            const b = btn.getBoundingClientRect();

            return Array.from(main.querySelectorAll('article')).some((card) => {
                const c = card.getBoundingClientRect();

                return !(c.bottom < b.top || c.top > b.bottom || c.right < b.left || c.left > b.right);
            });
        });
        expect(overlapped, '悬浮按钮不应与内容卡片重叠').toBe(false);
    });
});

test.describe('前台资源与 SEO 基线', () => {
    test('公开页面不加载外部占位图', async ({ page }) => {
        /** @type {string[]} */
        const external = [];
        page.on('request', (request) => {
            const url = request.url();
            if (url.includes('picsum.photos') || url.includes('unsplash.com')) {
                external.push(url);
            }
        });

        for (const path of ['/', '/cases', '/solutions', '/products']) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
        }

        expect(external, `不应请求外部占位图：${external.join(', ')}`).toHaveLength(0);
    });

    test('列表页 meta description 非空且 sitemap/robots 可访问', async ({ page, request }) => {
        for (const path of ['/', '/cases', '/solutions', '/products']) {
            await page.goto(path);
            const description = await page
                .locator('meta[name="description"]')
                .getAttribute('content');
            expect(description?.trim(), `${path} 的 meta description 不应为空`).toBeTruthy();
        }

        expect((await request.get('/sitemap.xml')).status()).toBe(200);
        expect((await request.get('/robots.txt')).status()).toBe(200);
    });

    test('未配置 OG 图时不输出指向 404 的 og:image', async ({ page, request }) => {
        await page.goto('/');
        const ogImage = await page.locator('meta[property="og:image"]').count();

        if (ogImage > 0) {
            const url = await page.locator('meta[property="og:image"]').getAttribute('content');
            expect(url).toBeTruthy();
            const response = await request.get(String(url));
            expect(response.status(), 'og:image 必须可访问').toBeLessThan(400);
        }
    });
});
