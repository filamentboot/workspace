---
plan: 01-08
phase: 01-package-release-compliance
status: complete
gap_closure: true
requirements:
  - COMPLY-04
  - COMPLY-05
completed_at: 2026-06-10
---

## Summary

关闭 01-VERIFICATION.md 标记的 2 个 gaps（COMPLY-04/05 PARTIAL），让包 CI 在 PHP 8.3 matrix 上的 `Pint 代码风格检查` 与 `PHPStan 静态分析` 两个步骤通过。

## 变更详情

### 修改 1：PackageBoundaryTest.php 第 44 行（concat_space 修复）

**文件：** `packages/filament-admin/tests/Unit/PackageBoundaryTest.php`

```diff
-        $rootSrc = dirname(__DIR__, 4) . '/src';
+        $rootSrc = dirname(__DIR__, 4).'/src';
```

**原因：** Laravel pint preset 的 `concat_space` 规则要求 `.` 运算符两侧无空格。ci.yml 的 `Pint 代码风格检查` 步骤无 `continue-on-error`，PHP 8.3/8.4 matrix 均会因此失败。

### 修改 2：PackageMetadataTest.php loadComposerJson() 返回类型（phpstan 泛型修复）

**文件：** `packages/filament-admin/tests/Unit/PackageMetadataTest.php`

```diff
     /**
      * 加载包的 composer.json 并解析为数组。
+     *
+     * @return array<string, mixed>
      */
     private function loadComposerJson(): array
```

**原因：** phpstan level 6 的 `missingType.iterableValue` 检查要求 iterable 返回类型标注元素类型。添加 PHPDoc `@return array<string, mixed>` 标注（`array<string, mixed>` 不能用于 PHP 原生类型声明，PHP 不支持泛型语法）。

## 验证结果

### pint --test（2 个目标文件）

```
$ vendor/bin/pint --test tests/Unit/PackageBoundaryTest.php tests/Unit/PackageMetadataTest.php
{"tool":"pint","result":"passed"}
```

退出码：**0**，无 `concat_space` fixer 名称。

### phpstan analyse --level=6（2 个目标文件）

```
$ vendor/bin/phpstan analyse tests/Unit/PackageBoundaryTest.php tests/Unit/PackageMetadataTest.php --memory-limit=2G --level=6
 [OK] No errors
```

退出码：**0**。

### phpunit -c phpunit.xml.dist（全包）

```
$ vendor/bin/phpunit -c phpunit.xml.dist
OK (27 tests, 107 assertions)
```

退出码：**0**，27 个既有测试全部通过，无回归。

## Diff 统计

```
packages/filament-admin/tests/Unit/PackageBoundaryTest.php | 2 +-
packages/filament-admin/tests/Unit/PackageMetadataTest.php | 2 ++
2 files changed, 3 insertions(+), 1 deletion(-)
```

## COMPLY 状态变更

| 需求 | 修复前 | 修复后 |
|------|--------|--------|
| COMPLY-04（phpstan.neon + pint.json 同等严格度） | PARTIAL | **SATISFIED** |
| COMPLY-05（CI pint:test / phpstan 失败即 fail）| PARTIAL | **SATISFIED** |

## 残留风险声明

本 plan **不**修复 pre-existing pint/phpstan 错误（如 `src/Models/AdminUser.php`、Filament Widgets 等 Phase 1 未触碰文件中的既有违规）。这些属于历史技术债务，01-VERIFICATION.md「关于 pre-existing pint/phpstan 错误的说明」已明确将其归属未来 Phase 处理。

包 CI 在全包 `pint --test` / `phpstan analyse`（作用 src+tests 全集）上仍会因 pre-existing 问题失败。本 plan 仅保证 Phase 1 自身引入的 2 处代码质量违规已消除，CI 在 **Phase 1 范围**内不再因本 Phase 自身原因失败。

## Self-Check: PASSED

- [x] 2 个目标文件各精确修改，git diff 显示 PackageBoundaryTest.php 1+1-，PackageMetadataTest.php 2+0-
- [x] `vendor/bin/pint --test`（目标文件）退出码 0
- [x] `vendor/bin/phpstan analyse --level=6`（目标文件）退出码 0，输出含 `[OK] No errors`
- [x] `vendor/bin/phpunit -c phpunit.xml.dist` 退出码 0，`OK (27 tests, 107 assertions)`
- [x] 不引入新方法、新依赖、新配置文件、不修改 src/ 源码
- [x] COMPLY-04 / COMPLY-05 状态 PARTIAL → SATISFIED

## key-files

### created
- 无新增文件

### modified
- packages/filament-admin/tests/Unit/PackageBoundaryTest.php（第 44 行 concat_space 修复）
- packages/filament-admin/tests/Unit/PackageMetadataTest.php（loadComposerJson 返回类型 PHPDoc 泛型标注）
