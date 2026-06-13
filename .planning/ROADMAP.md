### Phase 9: 编辑器插件

**Goal**: 开发富文本编辑器和 Markdown 编辑器两个 Filament 表单组件插件，支持图片上传（与媒体库联动）、代码高亮、自定义工具栏

**Depends on**: Phase 1（基础架构），可与 Phase 5/6/8 并行
**Requirements**: EDITOR-01, EDITOR-02
**Work estimate**: 约 8-12h（富文本 4-6h + Markdown 4-6h）

**Success Criteria**:

1. Filament 表单中使用 `RichEditor::make('content')` 即可渲染富文本编辑器（TinyMCE 或 Tiptap），支持图片拖拽上传到媒体库、表格、链接、代码块
2. Filament 表单中使用 `MarkdownEditor::make('content')` 即可渲染 Markdown 编辑器，支持实时预览、工具栏（加粗/斜体/标题/列表/链接/图片/代码）、图片上传到媒体库
3. 两个编辑器均支持配置工具栏按钮、上传磁盘、文件大小限制，与现有 `UploadSettings` 联动
4. 编辑器输出的内容在详情页正确渲染（HTML 安全过滤 / Markdown 转 HTML）

**Plans**: 4 plans
Plans:
**Wave 1**

- [ ] 09-01-PLAN.md — 富文本包骨架 + RichEditorField（Tiptap 增强：动态磁盘+UploadSettings 联动）+ RichEditorPurifier 保存前 XSS 过滤 + Wave 0 测试（EDITOR-01）
- [ ] 09-03-PLAN.md — Markdown 包完整：MarkdownEditorField（EasyMDE 增强：分屏预览+代码高亮+动态磁盘）+ MarkdownRenderer（CommonMark+XSS 展示时过滤）+ Wave 0 测试（EDITOR-02）

**Wave 2** *(blocked on 09-01 — 共享 rich-editor 包)*

- [ ] 09-02-PLAN.md — wangEditor 可替换组件：WangEditorField custom field + Blade/Alpine 桥接 + 图片上传路由（接 UploadValidator 三重校验）+ FilamentAsset 注册（EDITOR-01）

**Wave 3** *(blocked on 09-01 + 09-02 + 09-03)*

- [ ] 09-04-PLAN.md — Monorepo 集成（根 composer.json repositories/require/autoload + config/purifier.php richeditor 白名单）+ 集成测试（plugin:scan 发现 + 类加载 + 渲染过滤）（EDITOR-01/02）

---
