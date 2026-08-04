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
 * ⚠️ 选择器约定（2026-08-04 真机校准，别照 Filament 文档想当然）：
 *   - **面板语言是 en**。Filament 自带文案是英文：`Create` / `Save changes` /
 *     `Confirm` / `Add to :label`。只有本包自己写的动作标签是中文（预览 / 发布 /
 *     提交审核 / 归档 / 退回草稿）。混着来。
 *   - 表单控件的 **id 是 `form.<path>`**，wire:model 才是 `data.<path>`。
 *     写 `input[id="data.slug"]` 一个都选不中。
 *   - `native(false)` 的 Select 不是 <select>：触发器是 `.fi-select-input-btn`，
 *     选项是 `li[role=option][data-value=…]`。
 *   - 树页工具栏也有个 `Save`（保存拖拽顺序），模态里的提交必须限定在
 *     `.fi-modal-window` 内，否则点到工具栏那个。
 *
 * 不进 CI（Playwright 由本人不定时手跑，见 CLAUDE.md 的测试策略）。
 *
 * 运行：
 *   php artisan serve --port=8123
 *   BASE_URL=http://localhost:8123 npx playwright test uat-phase12 --config=playwright.config.uat.cjs
 *
 * 前置：后台账号 admin@example.com / password（global-setup 负责登录）。
 */
const { test, expect } = require('@playwright/test');

/** 本次运行的唯一后缀，避免与库里已有数据撞 slug（slug 有 unique 索引） */
const RUN = `uat12-${Date.now().toString(36)}`;

/** 两套主题，逐一在前台验证区块渲染 */
const THEMES = ['decoration', 'tech-product'];

/**
 * 打开后台某个路径并等 Livewire 挂好
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} path 形如 site-pages
 */
async function gotoAdmin(page, path) {
    await page.goto(`/admin/${path}`);
    await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });
}

/**
 * 选一个 native(false) 的 Filament Select
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} statePath 形如 data.active_theme
 * @param {string} value 选项的 data-value
 */
