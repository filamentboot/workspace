# 升级指南

本文档面向 `filamentboot/filamentboot-site` 包的消费者，列出各版本间的不兼容变更（Breaking Changes）及升级操作步骤。

---

## v0.13 → v0.14 升级指南

### 摘要

v0.13 是本包首次正式发布。v0.14 是九期"完善包"的成果，把首次发布后陆续暴露出的打包与命名缺口一次性收口，其中两项是不兼容变更。

---

### Breaking Change 1：资讯摘要字段改名 `excerpt_zh` → `description_zh`

**影响：** 在覆盖视图、自定义查询或报表脚本里直接引用过 `$article->excerpt_zh` / `excerpt_zh` 列的用户。

**背景：** 资讯此前是六类内容里唯一用 `excerpt_zh` 而不是 `description_zh` 的例外。v0.14 统一改名，硬改，**不留 accessor 兼容**——迁移后继续读 `excerpt_zh` 不会报错，会静默拿到 `null`（Blade `??` 不会提示）。

**操作：**

1. 升级依赖后执行 `php artisan migrate`。迁移
   `2026_08_13_150000_rename_excerpt_zh_to_description_zh_in_site_news_articles_table.php`
   会重命名列，**并同步改写 `site_revisions` 表里已存资讯修订快照 payload 的 JSON 键**——
   只有这一步做对，历史版本的"回滚"功能才不会在改名前的旧快照上失效。
2. 搜索项目里对 `excerpt_zh` 的引用（覆盖视图、自定义 Blade、报表 SQL），全部改成
   `description_zh`。

```bash
grep -rn 'excerpt_zh' resources/views/vendor/filamentboot-site/ app/ 2>/dev/null
```

无输出即说明没有需要手工改的引用。

---

### Breaking Change 2：删除 `filamentboot-site-migrations` publish tag

**影响：** 曾执行过 `php artisan vendor:publish --tag=filamentboot-site-migrations` 的用户。

**背景：** 本包的 `loadMigrationsFrom()` 一直自动加载全部迁移，发布这个 tag 只会让
`migrate` 重复扫描同一批文件。v0.13 时这个 tag 就已经不该存在，v0.14 正式移除。

**操作：** 若项目 `database/migrations/` 下有一批日期前缀是发布时间、内容与
`vendor/filamentboot/filamentboot-site/database/migrations/` 重复的文件，删除它们即可，
本包自带的自动加载不受影响。

---

### 其他变更（非 Breaking，建议一并处理）

- **`composer.json` 新增 `"ext-intl": "*"` 到 `require`**：`SiteProductResource`/
  `SitePackageResource` 的价格列一直依赖 `ext-intl`，此前只在部署脚本里用
  `--ignore-platform-req=ext-intl` 绕过，实际运行时缺了这个扩展仍会 500。升级前确认
  PHP 已装 `intl` 扩展。
- **演示数据不再随"插件市场"一键安装无条件播种**：需要演示内容改用命令行
  `filamentboot-site:install --with-demo`，或后台「网站设置」页新增的"种入演示数据"
  按钮。
- **新增 `php artisan filamentboot-site:doctor`**：升级后建议跑一次，七项检查覆盖
  插件启用、迁移、结构性种子、关键路由、内容配置、首页响应头、媒体磁盘。
- **新增 `filamentboot-site-tests` publish tag**：需要发布 Playwright 冒烟测试到项目里
  时用 `vendor:publish --tag=filamentboot-site-tests`（默认不随安装自动发布）。

---

### composer 约束建议

```json
{
    "require": {
        "filamentboot/filamentboot-site": "^0.14"
    }
}
```

---

## 完整升级清单

- [ ] 执行 `composer update filamentboot/filamentboot-site`
- [ ] 执行 `php artisan migrate`（含 `excerpt_zh` 改名迁移，Breaking Change 1）
- [ ] `grep` 项目里残留的 `excerpt_zh` 引用并改成 `description_zh`
- [ ] 删除此前发布过的 `filamentboot-site-migrations` 迁移文件（Breaking Change 2，如适用）
- [ ] 确认 PHP 已装 `ext-intl` 扩展
- [ ] 更新 `composer.json` 约束为 `^0.14`
- [ ] 跑一次 `php artisan filamentboot-site:doctor` 确认健康

---

更多信息请参阅：

- [README](README.md)
- [变更记录](CHANGELOG.md)
