/**
 * wangEditor Alpine Component 桥接
 *
 * 实现 Filament 5 Livewire Alpine 桥接模式：
 * - 通过 $wire.get/set 与 Livewire 组件状态同步
 * - 死循环防护（Pitfall 3）：onChange 时比对新旧值，避免 set→onChange→set 无限循环
 * - 图片 customUpload 携带 X-CSRF-TOKEN header（T-09-07）
 * - errno 协议：errno===0 成功，insertFn 插入图片；errno!==0 提示用户错误
 *
 * @param {object} config
 * @param {string} config.statePath - Livewire 组件状态路径（如 data.content）
 * @param {string} config.uploadUrl - 图片上传 URL（/filamentboot-wang-editor/upload）
 * @param {string} config.disk - 上传磁盘名称（oss/cos/public）
 */
export default function wangEditorField({ statePath, uploadUrl, disk }) {
    return {
        editor: null,
        toolbar: null,

        /**
         * Alpine component 初始化
         *
         * 创建 wangEditor 实例和工具栏，绑定 onChange 到 Livewire 状态。
         * 依赖 window.wangEditor（由 FilamentAsset CDN Js 注入全局）。
         */
        init() {
            const { createEditor, createToolbar } = window.wangEditor ?? {};

            if (!createEditor || !createToolbar) {
                console.error('[WangEditor] window.wangEditor 未加载，请检查 CDN 地址');
                return;
            }

            const toolbarSelector = `#wang-editor-toolbar-${statePath}`;
            const editorSelector = `#wang-editor-content-${statePath}`;

            this.editor = createEditor({
                selector: editorSelector,
                html: this.$wire.get(statePath) ?? '',
                config: {
                    placeholder: '请输入内容...',
                    // 图片上传：使用 customUpload 携带 CSRF token 和磁盘参数（T-09-07/D-09-07）
                    MENU_CONF: {
                        uploadImage: {
                            /**
                             * 自定义图片上传函数
                             *
                             * @param {File[]} files - 待上传文件列表
                             * @param {function} insertFn - wangEditor 内置插入图片函数
                             */
                            customUpload: async (files, insertFn) => {
                                for (const file of files) {
                                    const formData = new FormData();
                                    formData.append('file', file);
                                    formData.append('disk', disk);

                                    try {
                                        const response = await fetch(uploadUrl, {
                                            method: 'POST',
                                            headers: {
                                                // CSRF 防护（T-09-07）
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                            },
                                            body: formData,
                                        });

                                        const result = await response.json();

                                        if (result.errno === 0) {
                                            // 成功：插入图片到编辑器
                                            insertFn(result.data.url, result.data.alt ?? '', result.data.href ?? '');
                                        } else {
                                            // 失败：显示错误信息
                                            console.error('[WangEditor] 图片上传失败:', result.message);
                                        }
                                    } catch (error) {
                                        console.error('[WangEditor] 图片上传请求异常:', error);
                                    }
                                }
                            },
                        },
                    },
                    // onChange 回调：同步编辑器内容到 Livewire 状态
                    onChange: (editorInstance) => {
                        const newHtml = editorInstance.getHtml();

                        // 死循环防护（Pitfall 3）：
                        // $wire.set 会触发 Livewire 重渲染，若不比对新旧值
                        // 则会进入 set→onChange→set 无限循环
                        if (newHtml === this.$wire.get(statePath)) {
                            return;
                        }

                        this.$wire.set(statePath, newHtml);
                    },
                },
                mode: 'default',
            });

            this.toolbar = createToolbar({
                editor: this.editor,
                selector: toolbarSelector,
                mode: 'default',
            });
        },

        /**
         * Alpine component 销毁
         *
         * 在组件从 DOM 移除时销毁 wangEditor 实例，释放内存。
         */
        destroy() {
            if (this.editor) {
                this.editor.destroy();
                this.editor = null;
            }

            if (this.toolbar) {
                this.toolbar.destroy();
                this.toolbar = null;
            }
        },
    };
}