async function pickSelect(page, statePath, value) {
    const wrap = page.locator(`[wire\\:partial*="${statePath}"]`).first();
    await wrap.locator('.fi-select-input-btn').first().click();
    await page.waitForTimeout(300);
    await page.locator(`li[role="option"][data-value="${value}"]`).first().click();
    await page.waitForTimeout(800);
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
 * @returns {Promise<string>} 新页面的 id
 */
async function createDraftPageWithBlock(page, slug, title) {
    await gotoAdmin(page, 'site-pages/create');

    await page.locator('input[id="form.slug"]').fill(slug);
    await page.locator('input[id="form.title_zh"]').fill(title);

    // 加一个「首屏横幅」区块。Builder 的添加按钮文案是 Filament 的
    // `Add to :label`，:label 是本包给的中文「页面区块」
    await page.getByRole('button', { name: 'Add to 页面区块' }).first().click();
    await page.getByRole('button', { name: '首屏横幅', exact: true }).first().click();

    const heroTitle = page.locator('input[id^="form.blocks"][id$=".data.title"]').first();
    await heroTitle.waitFor({ timeout: 15000 });
    await heroTitle.fill(`${title} 的区块标题`);

    await page.getByRole('button', { name: 'Create', exact: true }).first().click();

    // 创建后跳到编辑页
    await page.waitForURL(/\/site-pages\/\d+\/edit/, { timeout: 20000 });

    const id = (page.url().match(/site-pages\/(\d+)\/edit/) || [])[1];
    expect(id, '创建后应跳到编辑页并带上记录 id').toBeTruthy();

    return id;
}

/**
 * 点一个带确认弹窗的 Header Action 并确认
 *
 * 动作标签是本包写的中文；确认按钮是 Filament 的英文 Confirm。
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} label
 */
async function confirmAction(page, label) {
    await page.getByRole('button', { name: label, exact: true }).first().click();

    const modal = page.locator('.fi-modal-window').filter({ hasText: /Confirm|Cancel/ }).first();
    await modal.waitFor({ state: 'visible', timeout: 15000 });
    await modal.getByRole('button', { name: 'Confirm', exact: true }).first().click();

    await page.waitForTimeout(2000);
}

/**
 * 点一个 Filament Tabs 的标签
 *
 * Tabs 渲染成 <button> 但 ARIA role 是 tab，getByRole('button') 选不中。
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} name
 */
async function clickTab(page, name) {
    await page.locator('[role="tab"]', { hasText: name }).first().click();
    await page.waitForTimeout(400);
}

/**
 * 切换前台主题（后台「网站设置」页 → 外观 Tab）
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} theme
 */
async function switchTheme(page, theme) {
    await gotoAdmin(page, 'settings/site');

    await clickTab(page, '外观');

    await pickSelect(page, 'active_theme', theme);

    await page.getByRole('button', { name: 'Save changes', exact: true }).first().click();
    await page.waitForTimeout(2000);
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
        await expect(page.getByRole('button', { name: '待审核', exact: true }).first()).toBeVisible({ timeout: 15000 });

        await confirmAction(page, '发布');
        await expect(page.getByRole('button', { name: '已发布', exact: true }).first()).toBeVisible({ timeout: 15000 });

        // 前台可见，且区块渲染出来了
        await page.goto(`/${slug}`);
        await expect(page.getByText(`${title} 的区块标题`)).toBeVisible({ timeout: 15000 });
    });

    for (const theme of THEMES) {
        test(`区块在 ${theme} 主题下渲染`, async ({ page }) => {
            await switchTheme(page, theme);

            await page.goto(`/${slug}`);

            await expect(page.getByText(`${title} 的区块标题`)).toBeVisible({ timeout: 15000 });

            // 两套主题都用 bg-site-* 语义工具类，确认主题 CSS 那一份真的进来了
            expect(await page.content()).toContain('bg-site-base');
        });
    }

    test('草稿预览带 noindex 且未登录访问被拒', async ({ page, browser }) => {
        const draftSlug = `${RUN}-draft`;
        const pageId = await createDraftPageWithBlock(page, draftSlug, `UAT 草稿 ${RUN}`);

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
        await page.getByRole('link', { name: title }).first().click();
        await page.waitForURL(/\/site-pages\/\d+\/edit/, { timeout: 20000 });

        const newSlug = `${slug}-moved`;
        await page.locator('input[id="form.slug"]').fill(newSlug);
        await page.getByRole('button', { name: 'Save changes', exact: true }).first().click();
        await page.waitForTimeout(2500);

        // 旧地址 301 到新地址
        const redirected = await page.request.get(`/${slug}`, { maxRedirects: 0 });
        expect(redirected.status()).toBe(301);
        expect(redirected.headers()['location']).toContain(newSlug);

        // 重定向列表里能看到这条。
        // 必须 exact：库里 from_path 存的是 normalizePath() 归一后的值（trim 掉两端斜杠），
        // 列上用 ->prefix('/') 补回斜杠，渲染出来是 `/旧slug`。而 to_path 那一格是
        // `/旧slug-moved`——用模糊匹配就会被它命中，from_path 整行缺失也照样绿。
        await gotoAdmin(page, 'site-redirects');
        await expect(page.getByText(`/${slug}`, { exact: true }).first()).toBeVisible({ timeout: 15000 });
    });

    test('版本历史的查看 Modal 出对比表格', async ({ page }) => {
        // 唯一能证明这件事的地方就是真浏览器：对比表是 Text::make(fn (): HtmlString => …)
        // 手拼的 HTML，而 Filament 5 的模态体是客户端惰性渲染的，
        // Livewire::test() 里 action-modals 那块永远是空的（Feature 测试因此只能
        // 退一步去渲染 Text 组件本身，见 SitePageResourcePageTest）。
        await gotoAdmin(page, 'site-pages');

        await page.getByRole('link', { name: title }).first().click();
        await page.waitForURL(/\/site-pages\/\d+\/edit/, { timeout: 20000 });

        // 版本历史是关系管理器，渲染在表单下方
        await expect(page.getByText('版本历史').first()).toBeVisible({ timeout: 15000 });

        // 取最后一行的「查看」：列表按 id 倒序，第一行是最新快照，它与当前内容一致，
        // 点开只会得到「该版本与当前内容一致」那一句，看不到表格。最后一行是基线快照。
        await page.getByRole('button', { name: '查看', exact: true }).last().click();

        const modal = page.locator('.fi-modal-window').filter({ hasText: '版本内容对比' }).first();
        await modal.waitFor({ state: 'visible', timeout: 15000 });

        // 表头三列真的是表格单元格，而不是一串被转义的 &lt;table&gt; 文本
        await expect(modal.locator('th', { hasText: '该版本' }).first()).toBeVisible({ timeout: 10000 });
        await expect(modal.locator('th', { hasText: '当前' }).first()).toBeVisible();
        await expect(modal.getByText('URL Slug').first()).toBeVisible();
        await expect(modal.getByText('&lt;table')).toHaveCount(0);
    });
});

