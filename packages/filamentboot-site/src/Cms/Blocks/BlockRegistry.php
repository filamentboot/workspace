<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use InvalidArgumentException;

/**
 * 区块注册表（#12）
 *
 * 既是查找表也是**安全白名单**：页面 blocks payload 里的 type 只有在此注册过
 * 才会被渲染，未知 key 由渲染层跳过并记日志。页面内容因此不可能执行任意
 * HTML / Blade / PHP——内容编辑能选的区块，永远只是开发者声明过的那几种。
 *
 * 在 SiteServiceProvider 中注册为容器单例，宿主可在自己的 ServiceProvider 里
 * 追加自定义区块：app(BlockRegistry::class)->register(new MyBlock).
 */
class BlockRegistry
{
    /**
     * 已注册区块，键为 block key
     *
     * @var array<string, BlockContract>
     */
    protected array $blocks = [];

    /**
     * 注册一个区块
     *
     * key 必须是小写字母/数字/连字符：它会进入视图名与 payload，
     * 放宽字符集等于给视图路径解析留下可被内容侧影响的入口。
     *
     * @throws InvalidArgumentException key 非法或重复注册
     */
    public function register(BlockContract $block): void
    {
        $key = $block->key();

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key) !== 1) {
            throw new InvalidArgumentException("区块 key「{$key}」非法，只允许小写字母、数字与连字符。");
        }

        if (isset($this->blocks[$key])) {
            throw new InvalidArgumentException("区块 key「{$key}」已被注册，不能重复注册。");
        }

        $this->blocks[$key] = $block;
    }

    /**
     * 批量注册
     *
     * @param  iterable<BlockContract>  $blocks
     */
    public function registerMany(iterable $blocks): void
    {
        foreach ($blocks as $block) {
            $this->register($block);
        }
    }

    /**
     * key 是否已注册
     */
    public function has(string $key): bool
    {
        return isset($this->blocks[$key]);
    }

    /**
     * 按 key 取区块，未注册时返回 null
     *
     * 返回 null 而不抛异常：渲染层遇到历史遗留的未知 key 应当跳过并记日志，
     * 不能让一个失效区块把整个页面打成 500。
     */
    public function get(string $key): ?BlockContract
    {
        return $this->blocks[$key] ?? null;
    }

    /**
     * 全部已注册区块
     *
     * @return array<string, BlockContract>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * 全部已注册 key
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->blocks);
    }

    /**
     * 后台下拉用的 key => label 映射
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->blocks as $key => $block) {
            $options[$key] = $block->label();
        }

        return $options;
    }
}
