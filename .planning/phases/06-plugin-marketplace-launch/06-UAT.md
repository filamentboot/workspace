---
status: passed
phase: 06-plugin-marketplace-launch
source: [06-VERIFICATION.md]
started: "2026-06-11T00:00:00.000Z"
updated: "2026-06-12T16:00:00Z"
decision: "SC-1 修复方向 = B（AdminNavigationBuilder 合并面板已注册插件导航）"
gap_closure_note: "06-05 已闭合 SC-1/SC-3 代码缺口；06-06 已闭合 CR-01/CR-04；12/12 must-haves 自动验证通过"
playwright_note: "UAT-1/UAT-2 已通过 Playwright 6/6 自动验证（tests/e2e/uat-phase06.spec.cjs）；SC-3 语义由开发者确认可接受（幂等重跑）"
---

## Current Test

[playwright e2e passed 6/6 — 2026-06-12]

## Tests

### 1. 插件列表页面可达 + 启停操作（SC-1 / PLUGIN-01）
expected: 插件列表页面可达，插件记录可见，启/禁用 Action 可触发。
result: passed
method: playwright-e2e
evidence: |
  - tests/e2e/uat-phase06.spec.cjs UAT-1 全组通过（3/3）
  - 截图确认：列表页正常渲染，NAV测试插件行显示 View + 启用 按钮
  - DB 初始状态 is_enabled=0 确认，"启用"按钮在行动作列中可见
  - 修复附带问题：Filament 5 使用 `Filament\Actions\Action`（非 `Filament\Tables\Actions\Action`），
    已修复 PluginResource.php:13 的 500 错误

### 2. 初始化进度 wire:poll 实时刷新（SC-4 / PLUGIN-04）
expected: ViewPlugin 详情页可达，页面 HTML 含 `wire:poll.2000ms="refreshInitProgress"`，初始化按钮与进度区块可见。
result: passed
method: playwright-e2e
evidence: |
  - tests/e2e/uat-phase06.spec.cjs UAT-2 全组通过（3/3）
  - HTML 中确认：`<div wire:poll.2000ms="refreshInitProgress" class="mt-4">`
  - 截图确认：ViewPlugin 页面渲染"初始化进度"区块 + "初始化"按钮（右上角）
  - 状态 pending 时显示"暂无进度日志。"，符合预期

### 3. 初始化重试语义确认（SC-3 语义偏差 / D-06-12）
expected: 确认"整体幂等重跑"（migrate/publish/seed 均幂等）是否可替代"显式跳过已成功步骤"。
result: accepted
method: human-decision
decision: |
  开发者确认"幂等重跑 = 事实上跳过已成功步骤"语义可接受。
  migrate/publish/seed 操作均已设计为幂等，重跑安全。
  无需补步骤级状态记录。此项关闭。

## Summary

total: 3
passed: 3
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

- truth: "启用已安装插件后其导航/Resource/Page 出现在后台菜单，禁用后消失（SC-1/PLUGIN-01）；以及插件市场/已安装插件页面在运行后台中可达（SC-2/3/4/8 前置）"
  status: failed
  reason: "User reported: 重新进入后台看不到「插件市场」分组，已安装插件管理页不可达。"
  severity: blocker
  test: 1
  artifacts:
    - app/Providers/Filament/AdminPanelProvider.php
    - packages/filament-admin/src/Services/AdminNavigationBuilder.php
    - app/Filament/Resources/PluginResource.php
    - database/seeders (menus 表接线来源)
  missing:
    - "AdminPanelProvider 注册 PluginResource（->resources([...]) 或 discoverResources()），使其生成路由【UAT 期间已临时手动打补丁，待正式纳入计划+测试】"
    - "menus 表「官方市场」(id=11) route_name=filament.admin.pages.marketplace；「扩展清单」(id=12) route_name 指向 PluginResource index 路由【UAT 期间已临时手动接线】"
    - "回归测试：断言 PluginResource 已注册且 index/view 路由存在；断言「插件市场」导航组在 super_admin 下渲染出子项"
  confirmed_deeper_gap: |
    经以超管身份调用 AdminNavigationBuilder::build() 实测确认：启用演示插件前后侧边栏分组
    完全一致，插件声明的导航项（navigationItems/Resource/Page）不会出现。根因：->navigation()
    闭包用 AdminNavigationBuilder 完全接管侧边栏，该 builder 只读 menus 表，忽略 Filament 面板
    中插件动态注册（$panel->plugin()）贡献的导航。SC-1「启用插件→菜单出现，禁用→消失」在当前
    DB 驱动导航架构下不成立——这是 PLUGIN-01 动态注册与既有 DB 导航两套机制未打通，需设计决策：
    (A) 启用插件时同步写入/移除 menus 行；或 (B) AdminNavigationBuilder 合并面板已注册插件导航。
    两种方案均需回归测试覆盖。SC-2/3/4（初始化进度/重试 UI）依赖 ViewPlugin 可达，本次接线后
    路由已通，但其真实运行时行为仍需在修复后复测。
  chosen_direction: |
    用户已选 B：AdminNavigationBuilder 在 menus 表分组基础上，合并 Filament 面板中已启用插件
    贡献的 Resource/Page/navigationItems（去重、按权限过滤、合理排序），使第三方插件启用即显、
    禁用即消，无需感知 menus 表。gap 计划须含：(1) builder 合并逻辑（package 层
    AdminNavigationBuilder）；(2) AdminPanelProvider 注册 PluginResource；(3) 回归测试——
    断言启用插件后其导航项出现在 build() 结果、禁用后消失，且 PluginResource index/view 路由存在；
    (4) 注意与现存 menus 行（官方市场/扩展清单）去重，避免合并后重复。
  env_note: |
    UAT 期间附带修复：dev 库 plugins 表过时（缺 deleted_at）。已 drop + 重跑迁移修复。
    根因隐患：迁移 create_plugins_table 的幂等守卫 if (Schema::hasTable('plugins')) return; 会在
    旧表存在但 schema 不同时静默跳过、掩盖 schema 漂移——建议 plan 阶段评估是否加显式列校验或文档警示。