test.describe('前台导航跟随后台菜单', () => {
    test('建 main 菜单项后导航同步', async ({ page }) => {
        const label = `UAT导航${RUN}`;

        await gotoAdmin(page, 'site-menus');

        // 若还没有 main 菜单则建一条
        if (!(await page.getByText('main', { exact: true }).first().isVisible().catch(() => false))) {
            await gotoAdmin(page, 'site-menus/create');
            await page.locator('input[id="form.key"]').fill('main');
            await page.locator('input[id="form.name"]').fill('顶部导航');
            await page.getByRole('button', { name: 'Create', exact: true }).first().click();
            await page.waitForTimeout(2000);
            await gotoAdmin(page, 'site-menus');
        }

        // 进菜单项树。锚点项不依赖任何页面，最稳
        await page.getByRole('link', { name: '管理菜单项' }).first().click();
        await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });

        // 树页的新建是 Filament CreateAction，文案 `New :modelLabel`
        await page.getByRole('button', { name: /^New/ }).first().click();

        const labelInput = page.locator('input[id="mountedActionSchema0.label"]');
        await labelInput.waitFor({ state: 'visible', timeout: 15000 });
        await labelInput.fill(label);

        await pickSelect(page, 'mountedActionSchema0.type', 'anchor');

        await page.locator('input[id="mountedActionSchema0.target_anchor"]').fill('#uat');

        const modal = page.locator('.fi-modal-window').filter({ has: labelInput }).first();
        await modal.getByRole('button', { name: 'Create', exact: true }).first().click();
        await page.waitForTimeout(2500);

        // 前台导航出现这一项
        await page.goto('/');
        await expect(page.getByText(label).first()).toBeVisible({ timeout: 15000 });
    });

    test('菜单项删光后导航回退硬编码列表，不白屏', async ({ page }) => {
        await gotoAdmin(page, 'site-menu-items');

        // 逐条删干净（删除是 iconButton，aria-label 是 Filament 的 Delete）
        for (let guard = 0; guard < 20; guard++) {
            const del = page.locator('button[aria-label="Delete"]').first();

            if (!(await del.count())) {
                break;
            }

            await del.click();

            const modal = page.locator('.fi-modal-window').filter({ hasText: /Confirm|Delete/ }).first();
            await modal.waitFor({ state: 'visible', timeout: 15000 });
            await modal.getByRole('button', { name: /^(Confirm|Delete)$/ }).first().click();
            await page.waitForTimeout(1800);
        }

        // 回退硬编码列表，**不白屏**（升级安全硬要求）
        await page.goto('/');
        await expect(page.locator('nav[aria-label="主导航"]').first()).toBeVisible({ timeout: 15000 });
        await expect(page.getByText('装修案例').first()).toBeVisible({ timeout: 15000 });
    });
});
