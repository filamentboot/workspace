# AGENTS.md — wiki/

`wiki/` 是 FilamentAdmin 的**对外官方文档目录**，面向安装和使用本包的开发者。

## 定位

- **受众**：安装 `filament-admin/filament-admin` 的开发者（独立开发者、小团队）
- **性质**：公开发布，可同步到 GitHub Wiki 或文档站
- **口径**：只写**已落地**的能力，区分「已完成 / 已铺垫 / 待开发」，禁止把规划中的功能写成已完成

## 目录结构

```text
wiki/
├── AGENTS.md              # 本文件，说明 wiki/ 的写作规范
├── index.md               # 首页：项目一句话介绍 + 核心能力列表 + 快速导航
├── installation.md        # 安装指南：环境要求、安装步骤、初始化
├── quickstart.md          # 快速开始：从安装到首次登录
├── guide/                 # 详细使用指南
│   ├── authentication.md  # 认证体系（登录、2FA、登录日志）
│   ├── permissions.md     # 角色权限（RBAC、数据权限）
│   ├── menus.md           # 菜单管理
│   ├── departments.md     # 部门管理
│   ├── logs.md            # 日志（操作日志、登录日志）
│   └── plugins.md         # 插件市场使用
├── reference/             # 技术参考
│   ├── plugin-api.md      # FilamentAdminPlugin::make() 完整 API
│   ├── config.md          # filament-admin.php 配置项说明
│   ├── artisan.md         # filament-admin:publish 等 Artisan 命令
│   └── extending.md       # 继承扩展指南（Model、Resource 发布 stub）
└── changelog.md           # 版本更新记录
```

## 写作规范

- **语言**：简体中文，技术术语（Composer、Guard、Stub 等）保留英文
- **状态标注**：未落地的能力必须标注 `（待开发）`，铺垫完成但未完整的标注 `（已铺垫）`
- **代码示例**：所有代码块必须可运行，与当前版本对齐
- **版本对齐**：每次修改前核对对应代码和测试是否已落地

## 不属于 wiki/ 的内容

| 内容 | 正确位置 |
|------|---------|
| 产品需求文档（PRD） | `docs/prd/` |
| 开发规格和实施计划 | `docs/superpowers/` |
| 项目开发规划和梳理 | `doc/` |
| 部署运维相关文档 | `wiki/guide/` 或单独的运维文档 |
