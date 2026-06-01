# 插件市场基础包开发说明

## 包边界

- `FilamentAdmin` 是后台基座基础包
- `filament-admin/plugin-platform` 是依赖 `FilamentAdmin` 的插件市场基础包
- 插件市场包负责：
  - 扩展库存管理
  - 官方市场索引缓存
  - 官方可信来源安装动作
  - 初始化、启停、兼容性与来源边界表达

## 当前配置入口

配置文件：`config/plugin-platform.php`

- `system_dependency_packages`
  - 定义系统基础依赖白名单
  - 用于同步到扩展库存
- `local_extensions`
  - 定义本地已纳管插件
  - 支持 `kind`、`source`、`status`、`meta.registration`
- `official_market_index_url`
  - 定义官方市场远程 JSON 索引地址
  - 已配置时优先走远程拉取
- `official_market_request_timeout`
  - 定义远程官方市场索引拉取超时
- `official_market_entries`
  - 定义官方市场本地回退条目
  - 未配置远程索引地址时作为本地缓存源使用

## 当前命令

- `plugin-platform:sync-system-dependencies`
  - 同步系统基础依赖到扩展库存
- `plugin-platform:sync-local-extensions`
  - 同步本地已纳管插件到扩展库存
- `plugin-platform:sync-official-market`
  - 同步官方市场索引到市场缓存与扩展库存

## 当前后台入口

- `扩展清单`
  - 面向当前系统里已经被平台识别的对象
  - 支持安装、启用、禁用、初始化、详情查看
- `官方市场`
  - 面向官方市场缓存条目
  - 支持搜索、筛选、详情查看、本地状态对照
  - 仅官方可信来源开放后台安装动作

## 生命周期语义

- `安装`
  - 把插件纳入当前系统
  - 官方可信来源当前优先走 `composer require` 受控安装链路
- `启用`
  - 表达平台管理状态
  - 当前尚未驱动真实菜单/页面运行时隐藏
- `初始化`
  - 主要面向方案型插件
  - 当前已支持记录执行结果与失败提示
  - 真实插件级初始化执行链路仍待补完

## 来源边界

- `官方可信来源`
  - 可后台安装
- `官方收录来源`
  - 只展示兼容信息与安装指引
- `开源生态引用来源`
  - 只展示兼容信息与安装指引
- `系统内置`
  - 不进入市场安装语义

## 当前审计事件

- `plugin_installed`
- `plugin_install_failed`
- `plugin_enabled`
- `plugin_disabled`

这些事件都写入 `admin` 日志通道，便于后续在后台操作日志中对照安装与状态变更。

## 当前实现边界

- 官方市场索引已支持远程 JSON 拉取；未配置远程地址时回退本地缓存条目
- 官方可信来源安装已接入 `composer require`；当前仍是同步执行，尚未做异步任务化
- 启停还没有驱动真实菜单、页面、配置入口的运行时隐藏
- 初始化还没有接入真实插件级执行器与更细粒度进度记录
