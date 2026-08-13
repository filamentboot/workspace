// @ts-check
/**
 * 悬浮询盘面板·可配置额外问题回归（3.5 期 F 段）
 *
 * `filamentboot-site.contact.panel_fields`（户型 / 建筑面积 / 装修阶段）2.5 期批次 3
 * 加的，此前只读过代码与 config，从没在真实浏览器里选过下拉、走过一次完整提交。
 * 下拉选项来自 config 解析（`ContactFormBlock::normalizedFields()`），选项文本改错
 * 或类型写错都是**静默丢字段**（见该方法「重名 / 下拉无选项就整条丢掉」的注释），
 * 属于本项目一贯的静默失效族，值得钉住。
 *
 * 只在 desktop project 跑：本文件唯一一条用例会真的提交一次表单，消耗
 * `ContactSubmission` 的 IP 限流额度（本站本地配置 5 次/10 分钟）。跑两个 project
 * 就是两次提交，没必要——面板本身在两种视口下用的是同一份 DOM。
 *
 * ## 这条用例依赖下游自己配置的站点内容，不是包的默认行为
 *
 * `filamentboot-site.contact.panel_fields` 包内默认是空数组（见 config 文件里
 * 那段注释：「这是站点内容，不是包的行为」），**本仓库自己的生产配置也是空的**
 * ——filamentboot-web 卖的是软件包本身，没有理由问「户型」。这条用例断言的
 * 「户型/建筑面积/装修阶段」三个问题是**装修行业客户会配的典型示例**，不会在
 * 默认安装或本站上出现。用例检测到面板里没有任何 `<select>` 时会自动
 * `test.skip`，不是误判成通过——下游配置了对应字段（示例见 config 注释里的
 * `户型` 那一条）之后再跑这条，用它验证下拉渲染与提交流程。
 *
 * 运行：
 *   php artisan serve --port=8124
 *   npx playwright test site-contact-panel-fields --config=playwright.config.site.cjs --project=desktop
 */
const { test, expect } = require('@playwright/test');

test.describe('询盘面板的可配置下拉字段', () => {
    test('户型/装修阶段渲染为下拉且选项与 config 一致，选完能提交成功', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', '避免双 project 重复消耗提交限流额度');

        const errors = [];
        page.on('pageerror', (e) => errors.push(String(e)));
        page.on('console', (msg) => {
            if (msg.type() === 'error') errors.push(msg.text());
        });

        await page.goto('/');
        await page.locator('[data-contact-trigger="floating"]').click();

        const panel = page.locator('#contact-panel');
        await expect(panel).toBeVisible();

        const selectCount = await panel.locator('select').count();
        test.skip(
            selectCount === 0,
            '未检测到面板里的额外问题下拉——filamentboot-site.contact.panel_fields 未配置（包默认与本站当前配置均是空数组，属于站点内容留白，见上方说明），跳过'
        );

        // 三个额外问题按 config 声明顺序出现：户型（下拉）→ 建筑面积（文本）→ 装修阶段（下拉）
        const labels = panel.locator('label');
        await expect(labels.filter({ hasText: '户型' })).toBeVisible();
        await expect(labels.filter({ hasText: '建筑面积' })).toBeVisible();
        await expect(labels.filter({ hasText: '装修阶段' })).toBeVisible();

        const layoutSelect = panel.locator('select').first();
        const stageSelect = panel.locator('select').nth(1);

        await expect(layoutSelect.locator('option')).toHaveCount(9); // 「请选择」+ 8 个选项
        await expect(stageSelect.locator('option')).toHaveCount(5); // 「请选择」+ 4 个选项

        await layoutSelect.selectOption({ label: '三室两厅' });
        await stageSelect.selectOption({ label: '水电阶段' });

        // formKey 固定为 panel（floating-contact.blade.php 写死），id 因此稳定可依赖——
        // 不能用 input[type="text"] 选择器，蜜罐字段（同为 type="text"）在 DOM 里排在姓名前面
        await panel.locator('#contact-form-panel-name').fill('测试访客-F段验收');
        await panel.locator('#contact-form-panel-phone').fill('13800000000');

        // MIN_FILL_SECONDS=3：低于这个耗时会被 ContactSubmission 判为疑似脚本，静默丢弃
        // （对外仍回 200，前端看不出区别——所以这里必须真等，不能只是断言请求发出）
        await page.waitForTimeout(3500);

        await panel.getByRole('button', { name: '提交留言' }).click();

        await expect(panel.getByText('感谢您的留言！')).toBeVisible({ timeout: 10000 });
        await expect(panel.getByText('我们会尽快与您联系。')).toBeVisible();

        expect(errors, `控制台/页面报错：${errors.join('; ')}`).toEqual([]);
    });
});
