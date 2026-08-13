// @ts-check
/**
 * 窄视口矩阵回归（3.5 期 G2）
 *
 * 移动端此前**从来没有在真实浏览器上按视口档位验过**。既有的移动断言只有
 * site-contact-cta 里那一条固定 CTA 避让，而且用的是 Pixel 5 一档（393px）——
 * 恰好是最宽的那一档，320 / 360 上的问题全被漏掉。
 *
 * 2026-08-08 首次跑这套矩阵就抓到一条：**站内搜索页在 320px 溢出 52px、
 * 360px 溢出 13px**，整页（含固定导航条与底部操作条）可以左右拖动。根因是
 * 搜索框 `flex-1` 少了 `min-w-0`——flex 项目默认 `min-width:auto`，
 * `<input type="search">` 的固有最小宽度（size=20，约 177px）顶住不让缩。
 * 已修，本文件把它锁住。
 *
 * ## 触摸目标用的是哪一条标准
 *
 * **WCAG 2.5.8（AA）的 24×24**，不是 2.5.5（AAA）的 44×44。这是项目既定口径：
 * banner 轮播圆点在 2.5 期就是按 24 定的（见 banner-hero.blade.php 那段注释）。
 * 断言只覆盖**独立控件**——导航、汉堡、筛选胶囊、分页、轮播控件、底部操作条。
 * 正文里的文字链接走 2.5.8 的 inline 例外（高度由行高决定），不在断言范围内；
 * 它们的实测数据记在 docs/cms/03-3.5-对应性清单.md，要不要加大是设计决定。
 *
 * ## 针对官方 demo 数据的默认版
 *
 * `/about` 是两套主题演示数据都会种的静态页 slug，默认配置下两套主题都能跑通。
 * `/tags/smart-home` 则是 decoration 主题专有的标签 slug（见
 * `Demo\DecorationDemoSeeder::seededSlugs()`）——software 主题的标签是
 * `api-integration`/`automation` 等一套完全不同的词，同一条路径会 404。
 * 跑在 software 主题上时把这一条换成 `/tags/api-integration` 之类真实存在的
 * 标签地址；这条断言本身只关心「这一类版式在窄视口下不横向溢出」，
 * 不关心具体是哪一个标签。
 *
 * 运行：
 *   php artisan serve --port=8124
 *   npx playwright test site-mobile-viewport --config=playwright.config.site.cjs
 */
const { test, expect } = require('@playwright/test');

/** 窄视口档位：iPhone SE 之前的小屏 / 主流 Android / iPhone SE / Pixel 5 */
const WIDTHS = [320, 360, 375, 393];

/** 九类公开地址，覆盖每一种版式（后两条依赖官方 demo 数据，见上方说明） */
const PATHS = [
    '/',
    '/cases',
    '/solutions',
    '/products',
    '/packages',
    '/news',
    '/search?q=智能',
    '/tags/smart-home',
    '/about',
];

/** 项目既定的触摸目标下限（WCAG 2.5.8 AA） */
const MIN_TARGET = 24;

// 整份文件只在移动 project 下跑：桌面视口验不出这些
test.describe.configure({ mode: 'default' });

test.describe('窄视口矩阵', () => {
    test.skip(({ isMobile }) => !isMobile, '窄视口断言只在移动 project 下有意义');

    for (const width of WIDTHS) {
        test(`${width}px 下九类地址都不横向溢出`, async ({ page }) => {
            await page.setViewportSize({ width, height: 800 });

            /** @type {string[]} */
            const failures = [];

            for (const path of PATHS) {
                const response = await page.goto(path, { waitUntil: 'networkidle' });
                expect(response?.status(), `${path} 应当 200`).toBe(200);

                const result = await page.evaluate(() => {
                    const de = document.documentElement;
                    const overflow = de.scrollWidth - de.clientWidth;
                    if (overflow <= 0) return { overflow, source: '' };

                    // 报最外层的越界元素——内层的都是它撑出来的，列出来只会刷屏
                    /** @type {Element[]} */
                    const outermost = [];
                    for (const el of document.querySelectorAll('body *')) {
                        const rect = el.getBoundingClientRect();
                        if (rect.width === 0 || rect.height === 0) continue;
                        if (rect.right <= de.clientWidth + 1) continue;
                        if (outermost.some(seen => seen.contains(el))) continue;
                        outermost.push(el);
                    }

                    return {
                        overflow,
                        source: outermost
                            .slice(0, 3)
                            .map(el => `<${el.tagName.toLowerCase()} class="${String(el.className).slice(0, 60)}">`)
                            .join(' / '),
                    };
                });

                if (result.overflow > 0) {
                    failures.push(`${path} 溢出 ${result.overflow}px：${result.source}`);
                }
            }

            expect(failures, `以下地址在 ${width}px 下横向溢出`).toEqual([]);
        });
    }
});

