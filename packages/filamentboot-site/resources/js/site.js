/**
 * 官网前台脚本入口（#29）
 *
 * 只做一件事：把 Alpine 交付到公开页上。
 *
 * 为什么需要它：在 #29 之前，前台的 Alpine 是 Livewire 注入的 livewire.js 捎带进来的。
 * 而那个 script 标签带 data-csrf，渲染时会调 csrf_token() → 起 session → 公开页
 * 必然带 Set-Cookie，整页缓存无从谈起。要让公开页零 session，就得先让 Alpine
 * 有一条不经过 Livewire 的交付路径。
 *
 * 走 Vite 入口而不是 CDN 或提交压缩产物：主题 CSS 已经是这个模式
 * （config 的 assets.vite_entries + 宿主往 vite.config.js 的 input 加一项），
 * 加一个 JS 入口是同一条路，可审计、可随 npm 升级、不引境外单点。
 *
 * ⚠️ 各处的 alpine:init 监听器（如 shared/components/contact-panel-store.blade.php）
 * 必须在本文件执行之前注册。布局把那些内联 <script> 放在 <head>，而 @vite 产出的
 * 是 type="module"（天然 defer），顺序有保证。
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
