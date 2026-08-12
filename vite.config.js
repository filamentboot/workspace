import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
                'vendor/filamentboot/filamentboot-site/resources/css/themes/decoration.css',
                'vendor/filamentboot/filamentboot-site/resources/css/themes/software.css',
                'vendor/filamentboot/filamentboot-site/resources/js/site.js',
            ],
            refresh: true,
            // 原来这里挂着 bunny('Instrument Sans')。去掉的两个理由：
            // 1. 构建期要联 fonts.bunny.net 拉字体清单，国内访问 4s 起、经常直接 ETIMEDOUT，
            //    npm run build 会整个失败——部署链路上多一个境外单点。
            // 2. 该字体只被 resources/css/app.css 的 --font-sans 引用（宿主 landing 页），
            //    前台两套主题和 Filament 面板都不用它，中文站更用不上拉丁字体。
            // 去掉后回落 ui-sans-serif / system-ui。
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