test.describe('双固定元素的内容避让', () => {
    test.skip(({ isMobile }) => !isMobile, '底部操作条仅在 sm 以下出现');

    /**
     * 顶部固定导航 64px + 底部固定操作条（含 safe-area）同时存在。
     * 内容既不能被顶部盖住，也不能被底部盖住——两头各压一次是最容易漏的组合，
     * 因为单独看每一头都是对的。
     */
    test('列表页滚到底时首末条内容都不被固定条压住', async ({ page }) => {
        await page.goto('/cases');

        const nav = await page.locator('header.fixed').boundingBox();
        const bar = await page.locator('nav[aria-label="快捷联系"]').boundingBox();
        expect(nav, '顶部固定导航应存在').not.toBeNull();
        expect(bar, '底部操作条应存在').not.toBeNull();

        // 页首：第一条内容不能藏在导航条底下
        const firstTop = await page.evaluate(() => {
            const first = document.querySelector('#main-content article');

            return first ? first.getBoundingClientRect().top : null;
        });
        expect(firstTop, '列表页应当有内容卡片').not.toBeNull();
        expect(firstTop ?? 0, '首条内容不应被顶部导航压住').toBeGreaterThanOrEqual((nav?.height ?? 0) - 1);

        // 页尾：滚到底后最后一条内容不能藏在操作条底下
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(300);

        const lastBottom = await page.evaluate(() => {
            const cards = document.querySelectorAll('#main-content article');
            const last = cards[cards.length - 1];

            return last ? last.getBoundingClientRect().bottom : null;
        });
        expect(lastBottom ?? 0, '末条内容不应被底部操作条压住').toBeLessThanOrEqual(bar?.y ?? 0);
    });
});

test.describe('独立控件的触摸目标', () => {
    test.skip(({ isMobile }) => !isMobile, '触摸目标只在触摸设备上有意义');

    /**
     * 只查独立控件。正文文字链接按 WCAG 2.5.8 的 inline 例外放行——
     * 它们的高度由行高决定，硬撑到 24px 会把正文行距整个撑开。
     *
     * 每条都带一个「至少命中几个」的下限：**选择器写错或版式改名时，
     * 一条匹配不到任何元素的断言会安静地变绿**——那比没有这条断言更糟。
     *
     * @type {Array<[string, string, string, number]>} [说明, 地址, 选择器, 最少命中数]
     */
    const CONTROLS = [
        ['汉堡按钮',       '/',      'button[aria-controls="mobile-nav"]', 1],
        ['底部操作条按钮', '/',      'nav[aria-label="快捷联系"] > a, nav[aria-label="快捷联系"] > button', 2],
        ['轮播控件',       '/',      'section[aria-label="首页幻灯片"] button', 1],
        ['资讯分类胶囊',   '/news',  'div[aria-label="资讯分类筛选"] a', 2],
        ['分页',           '/news',  '#main-content nav a', 1],
    ];

    for (const [label, path, selector, minimum] of CONTROLS) {
        test(`${label}不小于 ${MIN_TARGET}×${MIN_TARGET}`, async ({ page }) => {
            await page.goto(path);

            const measured = await page.evaluate(
                ([sel, min]) => Array.from(document.querySelectorAll(String(sel)))
                    .map(el => ({
                        size: el.getBoundingClientRect(),
                        text: (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 12),
                    }))
                    .filter(({ size }) => size.width > 0 && size.height > 0)
                    .map(({ size, text }) => ({
                        label: `${Math.round(size.width)}x${Math.round(size.height)} "${text}"`,
                        ok: size.width >= Number(min) && size.height >= Number(min),
                    })),
                [selector, MIN_TARGET]
            );

            expect(measured.length, `${label}一个都没匹配到——选择器过时了`)
                .toBeGreaterThanOrEqual(minimum);

            expect(
                measured.filter(item => ! item.ok).map(item => item.label),
                `${label}存在过小的触摸目标`
            ).toEqual([]);
        });
    }

    test('移动端抽屉里的栏目链接不小于触摸下限', async ({ page }) => {
        await page.goto('/');
        await page.locator('button[aria-controls="mobile-nav"]').click();
        await expect(page.locator('#mobile-nav')).toBeVisible();

        const measured = await page.evaluate((min) =>
            Array.from(document.querySelectorAll('#mobile-nav a, #mobile-nav button'))
                .map(el => ({
                    size: el.getBoundingClientRect(),
                    text: (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 12),
                }))
                .filter(({ size }) => size.width > 0 && size.height > 0)
                .map(({ size, text }) => ({
                    label: `${Math.round(size.width)}x${Math.round(size.height)} "${text}"`,
                    ok: size.height >= Number(min),
                })),
            MIN_TARGET
        );

        // 关闭按钮 + 至少一条栏目链接 + 底部 CTA
        expect(measured.length, '抽屉里一个可点元素都没匹配到').toBeGreaterThanOrEqual(3);

        expect(
            measured.filter(item => ! item.ok).map(item => item.label),
            '抽屉里存在过小的触摸目标'
        ).toEqual([]);
    });
});

test.describe('窄视口下的脚本与样式', () => {
    test.skip(({ isMobile }) => !isMobile, '与桌面 project 重复');

    test('320px 下 Alpine 可用、固定条有底色、无控制台报错', async ({ page }) => {
        /** @type {string[]} */
        const errors = [];
        page.on('console', message => message.type() === 'error' && errors.push(message.text()));
        page.on('pageerror', error => errors.push(String(error)));

        await page.setViewportSize({ width: 320, height: 800 });
        await page.goto('/');

        expect(await page.evaluate(() => typeof window.Alpine !== 'undefined')).toBe(true);

        // 最窄档位下同样不能是 rgba(0,0,0,0)——半透明档位与视口宽度无关，
        // 但这一条顺带证明 CSS 真的加载了（而不是整份样式表被跳过）
        const navBackground = await page.evaluate(
            () => getComputedStyle(document.querySelector('header.fixed')).backgroundColor
        );
        expect(navBackground).toBe('rgba(245, 245, 245, 0.8)');

        // 抽屉能开合：Alpine 真的接管了，不只是脚本加载了
        await page.locator('button[aria-controls="mobile-nav"]').click();
        await expect(page.locator('#mobile-nav')).toBeVisible();

        expect(errors).toEqual([]);
    });
});
