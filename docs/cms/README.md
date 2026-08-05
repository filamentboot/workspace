# 官网 CMS 文档 —— 已迁走

> 2026-08-05 起，官网/CMS 插件的全部文档搬到了 **`~/src/personal/qkznj/docs/cms/`**（Gitee 私有仓库 `johncaptain/qkznj`）。
>
> 本目录不再维护，只留这一页指路。

## 为什么搬

qkznj 站点（<https://www.qkznj.com>）在 2026-08-05 上线，它的 `packages/` 下带着 7 个包的完整副本。CMS 后续开发都在那边做——有真实站点可以立刻验证，比在 workspace 里改完再想办法看效果快得多。文档跟着开发地走。

## 这期间 workspace 是什么状态

**整个冻结。** 具体说：

- **`packages/` 下 7 个包不要在这边改。** 这边的副本会越来越旧，qkznj 那边才是最新的。
- **不从这边发版。** 中途不做 subtree split、不发 patch 版本。
- 包源码改动**攒到 qkznj 三期开工前一次性整体覆盖回来**，届时一个大 commit。这是明确选择的方案，代价（历史粒度丢失、中途无法发版）已经知道。

`docs/prd/`、`docs/dev/` 讲的是主包，不属于 CMS，没有搬走，仍在本目录同级。

## 去哪儿找什么

| 找什么 | 去哪 |
|---|---|
| CMS 插件的任务清单（未完成 / 已完成） | `qkznj/docs/cms/未完成tasks.md`、`已完成tasks.md` |
| CMS 技术路线与目标架构 | `qkznj/docs/cms/基于装修网站官网优化cms.md` |
| qkznj 站点独立的四期里程碑 | `qkznj/docs/cms/03-qkznj站点独立.md` |
| 上线时哪些配置必须手工改（包缺口台账） | `qkznj/docs/上线账本.md` |
| 素材流水线（CC0 封面、京东调研） | `qkznj/docs/cms/cc0-assets/`、`jd-assets/` |

## 一条待办：留在本仓库的缺陷

目录重构（#27）把 CMS 模型挪了命名空间，但没配套数据迁移改写 `media.model_type`，**案例与方案的封面图全部不渲染**（资讯的正常，它命名空间没变）：

- `Filamentboot\FilamentbootSite\Models\SiteCase` → `...\Modules\Corporate\Cases\Models\SiteCase`
- `...\Models\SiteSolution` → `...\Modules\Corporate\Solutions\Models\SiteSolution`

qkznj 线上和本地都复现，**`demo.xitongapp.com` 大概率同样中招**。正解是在 site 包里补一条改写 `media.model_type` 的迁移——任何装过旧版本的下游升级时都需要它。详见 `qkznj/docs/上线账本.md`。
