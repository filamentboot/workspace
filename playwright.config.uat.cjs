// @ts-check
/**
 * 后台 UAT 专用 Playwright 配置（需要管理员登录态）
 *
 * 与 playwright.config.site.cjs 的分工：那份跑纯前台（`site-*.spec.cjs`，无登录、桌面+移动双 project），
 * 这份跑穿透后台的端到端用例（`uat-*.spec.cjs`），靠 global-setup 先登录一次并复用 cookie。
 *
 * 本地：
 *   php artisan serve --port=8123
 *   BASE_URL=http://localhost:8123 npx playwright test uat-phase12 --config=playwright.config.uat.cjs
 *
 * BASE_URL 必须显式给：global-setup.cjs 自己读 process.env.BASE_URL，
 * 拿不到就回落到 8099，会登到另一个端口去。
 *
 * 不进 CI（Playwright 由本人不定时手跑，见 CLAUDE.md 的测试策略）。
 */
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/e2e',
    testMatch: 'uat-*.spec.cjs',
    globalSetup: './tests/e2e/global-setup.cjs',
    use: {
        ...devices['Desktop Chrome'],
        baseURL: process.env.BASE_URL || 'http://localhost:8123',
        storageState: '/tmp/pw-uat/auth.json',
        headless: true,
        // 后台是 Livewire 页面，动作后常有一次网络往返，默认 5s 的断言超时偏紧
        actionTimeout: 15000,
    },
    // 后台用例普遍串行依赖（建页面 → 发布 → 改 slug），并发跑会互相撞数据
    workers: 1,
    reporter: 'list',
    timeout: 90000,
});
