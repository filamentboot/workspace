// @ts-check
/**
 * 半透明底色与首屏高度的 computed 值回归（3.5 期 G1）
 *
 * 为什么必须实测 computed 值、不能看截图：这一族缺陷是**类名在、产物里没有规则**。
 * 一条完全透明的白色导航条，在页面滚动到顶时截图上看起来完全正常，只有滚动之后
 * 才露馅；而 CI 截图恰好都是滚到顶拍的。CLAUDE.md 里三条「静默失效」硬约束
 * 全是这个形态，本文件是它们的自动化闸门。
 *
 * 三个已经踩过的坑（都不报错、不告警）：
 *   1. 给手写语义类加斜杠透明度  → 编译不出任何规则
 *   2. 语义类写进 @layer utilities → 变体（hover:/md:）全部消失
 *   3. 内联 color-mix()           → Chrome 111 以下整条声明被丢弃
 * 第 3 条是本期新加的：微信 Android 的 XWeb 长期停在 Chromium 107 一档，
 * 那一档上原来的固定导航条、底部操作条、图上文案面板**全是完全透明的**。
 * 现在统一走 decoration.css / software.css 里预声明的 bg-site-*-NN 档位。
 *
 * 期望值写死成 rgba 字面量是有意的：它就是「这一档到底该是什么颜色」的断言。
 * 改主题 token 时这些数字**应该**红——那正是提醒去看 --site-bg-*-rgb 那几行。
 *
 * ## 为什么下面这组断言不区分主题
 *
 * 用到的 `--site-bg-base-rgb` / `--site-bg-surface-rgb` 两个 token 与
 * `.bg-site-base-*` / `.bg-site-surface-*` 系列 `@utility` 现在都定义在
 * `resources/css/themes/shared.css` 里（七期批次 1 起两套主题共用同一份，
 * 见 CLAUDE.md「双主题：真分岔各自持有」），不是 decoration 各自持有的值——
 * 在 software 主题下跑同一批断言应该得到完全相同的结果。这里只跑一遍是为了
 * 不用额外切一次主题再跑一遍同样的数字，不是「只验了 decoration」；哪天两套
 * 主题的这几个 token 真分岔了，这条注释和下面的断言都需要跟着改。
 *
 * 运行：
 *   php artisan serve --port=8124
 *   npx playwright test site-computed-styles --config=playwright.config.site.cjs
 */
const { test, expect } = require('@playwright/test');

/**
 * 取元素的 computed 属性值
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} selector
 * @param {string} property
 * @returns {Promise<string|null>} 元素不存在时返回 null
 */
async function computed(page, selector, property) {
    return page.evaluate(
        ([sel, prop]) => {
            const el = document.querySelector(sel);

            return el ? getComputedStyle(el).getPropertyValue(prop) : null;
        },
        [selector, property]
    );
}

test.describe('半透明底色（两套主题共享 shared.css 里的同一份 token，见上方说明）', () => {
    test('首页固定导航条不是完全透明的', async ({ page }) => {
        await page.goto('/');

        // 曾经实测为 rgba(0, 0, 0, 0)：内容滚上来直接透过去
        expect(await computed(page, 'header.fixed', 'background-color'))
            .toBe('rgba(245, 245, 245, 0.8)');
    });

    test('首页幻灯片的提亮层与文案面板保持各自的透明度', async ({ page }) => {
        await page.goto('/');

        const hero = 'section[aria-label="首页幻灯片"]';

        // 提亮层：照片要透得出来，不能变成不透明白块
        expect(await computed(page, `${hero} .bg-site-base-35`, 'background-color'))
            .toBe('rgba(255, 255, 255, 0.35)');

        // 文案面板：近实色，正文对比度靠它兜住
        expect(await computed(page, `${hero} .bg-site-base-94`, 'background-color'))
            .toBe('rgba(255, 255, 255, 0.94)');
    });

    test('移动端底部操作条不是完全透明的', async ({ page }) => {
        await page.goto('/');

        expect(await computed(page, 'nav[aria-label="快捷联系"]', 'background-color'))
            .toBe('rgba(245, 245, 245, 0.95)');
    });

    test('列表页的固定导航条同样有底色', async ({ page }) => {
        await page.goto('/products');

        expect(await computed(page, 'header.fixed', 'background-color'))
            .toBe('rgba(245, 245, 245, 0.8)');
    });
});

test.describe('首屏高度的 dvh 回退', () => {
    /**
     * `dvh` 要 Chrome 108 / Safari 15.4，低于门槛整条 min-height 被丢弃、
     * 首屏塌成内容高度。回退包在 @supports 里（不能同规则写两行 min-height，
     * Lightning CSS 会把前一条当冗余删掉），这里断言最终值是个真实像素高度。
     */
    test('首页幻灯片区块有确定的最小高度', async ({ page }) => {
        await page.goto('/');

        const minHeight = await computed(page, 'section[aria-label="首页幻灯片"]', 'min-height');

        expect(minHeight).toMatch(/^\d+(\.\d+)?px$/);
        expect(parseFloat(minHeight ?? '0')).toBeGreaterThan(300);
    });
});

test.describe('变体真的生成了', () => {
    /**
     * 语义类写进 @layer utilities 时 hover: 之类的变体一条都生成不出来——
     * 类名照样在 HTML 里，产物里没有规则。静态截图完全看不出来，
     * 只能实测 hover 前后的 computed 值。
     */
    // 触摸设备上没有 hover 态，这条只在桌面 project 跑
    test.skip(({ isMobile }) => Boolean(isMobile), '触摸设备没有 hover 态');

    test('次级链接 hover 后文字颜色确实变化', async ({ page }) => {
        await page.goto('/');

        // 主导航第一项。它是 text-site-secondary + hover:text-site-accent，
        // 两个都是 @utility 声明的语义类——变体失效时这里会停在 secondary 不动。
        const link = page.locator('nav[aria-label="主导航"] a').first();

        const before = await link.evaluate(el => getComputedStyle(el).color);

        await link.hover();

        // 这批链接带 transition-colors duration-200，hover 之后立刻取 computed 值
        // 拿到的是过渡中间态（还等于 before）。轮询到过渡结束为止，
        // 不是给「变体没生成」放水——那种情况下颜色永远不会变，一样会超时红。
        await expect.poll(
            () => link.evaluate(el => getComputedStyle(el).color),
            { timeout: 3000 }
        ).not.toBe(before);
    });
});

test.describe('前台脚本交付', () => {
    test('Alpine 已加载且公开页无控制台报错', async ({ page }) => {
        /** @type {string[]} */
        const errors = [];

        page.on('console', message => message.type() === 'error' && errors.push(message.text()));
        page.on('pageerror', error => errors.push(String(error)));

        await page.goto('/');

        // Alpine 由 site.js 经 Vite 独立交付，不是 Livewire 捎带的；
        // 它没进 build 的话，导航抽屉、询盘面板、图集轮播全部不可交互。
        expect(await page.evaluate(() => typeof window.Alpine !== 'undefined')).toBe(true);

        expect(errors).toEqual([]);
    });
});
