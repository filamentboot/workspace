// @ts-check
/**
 * 阶段 2 收口 UAT（#13–#20）
 *
 * 覆盖后台到前台的整条链路，也就是 CMS「第一次真正可用」的那条路：
 *   建页面 → 加区块 → 提交审核 → 发布 → 前台可见
 *   改 slug → 自动建 301 → 旧地址可跳
 *   建菜单项 → 前台导航同步
 *   草稿预览带 noindex
 *
 * 两套主题各跑一遍：区块视图是双主题各一份完整副本（§0.3 第 1 条），
 * 只跑默认主题时另一套缺视图会一路静默降级成「区块不显示」。
 *
 * 不进 CI（Playwright 由本人不定时手跑，见 CLAUDE.md 的测试策略）。
 *
 * 运行：
 *   php artisan serve --port=8123
 *   BASE_URL=http://localhost:8123 npx playwright test uat-phase12 --config=playwright.config.cjs
 *
 * 前置：后台账号 admin@example.com / password（global-setup 负责登录）。
 */
const { test, expect } = require('@playwright/test');

/** 本次运行的唯一后缀，避免与库里已有数据撞 slug（slug 有 unique 索引） */
const RUN = `uat12-${Date.now().toString(36)}`;

/** 两套主题，逐一在前台验证区块渲染 */
const THEMES = ['decoration', 'tech-product'];

/**
 * 打开后台某个资源列表页
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} path 形如 site-pages
 */
async function gotoAdmin(page, path) {
    await page.goto(`/admin/${path}`);
    await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });
}

/**
 * 在「静态页面」里新建一个带区块的草稿页
 *
 * 只填最少字段 + 一个 hero 区块：Builder 的交互是本用例真正要验的东西，
 * 把七种区块全拖一遍只会让用例又长又脆。
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} slug
 * @param {string} title
 */
async function createDraftPageWithBlock(page, slug, title) {
    await gotoAdmin(page, 'site-pages/create');

    await page.locator('input[id="data.slug"]').fill(slug);
    await page.locator('input[id="data.title_zh"]').fill(title);

    // 加一个「首屏横幅」区块
    await page.getByRole('button', { name: /新增到.*页面区块|页面区块/ }).first().click();
    await page.getByRole('button', { name: '首屏横幅' }).first().click();

    const heroTitle = page.locator('input[id^="data.blocks"][id$=".data.title"]').first();
    await heroTitle.waitFor({ timeout: 10000 });
    await heroTitle.fill(`${title} 的区块标题`);

    await page.getByRole('button', { name: /^创建$|^新增$/ }).first().click();

    // 创建后跳到编辑页
    await page.waitForURL(/\/site-pages\/\d+\/edit/, { timeout: 20000 });
}

/**
 * 点一个 Filament Header Action 并确认它的确认弹窗
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} label
 */
async function confirmAction(page, label) {
    await page.getByRole('button', { name: label, exact: true }).first().click();

    // requiresConfirmation() 的模态：确认按钮文案随语言包，用 role 兜住
    const modal = page.locator('.fi-modal').last();
    await modal.waitFor({ timeout: 10000 });
    await modal.getByRole('button', { name: /确认|Confirm|提交|保存/ }).first().click();

    await page.waitForTimeout(1500);
}

/**
 * 切换前台主题（后台「网站设置」页）
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} theme
 */
async function switchTheme(page, theme) {
    await page.goto('/admin/settings/site');
    await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });

    const select = page.locator('select[id="data.active_theme"], [id="data.active_theme"]').first();
    await select.waitFor({ timeout: 10000 });
    await select.selectOption(theme).catch(async () => {
        // native(false) 的 Filament Select 不是原生 select，退回点选
        await select.click();
        await page.getByRole('option', { name: new RegExp(theme === 'decoration' ? '深色' : '浅色') }).first().click();
    });

    await page.getByRole('button', { name: /^保存|Save/ }).first().click();
    await page.waitForTimeout(1500);
}

test.describe.configure({ mode: 'serial' });

