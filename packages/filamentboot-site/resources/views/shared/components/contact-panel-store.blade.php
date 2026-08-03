{{--
 * 询盘面板全局 Alpine Store（跨主题共享）
 *
 * 必须在 <head> 中引入：脚本在 HTML 解析阶段就注册 alpine:init 监听，
 * 早于 Livewire 启动 Alpine，确保任何位置的 CTA 都能拿到 $store.contactPanel。
 *
 * 此前面板开关状态存放在 floating-contact 自己的 x-data 作用域里，
 * 导航栏的 CTA 只能去 remove 一个根本不存在的 hidden class，
 * 线上表现为「点击预约咨询没有任何反应」。改为全局 store 后，
 * 导航、移动菜单、列表页、详情页和悬浮按钮共用同一个可测试的打开动作。
 --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('contactPanel', {
            open: false,
            source: '',

            /**
             * 打开询盘面板
             *
             * @param {string} source 来源标识，便于后续区分转化入口
             */
            show(source = '') {
                this.source = source;
                this.open = true;
            },

            hide() {
                this.open = false;
            },

            toggle() {
                this.open = ! this.open;
            },
        });
    });
</script>
