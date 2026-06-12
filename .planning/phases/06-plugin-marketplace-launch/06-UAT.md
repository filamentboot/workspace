---
status: testing
phase: 06-plugin-marketplace-launch
source: [06-VERIFICATION.md]
started: "2026-06-11T00:00:00.000Z"
updated: "2026-06-12T14:35:00Z"
decision: "SC-1 修复方向 = B（AdminNavigationBuilder 合并面板已注册插件导航）"
gap_closure_note: "06-05 已闭合 SC-1/SC-3 代码缺口；06-06 已闭合 CR-01/CR-04；12/12 must-haves 自动验证通过，3 项运行时行为待人工复测"
---

## Current Test

[testing complete]

## Tests

### 1. 插件启停实时控制后台菜单（SC-1 / PLUGIN-01）
expected: 启用插件后，其导航/Resource/Page/Widget 出现在左侧菜单；禁用后立即消失。注意需确认缓存 `plugins.enabled_list`（30s TTL）刷新后生效或启停时已主动清缓存。
result: issue
reported: "重新进入后台，侧边栏根本看不到「插件市场」分组；已安装插件管理页也无法到达。"
severity: blocker
root_cause: |
  两处运行时接线缺失，导致整个插件 UI 在运行的后台中不可达（单测全绿因覆盖盲区）：
  (1) AdminPanelProvider 从未注册 PluginResource——既无 ->resources([PluginResource::class])
      也无 discoverResources()，仅显式注册了 MarketplacePage。结果 PluginResource/ListPlugins/
      ViewPlugin 无任何路由（route:list 无 filament.admin.resources.plugins.*），启停与初始化
      页彻底打不开。
  (2) 面板用 DB 驱动导航 AdminNavigationBuilder（读 menus 表）。「插件市场」组(id=10)的子项
      官方市场(id=11)、扩展清单(id=12) 的 url 与 route_name 均为 null，被 toNavigationItem 的
      blank(url) 过滤 → 组内无项 → 整组被 continue 跳过 → 侧边栏不显示「插件市场」。
  覆盖盲区：PluginPanelRegistrationTest 仅用反射单测 registerEnabledPlugins 三分支；
  ListPlugins/ViewPlugin 为隔离单测。无任何测试断言 PluginResource 被面板注册/可路由，
  也无测试断言 menus 导航行被正确接线（route_name 指向真实 Filament 路由）。

### 2. 初始化进度 wire:poll 实时刷新（SC-4 / PLUGIN-04）
expected: 在插件详情页（ViewPlugin）触发"初始化"后，页面通过 `wire:poll.2000ms="refreshInitProgress"` 每 2 秒轮询并实时显示当前步骤/进度；初始化失败后出现"重试初始化"按钮且点击可重试。
result: blocked
blocked_by: prior-gap
reason: "依赖 Test 1 的 SC-1 修复（PluginResource 注册 + 导航打通）落地后才能在真实可达的 ViewPlugin 页上复测。本次接线后路由已通，但完整运行时行为留待 gap 修复后验证。"

### 3. 初始化重试语义确认（SC-3 语义偏差 / D-06-12）
expected: ROADMAP 原文要求"重试时跳过已成功的步骤"，当前实现为"整体幂等重跑"（migrate/publish/seed 均幂等，重跑安全但不显式跳过已成功步骤）。请开发者确认该语义差异是否可接受；若必须显式跳过，需补步骤级状态记录。
result: blocked
blocked_by: open-decision
reason: "属设计语义确认，与 SC-1 的 ViewPlugin 可达性绑定；并入 gap 阶段一并裁决（保留幂等重跑 or 补步骤级跳过）。"

## Summary

total: 3
passed: 0
issues: 1
pending: 0
skipped: 0
blocked: 2

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