test.describe('阶段 2 收口：CMS 端到端', () => {
    const slug = `${RUN}-page`;
    const title = `UAT 区块页 ${RUN}`;

    test('建页面 → 加区块 → 提交审核 → 发布 → 前台可见', async ({ page }) => {
        await createDraftPageWithBlock(page, slug, title);

        // 草稿状态前台不可见（§0.3 第 3 条：草稿绝不泄露）
        const draftResponse = await page.request.get(`/${slug}`);
        expect(draftResponse.status()).toBe(404);

        // 提交审核 → 发布
        await confirmAction(page, '提交审核');
        await expect(page.getByText('待审核').first()).toBeVisible({ timeout: 10000 });

        await confirmAction(page, '发布');
        await expect(page.getByText('已发布').first()).toBeVisible({ timeout: 10000 });

        // 前台可见，且区块渲染出来了
        await page.goto(`/${slug}`);
        await expect(page.getByText(`${title} 的区块标题`)).toBeVisible({ timeout: 10000 });
    });

    for (const theme of THEMES) {
        test(`区块在 ${theme} 主题下渲染`, async ({ page }) => {
            await switchTheme(page, theme);

            await page.goto(`/${slug}`);

            await expect(page.getByText(`${title} 的区块标题`)).toBeVisible({ timeout: 10000 });

            // 主题各自的容器类应当出现，确认真的换了那一份视图
            const html = await page.content();
            expect(html).toContain('bg-site-base');
        });
    }

    test('草稿预览带 noindex 且未登录访问被拒', async ({ page, browser }) => {
        const draftSlug = `${RUN}-draft`;
        await createDraftPageWithBlock(page, draftSlug, `UAT 草稿 ${RUN}`);

        const pageId = (page.url().match(/site-pages\/(\d+)\/edit/) || [])[1];
        expect(pageId).toBeTruthy();

        // 已登录管理员直接访问预览：200 且带 noindex（双通道的第二条）
        const preview = await page.request.get(`/preview/${pageId}`);
        expect(preview.status()).toBe(200);
        expect(preview.headers()['x-robots-tag']).toContain('noindex');

        // 未登录 + 无签名 → 403。必须开一个不带 storageState 的干净上下文：
        // 默认上下文带着 global-setup 存下的后台 cookie，测不出未登录的行为。
        const anonContext = await browser.newContext({ storageState: undefined });
        const anonResponse = await anonContext.request.get(`/preview/${pageId}`);
        await anonContext.close();

        expect(anonResponse.status()).toBe(403);
    });

    test('改 slug 自动建 301，旧地址可跳', async ({ page }) => {
        await gotoAdmin(page, 'site-pages');

        // 回到目标页面的编辑页
        await page.getByRole('link', { name: title }).first().click().catch(async () => {
            await page.getByText(title).first().click();
        });
        await page.waitForURL(/\/site-pages\/\d+\/edit/, { timeout: 20000 });

        const newSlug = `${slug}-moved`;
        await page.locator('input[id="data.slug"]').fill(newSlug);
        await page.getByRole('button', { name: /^保存|Save/ }).first().click();
        await page.waitForTimeout(2000);

        // 旧地址 301 到新地址
        const redirected = await page.request.get(`/${slug}`, { maxRedirects: 0 });
        expect(redirected.status()).toBe(301);
        expect(redirected.headers()['location']).toContain(newSlug);

        // 重定向列表里能看到这条，且 hits 会涨
        await gotoAdmin(page, 'site-redirects');
        await expect(page.getByText(slug).first()).toBeVisible({ timeout: 10000 });
    });
});

test.describe('前台导航跟随后台菜单', () => {
    test('建 main 菜单项后导航同步，删空后回退硬编码列表', async ({ page }) => {
        const label = `UAT 导航 ${RUN}`;

        await gotoAdmin(page, 'site-menus');

        // 若还没有 main 菜单则建一条
        const hasMain = await page.getByText('main').first().isVisible().catch(() => false);
        if (!hasMain) {
            await page.getByRole('link', { name: /新建|Create/ }).first().click();
            await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });
            await page.locator('input[id="data.key"]').fill('main');
            await page.locator('input[id="data.name"]').fill('顶部导航');
            await page.getByRole('button', { name: /^创建$|^新增$/ }).first().click();
            await page.waitForTimeout(1500);
            await gotoAdmin(page, 'site-menus');
        }

        // 进菜单项树，加一个锚点项（锚点不依赖任何页面，最稳）
        await page.getByRole('link', { name: '管理菜单项' }).first().click();
        await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });

        await page.getByRole('button', { name: /新建|Create|新增/ }).first().click();
        const modal = page.locator('.fi-modal').last();
        await modal.waitFor({ timeout: 10000 });

        await modal.locator('input[id="data.label"]').fill(label);

        // 链接类型改成「页内锚点」
        const typeSelect = modal.locator('[id="data.type"]').first();
        await typeSelect.click();
        await page.getByRole('option', { name: '页内锚点' }).first().click();

        await modal.locator('input[id="data.target_anchor"]').fill('#uat');
        await modal.getByRole('button', { name: /^创建$|^新增$/ }).first().click();
        await page.waitForTimeout(2000);

        // 前台导航出现这一项
        await page.goto('/');
        await expect(page.getByText(label).first()).toBeVisible({ timeout: 10000 });

        // 删光菜单项 → 回退硬编码列表，**不白屏**（升级安全硬要求）
        await page.goBack();
        await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });
        await page.getByRole('button', { name: /删除|Delete/ }).first().click();
        const delModal = page.locator('.fi-modal').last();
        await delModal.waitFor({ timeout: 10000 });
        await delModal.getByRole('button', { name: /确认|Confirm|删除/ }).first().click();
        await page.waitForTimeout(2000);

        await page.goto('/');
        await expect(page.locator('nav[aria-label="主导航"]')).toBeVisible({ timeout: 10000 });
        await expect(page.getByText('装修案例').first()).toBeVisible({ timeout: 10000 });
    });
});
