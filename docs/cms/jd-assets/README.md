# 京东素材调研产物

> 用途：官网内容承载扩容的竞品结构调研与文案参考。
> **图片因平台水印无法直接上站**，只能当结构参考，详见下一节。
>
> 采集时间：2026-08-03 · 20 个 SKU

---

## 这批图不能直接上站（2026-08-03 实测结论）

**249 张全部带居中的 `JD.COM 京东` 水印**，不是可裁掉的角标，而是压在画面正中、
宽度约占 40% 的半透明大字。裁剪、缩放、换尺寸变体都躲不开：

| 试过的 URL 变体 | 结果 |
|---|---|
| `/n0/s1200x1200_jfs/...`（抓取用的） | 1200×1200，居中水印 |
| `/n0/jfs/...` | 800×800，居中水印，且额外叠了京东补贴促销版式 |
| `/n12/jfs/...` | 有水印 |
| 去掉尺寸段的裸 `jfs/...` | 404 |

所以**不存在「去掉角标即可用」这条路**，此前本文档这么写是错的。抹掉平台水印
等于重建画面，既不现实也不该做。

**这批图的正确用途是「结构参考」**：版式顺序、该拍哪些角度、参数怎么排布——
这些是可以借鉴的（版式属思想不属表达）。真正上站的图另找来源：

- 品牌方给渠道商的官方素材包（无平台水印，是最正的路子）
- 自己拍的实景与产品图
- 案例场景图用 CC0 图库

> **案例 / 方案 / 资讯的封面图已经解决**（2026-08-03）：走 `../cc0-assets/`，
> 从 Wikimedia Commons 的 Unsplash CC0 导入池取了 20 张上站。
> Unsplash / Pexels 官方 API 要 Key、`source.unsplash.com` 已关停、
> Openverse 被 Cloudflare 拦截，三条路都不通，详见那份 README。
>
> **产品封面仍然空缺**，且不该用 CC0 图凑：产品图要与型号对得上，
> 图库里没有对应 SKU 的白底图，硬凑等于挂着别人的产品当自己的。这一项还是等品牌方素材包。

`contact-sheet.html`（浏览器直接开，无需服务器）按 SKU 分组纵览 249 张图，
每张标注 `m03 1200x1200`（组别+序号+尺寸），可以用它对着挑「要拍成什么样」。

**含真人的场景图不要参考**，买家秀在抓取阶段已全部剔除（路径含 `/shaidan/`）。

---

## 图片就位后怎么进站

站上的图片路径是固定约定，放对位置即可，不需要改代码：

```
storage/app/public/site/cases/{slug}.jpg              案例封面
storage/app/public/site/solutions/{slug}.jpg          方案封面
storage/app/public/site/news/{slug}.jpg               资讯封面
storage/app/public/site/products/{slug}.jpg           产品封面
storage/app/public/site/products/{slug}/gallery-01.jpg 产品图集（可多张）
```

`SiteDemoSeeder` / `SiteNewsSeeder` 会自动灌进 Media Library：幂等，缺图静默跳过，
由前台 `image-placeholder` 组件渲染空态。slug 清单见两个 Seeder 的数据数组。

尺寸要求来自 `src/Concerns/HasCoverImage.php` 的三档转换：
`thumb` 400×300 / `card` 800×600 / `og` 1200×630 —— 源图不低于 1200px 宽。

---

## 目录内容

| 路径 | 说明 | 入库 |
|---|---|---|
| `contact-sheet.html` | 挑图纵览表 | ✅ |
| `products.json` | 20 个 SKU 的名称/品牌/价格/规格/分类/图片 URL | ✅ |
| `reviews-insight.json` | 84 条语义标签 + 24 条用户问答 | ✅ |
| `scripts/` | 采集脚本，可重新生成全部产物 | ✅ |
| `images/` | 249 张原图，约 48MB | ❌ gitignore |

`images/` 不入库：这是交给 UI 修图的中间素材，成品出来后即失效，
进 git 会永久撑大历史。克隆后需要看联系表的话按下方重新生成。

---

## 重新生成

依赖本机 `~/.claude/skills/webapp-testing/.venv` 里的 Playwright。

```bash
cd docs/cms/jd-assets/scripts
PY=~/.claude/skills/webapp-testing/.venv/bin/python

./ensure_edge.sh              # 起自动化 Edge（profile 副本 + 9222 调试端口）
$PY search_skus.py            # 按 3 个产品分类搜候选 SKU
$PY scrape.py <sku> [sku...]  # 抓商品页，原始 JSON 落 out/raw/
$PY refine.py                 # 从 raw 重新提取，产出 products.json + reviews-insight.json
$PY fetch_images.py           # 三路分流下载
$PY contact_sheet.py          # 生成联系表
```

`ensure_edge.sh` 用的是 `~/.config/edge-automation`（从日常 Edge 热拷贝 cookie 的副本），
与日常浏览器完全隔离。京东登录态随 cookie 带过去，掉了就在那个窗口重新扫码一次。

---

## 采集要点（踩坑记录）

1. **京东已全面前端渲染**，商品页与搜索页的传统 DOM 选择器（`.sku-name` / `.p-price` /
   `.Ptable` / `li.gl-item`）全部失效。数据改从 XHR 响应取：
   `pc_detailpage_wareBusiness`（主数据）、`pc_item_getWareGraphic`（图文详情）、
   `getLegoWareDetailComment`（评论聚合）。搜索页改用商品卡上的 `data-sku` 属性。

2. **不逆向 h5st 签名**。用 CDP 接管真实浏览器，让页面自己带签名发请求，
   被动监听 `page.on("response")` 捞 JSON 即可。

3. **图片尺寸看目录前缀**，踩过一次坑：
   `/n7/` 220×220 · `/n1/` 350×350 · `/n0/` 800×800 · `/n0/s1200x1200_jfs/` 1200×1200。
   最初用 `/n1/` 拿到的全是 350×350，喂不饱站上的 card/og 转换。

4. **详情页有两套模板**：经典模板图挂 `<img data-lazyload>`，SSD 模板挂
   `background-image: url()`。只匹配前者会让一半商品详情图数为 0。

5. **买家秀路径含 `/shaidan/`**，refine 阶段全部剔除，一张不下载
   （真实用户拍摄的自家环境，含人脸，属个人信息）。

---

## 竞品结构结论

最初给的 4 个 SKU（`10112370809583` / `10142494414452` / `10224115403181` /
`10135289352562`）**全是「全屋智能方案定制/咨询」**，价 2.9~199 的引流款，
对应站上的 `SiteSolution` 而非 `SiteProduct`。实体产品另按站上 3 个产品分类
（智能照明/智能安防/智能家电）搜了 16 个补齐。

### 客户关注维度（京东语义标签聚合，按人数）

用起来很顺手 5 · 颜值超炫酷 4 · 用起来超流畅 4 · 反应灵敏 3 · 科技感强 3 ·
使用稳定 3 · 语音控制 2 · 用起来超省心 2 · 远程控制超方便 · 自动感应亮灯 ·
联动功能强 · 做工超精细 · 智能联动方便 · 设计人性化合理

### 真实购前疑虑（FAQ 选题来源）

- 语音控制好用吗？
- 安装服务方便吗？
- 适合家庭使用吗？
- **镇上的能不能做** —— 服务覆盖范围，对本地商家是高频真问题
- 东西质量怎么样，有没有坏了的

以上均为聚合洞察，**不含任何评论正文**。
