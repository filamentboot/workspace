<?php

/**
 * Wave 0 占位：导出授权测试（FINAL-04）
 *
 * 待 Plan 03 实现后转为真实断言：
 * - 无 export_admin_user 权限的用户触发 ExportAction 被拒（Gate::check 返回 false）
 * - 拒绝时写入审计日志
 */

it('无 export_admin_user 权限用户触发导出被拒', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 03 实现 — 断言无 export_admin_user 权限用户触发 ExportAction 时 authorize() 抛出 AuthorizationException（FINAL-04）');
});

it('导出被拒时写入审计日志', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 03 实现 — 断言导出授权拒绝时 activity_log 表记录拒绝事件（FINAL-04）');
});
