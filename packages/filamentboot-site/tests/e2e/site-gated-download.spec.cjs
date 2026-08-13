// @ts-check
/**
 * 资料索取（gated content）浏览器端回归（3.5 期 F 段）
 *
 * `GatedDownloadBlock::file` 是 `required`，后台不传文件这一页压根保存不了
 * 已发布状态，这是那道门自己的守卫，不是缺陷。也因此，`GatedAssetRegistry`
 * （只扫已发布页）里没有对应登记时，`/downloads/{key}` 无论传什么都查不到。
 *
 * 结论：**资料索取的完整交互闭环（填表 → 拿到下载链接 → 点击下载）没有一份
 * 通用 demo 内容配了这个区块**（批次 3 的 decoration/software 两套演示数据
 * 都不含 `GatedDownloadBlock`）。服务端全链路已经在
 * `tests/Feature/SiteGatedDownloadTest.php` 用 RefreshDatabase 隔离测试库 +
 * 真实上传文件覆盖过（登记表构建 / 签名下载 / 未签名拒绝等），但那不是浏览器、
 * 也验不到 Alpine 的 `submit()` 是否真把响应里的 `download` 字段渲染成可点的
 * 下载按钮——这一段仍然是空的，**等下游自己配了带 GatedDownloadBlock 的已发布
 * 页面再补**，不在这里用假数据在开发库上现造一个实例。
 *
 * 本文件因此只锁**当前真实存在、且是安全边界**的那部分：不存在
 * （或未发布）的 slug 在真实浏览器里确实是 404，不会因为路由 / 缓存层的
 * 某个疏漏而意外可访问，也不会残留半成品内容——这条不依赖任何具体 demo 内容，
 * 用一个刻意起的、几乎不可能真实存在的 slug 即可验证，换主题/换 demo 数据
 * 都不影响这条断言。
 *
 * 运行：
 *   php artisan serve --port=8124
 *   npx playwright test site-gated-download --config=playwright.config.site.cjs
 */
const { test, expect } = require('@playwright/test');

test('不存在的 slug 对访客是真 404，不会意外可访问或漏出半成品内容', async ({ page }) => {
    const response = await page.goto('/e2e-probe-nonexistent-page-slug');

    expect(response?.status()).toBe(404);
    // 不该漏出资料索取区块的任何文案——404 页面必须是 404 页面本身，不是"内容
    // 渲染了但套了个 404 状态码"这种半吊子状态
    await expect(page.getByText('留个联系方式，领资料')).toHaveCount(0);
});
