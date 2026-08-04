{{--
 * 首触渠道归因（客户端版，#29）
 *
 * 原来这件事由 CaptureVisitorAttribution 中间件在服务端做，写 session。但公开页要能整页缓存
 * 就不能起 session（起了就有 Set-Cookie，共享缓存直接失效），所以搬到 localStorage。
 *
 * 「首触」的定义没变：**只在 key 不存在时写一次**，后续访问不覆盖。访客从广告落地后往往
 * 要跳几个页面才提交表单，若每次都刷新，提交时拿到的会是站内最后一跳，渠道信息全丢。
 * 也正因为只在首次写，document.referrer 拿到的就是真正的外部来源页。
 *
 * localStorage 不可用（隐私模式、禁用存储）时降级为**内存**：这一个页面内提交仍带得上归因，
 * 跨页就丢——比整段抛异常把表单一起搞坏好。
 *
 * 必须在 <head> 里 include，且要早于 site.js（Alpine.start()）：这里注册的是
 * alpine:init 监听器。
 --}}
<script>
    document.addEventListener('alpine:init', () => {
        const KEY = 'site.attribution';

        const UTM = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
        ];

        /** localStorage 不可用时的退路 */
        let memory = null;

        const read = () => {
            try {
                const raw = window.localStorage.getItem(KEY);

                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return memory;
            }
        };

        const write = (data) => {
            memory = data;

            try {
                window.localStorage.setItem(KEY, JSON.stringify(data));
            } catch (e) {
                // 存不进去就只留内存副本
            }
        };

        const capture = () => {
            const params = new URLSearchParams(window.location.search);

            const data = {
                landing_url: window.location.href.slice(0, 1024),
                referer: document.referrer ? document.referrer.slice(0, 1024) : null,
            };

            UTM.forEach((key) => {
                const value = params.get(key);

                data[key] = value ? value.slice(0, 255) : null;
            });

            return data;
        };

        Alpine.store('siteAttribution', {
            init() {
                if (read() === null) {
                    write(capture());
                }
            },

            /**
             * 随表单一起提交的归因字段
             *
             * @returns {Object}
             */
            payload() {
                return read() ?? {};
            },
        });
    });
</script>
