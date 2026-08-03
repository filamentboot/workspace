// @ts-check
/**
 * 官网咨询 CTA 回归用例
 *
 * 线上 P0：桌面导航与移动菜单的「预约咨询」按钮只 remove 一个不存在的 hidden class，
 * 而询盘面板由 floating-contact 自己的 Alpine 作用域控制，点击后毫无反应。
 * 修复后所有 CTA 统一调用 $store.contactPanel.show()，本文件逐个入口回归。
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
    test('悬浮按钮可打开面板', async ({ page }) => {
        await page.goto('/');
        await expectPanelClosed(page);

        await page.locator('[data-contact-trigger="floating"]').click();
        await expectPanelOpen(page);
    });

    test('Hero 区 CTA 可打开面板', async ({ page }) => {
        await page.goto('/');
        await page.locator('[data-contact-trigger="hero"]').click();
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

    test('面板可通过关闭按钮与 ESC 关闭', async ({ page }) => {
        await page.goto('/');

        await page.locator('[data-contact-trigger="floating"]').click();
        await expectPanelOpen(page);

        await page.locator(`${PANEL} button[aria-label="关闭表单"]`).click();
        await expectPanelClosed(page);

        await page.locator('[data-contact-trigger="floating"]').click();
        await expectPanelOpen(page);
        await page.keyboard.press('Escape');
        await expectPanelClosed(page);
    });
});

test.describe('移动端固定 CTA 避让', () => {
    test('悬浮按钮不遮挡页面末条内容', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile', '仅验证移动端视口');

        await page.goto('/cases');
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(300);

        const button = page.locator('[data-contact-trigger="floating"]');
        const box = await button.boundingBox();
        expect(box, '悬浮按钮应可见').not.toBeNull();

        // 按钮底边需与视口底部保持安全间距（safe-area 生效）
        const viewport = page.viewportSize();
        expect(viewport).not.toBeNull();
        if (box && viewport) {
            expect(viewport.height - (box.y + box.height)).toBeGreaterThanOrEqual(16);
        }

        // main 底部预留了 pb-24，末条卡片不应被按钮压住
        const overlapped = await page.evaluate(() => {
            const btn = document.querySelector('[data-contact-trigger="floating"]');
            const main = document.getElementById('main-content');
            if (!btn || !main) return false;
            const b = btn.getBoundingClientRect();
            const cards = Array.from(main.querySelectorAll('article'));
            return cards.some((card) => {
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
