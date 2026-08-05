// @ts-check
/**
 * Phase 03 UAT — Playwright E2E 测试
 *
 * FEAT-01: 超管模拟登录 — 顶栏横幅 + session 切换 + activity_log 写入
 * FEAT-02: /docs/api — Scramble OpenAPI 3.0 文档页可访问且仅含 api/v1 端点
 */

const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8099';

function mysql(sql) {
    return execSync(
        `mysql -h 127.0.0.1 -P 3380 -u root -p123456 filamentboot -sN -e "${sql}" 2>/dev/null`,
        { encoding: 'utf-8' }
    ).trim();
}

// ─────────────────────────────────────────────
// FEAT-01: 超管模拟登录
// ─────────────────────────────────────────────
test.describe('FEAT-01: 超管模拟登录', () => {
    test.beforeEach(() => {
        // 清除本次测试前的模拟登录 activity log，避免计数干扰
        mysql("DELETE FROM activity_log WHERE event IN ('impersonate.enter','impersonate.leave') AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    });

    test.afterEach(async ({ page }) => {
        // 若测试结束时仍处于 impersonate 状态，强制结束，避免污染后续测试
        try {
            const leave = page.locator('a[href*="filament-impersonate/leave"]');
            if (await leave.count() > 0) {
                await leave.click();
                await page.waitForLoadState('networkidle');
            }
        } catch (_) { /* 忽略清理失败 */ }
    });

    // 按钮通过 wire:click="mountAction('impersonate', ...)" 定位
    // 注：按钮文字显示原始翻译 key "filament-impersonate::action.label"，
    //     说明 zh_CN 翻译文件缺少 action.label 键（已知 bug，待 Phase 11 修复）
    test('非超管用户行显示模拟登录按钮，超管行不显示（super_admin 行无按钮）', async ({ page }) => {
        await page.goto(`${BASE_URL}/admin/admin-users`);
        await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });
        await page.waitForTimeout(1000);

        const allRows = await page.locator('tbody tr').count();
        const impersonateBtns = await page.locator('[wire\\:click*="impersonate"]').count();

        // 超管行（super_admin role）不应有按钮，其余行应有
        // 验证：按钮数 > 0 且 < 总行数（超管行被排除）
        expect(impersonateBtns).toBeGreaterThan(0);
        expect(impersonateBtns).toBeLessThan(allRows);
    });

    // 合并横幅验证 + activity_log 验证到一个测试，避免跨测试 session 失效问题
    // （leave 操作会触发 Session::regenerate()，使后续测试的旧 cookie 失效）
    test('点击模拟登录后横幅显示「正在模拟」，结束后横幅消失且 activity_log 写入两条记录', async ({ page }) => {
        await page.goto(`${BASE_URL}/admin/admin-users`);
        await page.waitForSelector('[wire\\:snapshot]', { timeout: 20000 });
        await page.waitForTimeout(1000);

        const impersonateBtn = page.locator('[wire\\:click*="impersonate"]').first();
        await expect(impersonateBtn).toBeVisible({ timeout: 10000 });

        await impersonateBtn.click();
        await page.waitForLoadState('networkidle');

        // 点击后会跳转（通常到根路径），需导航回 /admin/ 才能看到横幅
        await page.goto(`${BASE_URL}/admin/`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // 验证顶栏横幅存在且含「正在模拟」文字
        const banner = page.locator('#impersonate-banner');
        await expect(banner).toBeVisible({ timeout: 10000 });
        await expect(banner).toContainText('正在模拟');

        // 横幅含「结束模拟」直链
        const leaveLink = page.locator('a[href*="filament-impersonate/leave"]').first();
        await expect(leaveLink).toBeVisible({ timeout: 5000 });

        // 点击「结束模拟」
        await leaveLink.click();
        await page.waitForLoadState('networkidle');

        // 横幅消失，回到超管
        await expect(page.locator('#impersonate-banner')).not.toBeVisible({ timeout: 10000 });

        // 等 ImpersonationListener 写入 DB
        await page.waitForTimeout(1000);

        const enterCount = mysql("SELECT COUNT(*) FROM activity_log WHERE event='impersonate.enter' AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        const leaveCount = mysql("SELECT COUNT(*) FROM activity_log WHERE event='impersonate.leave' AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");

        expect(parseInt(enterCount)).toBeGreaterThanOrEqual(1);
        expect(parseInt(leaveCount)).toBeGreaterThanOrEqual(1);
    });
});

// ─────────────────────────────────────────────
// FEAT-02: /docs/api Scramble OpenAPI 文档页
// ─────────────────────────────────────────────
test.describe('FEAT-02: /docs/api Scramble 文档页', () => {
    test('/docs/api 返回 200 并渲染文档 UI', async ({ page }) => {
        const response = await page.goto(`${BASE_URL}/docs/api`);
        expect(response?.status()).toBe(200);

        // 页面有内容（Stoplight Elements 或 Scalar 等 UI 会渲染）
        await page.waitForLoadState('domcontentloaded');
        const bodyText = await page.content();
        // 包含 OpenAPI 相关特征字符串
        expect(bodyText.toLowerCase()).toMatch(/openapi|swagger|scalar|stoplight|redoc/);
    });

    test('/docs/api.json 返回 OpenAPI 3.x JSON，servers 含 api/v1，paths 含三个管理端点', async ({ page }) => {
        const response = await page.goto(`${BASE_URL}/docs/api.json`);
        expect(response?.status()).toBe(200);

        const json = await response?.json();
        expect(json).toHaveProperty('openapi');
        expect(json.openapi).toMatch(/^3\./);

        // Scramble 将 api_path 作为 servers base URL，paths 中不含该前缀
        // 例：servers[0].url = "http://localhost/api/v1"，paths = ["/admin/login", ...]
        const serverUrl = (json.servers?.[0]?.url ?? '');
        expect(serverUrl).toMatch(/api\/v1/);

        const paths = Object.keys(json.paths ?? {});
        // 三个管理端点（相对于 api/v1 base）
        expect(paths).toContain('/admin/login');
        expect(paths).toContain('/admin/me');
        expect(paths).toContain('/admin/logout');

        // 不含 Filament 内部路由
        const filamentPaths = paths.filter(p => p.includes('livewire') || p.includes('filament'));
        expect(filamentPaths).toHaveLength(0);
    });

    test('/docs (Scribe) 共存不受影响', async ({ page }) => {
        const response = await page.goto(`${BASE_URL}/docs`);
        // Scribe 文档页应返回 200
        expect(response?.status()).toBe(200);
    });
});
