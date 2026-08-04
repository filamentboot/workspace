<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

/**
 * 页面区块契约（#12）
 *
 * 一个区块 = 后台表单 Schema + 输入校验规则 + 默认值 + 前台视图。
 * 页面的 blocks 列存的是 [{type: key, data: {...}}, ...]，
 * 渲染时按 key 从 BlockRegistry 找到对应实现。
 *
 * BlockRegistry 同时充当全局白名单：未注册的 key 一律不渲染，
 * 页面内容因此无法执行任意 HTML / Blade / PHP。
 */
interface BlockContract
{
    /**
     * 区块唯一标识
     *
     * 存入页面 blocks payload 的 type 字段，只允许小写字母、数字与连字符。
     */
    public function key(): string;

    /**
     * 后台显示名称
     */
    public function label(): string;

    /**
     * 后台表单 Schema
     *
     * 返回 Filament 表单组件数组，由 SitePageResource 的 Builder 装配。
     *
     * @return array<int, mixed>
     */
    public function schema(): array;

    /**
     * 前台渲染视图名
     *
     * 按 filamentboot-site:: 命名空间解析，各主题可覆盖同名视图，
     * 缺失时回退到 shared 层。
     */
    public function view(): string;

    /**
     * payload 校验规则
     *
     * 键为 payload 内的字段路径（不含 data. 前缀），值为 Laravel 校验规则。
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * payload 默认值
     *
     * 新增区块时的初始数据，也用于补齐历史 payload 缺失的字段。
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * 用默认值补齐 payload 缺失的字段
     *
     * 渲染层（Cms\Rendering\BlockRenderer）每渲染一条都要调它，所以必须由契约
     * 声明而不是只存在于 AbstractBlock 上——否则渲染层拿着 BlockContract
     * 却调得到一个契约里没有的方法，静态分析看不见，宿主自定义区块
     * 若不继承 AbstractBlock 就会在渲染时炸。
     *
     * @param  array<string, mixed>  $data  区块 data 部分
     * @return array<string, mixed>
     */
    public function withDefaults(array $data): array;

    /**
     * 上传字段使用的磁盘名
     *
     * 前台视图拿到的 image 字段只是磁盘内相对路径，必须知道磁盘名才能
     * Storage::disk($disk)->url()。表单侧与渲染侧走同一个方法，
     * 后台改了默认磁盘不会出现「上传到 A、前台从 B 读」的错位。
     */
    public function disk(): string;
}
