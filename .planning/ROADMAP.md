### Phase 9: 编辑器插件

**Goal**: 开发富文本编辑器和 Markdown 编辑器两个 Filament 表单组件插件，支持图片上传（与媒体库联动）、代码高亮、自定义工具栏

**Depends on**: Phase 1（基础架构），可与 Phase 5/6/8 并行
**Requirements**: EDITOR-01, EDITOR-02
**Work estimate**: 约 8-12h（富文本 4-6h + Markdown 4-6h）

**Success Criteria**:

1. Filament 表单中使用 `RichEditor::make('content')` 即可渲染富文本编辑器（Tiptap（内置 RichEditor 增强）），支持图片拖拽上传到媒体库、表格、链接、代码块；并可在插件市场一键替换为 wangEditor 独立插件包（D-09-03）
2. Filament 表单中使用 `MarkdownEditor::make('content')` 即可渲染 Markdown 编辑器，支持实时预览、工具栏（加粗/斜体/标题/列表/链接/图片/代码）、图片上传到媒体库
3. 两个编辑器均支持配置工具栏按钮、上传磁盘、文件大小限制，与现有 `UploadSettings` 联动
4. 编辑器输出的内容在详情页正确渲染（HTML 安全过滤 / Markdown 转 HTML）

**Plans**: 4 plans
Plans:
**Wave 1**

- [x] 09-01-PLAN.md — 富文本包骨架 + RichEditorField（Tiptap 增强：动态磁盘+UploadSettings 联动）+ RichEditorPurifier 保存前 XSS 过滤 + Wave 0 测试（EDITOR-01）
- [x] 09-03-PLAN.md — Markdown 包完整：MarkdownEditorField（EasyMDE 增强：分屏预览+代码高亮+动态磁盘）+ MarkdownRenderer（CommonMark+XSS 展示时过滤）+ Wave 0 测试（EDITOR-02）

**Wave 2** *(blocked on 09-01 — 复用 UploadValidator + Phase 8 上传协议)*

- [x] 09-02-PLAN.md — wangEditor 独立插件包（packages/filament-admin-wang-editor/）：独立 composer.json + WangEditorPlugin/ServiceProvider + WangEditorField custom field + Blade/Alpine 桥接 + 图片上传路由（接 UploadValidator 三重校验）+ FilamentAsset 注册（EDITOR-01，D-09-03/D-09-04）

**Wave 3** *(blocked on 09-01 + 09-02 + 09-03)*

- [x] 09-04-PLAN.md — Monorepo 集成（根 composer.json repositories/require/autoload，含 rich-editor / markdown-editor / wang-editor 三包 + config/purifier.php richeditor 白名单）+ 集成测试（plugin:scan 发现 + 类加载 + 渲染过滤）（EDITOR-01/02）

---

### Phase 10: 官网插件

**Goal**: 开发官网插件（`laravelstack/filament-admin-site`），让客户可在后台管理并发布自己公司的前台官网（页面、文章、产品），作为插件市场的示范旗舰插件

**Depends on**: Phase 6（插件市场契约），Phase 8（图片上传磁盘），Phase 9（富文本编辑器）
**Requirements**: SITE-01, SITE-02, SITE-03, SITE-04
**Work estimate**: 约 10-15h

**Success Criteria**:

1. `packages/filament-admin-site/` 独立 Composer 包，按 Phase 6 `extra.filament-admin` 契约构建，可被 `plugin:scan` 发现并在后台启停
2. 后台提供页面（Page）、文章（Article）、产品（Product）的 Filament Resource CRUD，含分类、标签、发布状态、置顶
3. 前台路由在插件启用时自动接管根域 `/`（首页）与 `/{slug}`（单页/文章/产品详情），禁用时不影响现有 routes/web.php
4. 前台模板采用 Blade + Tailwind CSS 响应式设计 + Livewire 按需交互，SEO meta（TDK）直出
5. 支持多套可切换主题（整体皮肤切换，不只配色）

**Plans**: TBD

---

### Phase 11: 代码整理收尾

**Goal**: 清理累积的技术债、修复已登记 bug、将代码质量提升到发版标准

**Depends on**: Phase 1~10 全部完成
**Requirements**: CLEANUP-01, CLEANUP-02, CLEANUP-03
**Work estimate**: 约 15-20h

**Success Criteria**:

1. PHPStan Level 6 零错误
2. Pint 格式零警告，Feature 层测试覆盖率 ≥ 80%
3. 所有已登记 bug（CR-01 等，含 parent_id=0 根菜单保存 bug）修复完毕

**Plans**: TBD（执行前用 gsd-plan-phase 读已登记缺陷账目生成计划）

---

### Phase 12: 发版与仓库整理

**Goal**: 打 v0.5.0 tag，完成 Packagist 发版、subtree split 子包同步、CHANGELOG 更新

**Depends on**: Phase 11
**Requirements**: RELEASE-07
**Work estimate**: 约 1-2h

**Success Criteria**:

1. v0.5.0 tag 推送到 GitHub 和 Gitee
2. Packagist 自动更新，`composer require laravelstack/filament-admin:^0.5` 可安装
3. subtree split 各子包仓库同步
4. CHANGELOG 更新，README 版本号更新

**Plans**: TBD

---
