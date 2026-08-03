// @ts-check
/**
 * 官网前台专用 Playwright 配置（无需后台认证）
 *
 * 本地：php artisan serve --port=8123 后
 *       npx playwright test site- --config=playwright.config.site.cjs
 * 线上：BASE_URL=https://www.qkznj.com npx playwright test site- --config=playwright.config.site.cjs
 *
 * 桌面与移动两套 project，移动端用于验证固定悬浮 CTA 的避让与可点击性。
 */
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/e2e',
    testMatch: 'site-*.spec.cjs',
    use: {
        baseURL: process.env.BASE_URL || 'http://127.0.0.1:8123',
        headless: true,
    },
    projects: [
        {
            name: 'desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
        },
        {
            // 用 chromium 内核的 Pixel 5 而非 iPhone（WebKit）：
            // 国内主要移动入口（微信内置浏览器、各家 Android 浏览器）都是 chromium 内核，
            // 且不必额外下载 WebKit 引擎。需要覆盖 iOS Safari 时另跑 webkit project。
            name: 'mobile',
            use: { ...devices['Pixel 5'] },
        },
    ],
    reporter: 'list',
    timeout: 30000,
});
