/** 全局登录 setup：登录一次，保存 cookie 到 /tmp/pw-uat/auth.json，供所有测试复用 */
const { chromium } = require('@playwright/test');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8099';

module.exports = async function globalSetup() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    await page.goto(`${BASE_URL}/admin/login`);
    await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });

    const loginField = page.locator('input[id="form.login"]').first();
    const passwordField = page.locator('input[type="password"]').first();

    await loginField.waitFor({ timeout: 10000 });
    await loginField.click();
    await loginField.fill('admin@example.com');
    await passwordField.click();
    await passwordField.fill('password');

    await page.waitForTimeout(500);
    const submitBtn = page.locator('button[type="submit"]').first();
    await submitBtn.click();

    await page.waitForURL((url) => !url.toString().includes('/login'), { timeout: 30000 });
    console.log('\n[global-setup] ✓ 登录成功，保存 auth state');

    await page.context().storageState({ path: '/tmp/pw-uat/auth.json' });
    await browser.close();
};
