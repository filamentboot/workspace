# CC0 封面图流水线

> 用途：给案例 / 方案 / 资讯配封面图。**只收无署名义务的许可**（CC0、公有领域），
> 来源 Wikimedia Commons。
>
> 采集时间：2026-08-03 · 20 张已上站

图片本身不入库（`storage/app/public/` 被 Laravel 默认 gitignore，且二进制进 git 会
永久撑大历史，同 `jd-assets/images/` 的取舍）。**能重新生成**才是入库的东西：
`scripts/` + `queries.json` + `selection.json` 三件套跑一遍就还原全部 20 张。

---

## 为什么是 Commons，不是 Unsplash / Pexels / Openverse

| 来源 | 结论 |
|---|---|
| Unsplash / Pexels 官方 API | 需要 API Key，本机没有 |
| `source.unsplash.com` | 接口已关闭（`SiteDemoSeeder` 注释里的 Pitfall 7 就是这条） |
| Openverse API | **Cloudflare 人机验证拦截**，CLI 拿到的是 `Just a moment...` 挑战页，不绕 |
| Wikimedia Commons | 免 Key、许可字段结构化、可程序化筛选 —— 选它 |

## 关键取舍：只要 CC0 / 公有领域

CC-BY / CC-BY-SA 要求**可见署名**。站点是真实公司官网，履行署名义务意味着要加字段、
要在前台渲染出处 —— 那是产品层面的改动。所以脚本在 `FREE_LICENSES` 里硬过滤，
只放 `CC0` / `Public domain` / `No restrictions` / `PDM`，把署名义务从源头排除。

代价是候选池小很多，`fetch.php` 里那条 `in_array($license, FREE_LICENSES)` 会丢掉
大部分搜索结果。这是有意的，别为了凑数放宽它。

## 关键手法：搜 `Unsplash` 导入池，别按题材直搜

第一轮按 slug 直搜（`modern living room interior`、`home theater room` 之类），
21 个位置只凑出 8 个能用的。原因是 Commons 上的公有领域素材绝大多数是**档案扫描件**：
美国 HABS 建筑测绘（1930 年代黑白）、庞贝遗址、19 世纪壁纸样本、总统办公室照片。
放到现代智能家居官网上不是"风格不搭"，是**年代不搭**。

第二轮改搜 `<题材> Unsplash`：2017 年前 Unsplash 是 CC0，那批图被批量导入了 Commons，
文件名带 `(Unsplash)` 后缀。这是 Commons 上现代摄影的富矿，一轮就把可用数从 8 拉到 20。

**这是本目录最值得记住的一条。** 以后要补图，先搜 Unsplash 导入池。

## 必须人眼过一遍

脚本按尺寸和许可筛，筛不掉这三类，全靠 `montage.php` 拼联系表看：

1. **可识别人脸** —— CC0 允许用，但把陌生人的脸放上公司官网等于暗示其代言。
   与"买家秀不用"是同一条线（见 `jd-assets/README.md`）。
2. **第三方品牌字样** —— 和京东水印是同一类穿帮。本轮就换掉过一张：原先的安防摄像头
   壳体上有 `KING SECURITY` 字样，换成了无标识的一对枪机。
   `news/gateway-single-or-dual-protocol` 那张原图是 ELECOM 路由器，靠裁切把品牌 logo
   裁在画面外，只留网口与 `WPS` 字样。
3. **年代感** —— 影音方案原选了一张真·家庭影院，但是 CRT 电视 + 2000 年代装修，
   反而拉低定位，换成了影厅座椅。

## 用法

```bash
cd docs/cms/cc0-assets
php scripts/fetch.php                       # 按 queries.json 拉候选缩略图
php scripts/montage.php pool sheet.jpg 0 3  # 拼联系表（前缀, 输出, slug 偏移, 数量）
#   ↑ 人眼挑，把 组名 + 序号 填进 selection.json
php -d memory_limit=1G scripts/stage.php    # 下原图，居中裁 3:2 / 1600px，落 staged/

cp -r staged/* ../../../storage/app/public/site/
php ../../../artisan db:seed --force --class="Filamentboot\FilamentbootSite\Database\Seeders\SiteDemoSeeder"
php ../../../artisan db:seed --force --class="Filamentboot\FilamentbootSite\Database\Seeders\SiteNewsSeeder"
```

`fetch.php` 的 `queries.json` 格式是扁平的 `{"组名": ["检索词", ...]}`；本目录存的
`queries.json` 按三轮分了层便于阅读，重跑时取其中一层拍平即可。

Seeder 是幂等的、封面图每次都会重试挂载，所以图放对位置重跑一遍就行，不用清库。
缺图的位置由前台 `image-placeholder` 组件渲染空态，不会出破图。

尺寸 1600x1067（3:2）是能同时喂饱三档转换的最小形状：
`thumb` 400x300、`card` 800x600（4:3）、`og` 1200x630（≈2:1），见
`src/Concerns/HasCoverImage.php`。

---

## 还缺什么

- **18 个产品封面全部空缺**。产品图需要与型号对得上，CC0 图库里没有对应 SKU 的白底图，
  硬凑等于挂着别人的产品当自己的。等品牌方渠道商素材包，或自己拍。
- **`news/is-voice-control-useful` 空缺**。CC0 池里"智能音箱"清一色是 Amazon Echo Dot
  的棚拍图，放到自有品牌站上等于替竞品打广告。留空反而给前台占位降级留了个活样本。

---

## 已上站清单

许可与出处逐条可查，`provenance.json` 存了机器可读版（含作者与 Commons 页面 URL）。

| 落位 | 许可 | Commons 文件 | 原始尺寸 |
|---|---|---|---|
| `cases/modern-3bed-smart` | CC0 | Communal Coworking interior (Unsplash).jpg | 5501x3095 |
| `cases/villa-full-smart` | CC0 | White staircase (Unsplash).jpg | 5616x3684 |
| `cases/old-apt-lighting` | CC0 | Warm light in the dining room (Unsplash).jpg | 5596x3800 |
| `cases/new-home-security` | CC0 | Lost House (Unsplash).jpg | 5184x3456 |
| `cases/duplex-chinese-smart` | CC0 | 中国人的客厅.jpg | 4032x3024 |
| `cases/studio-nordic` | CC0 | Cozy interior with a sofa (Unsplash).jpg | 7360x4912 |
| `solutions/full-smart-solution` | CC0 | Breather Montreal interior (Unsplash).jpg | 7186x4796 |
| `solutions/smart-lighting-solution` | CC0 | Light bulbs and vines (Unsplash).jpg | 5002x3335 |
| `solutions/home-security-solution` | CC0 | 404 (Unsplash).jpg | 6000x4000 |
| `solutions/av-entertainment-solution` | CC0 | Movie theater seats (Unsplash).jpg | 5184x3456 |
| `news/when-to-involve-smart-home-installer` | CC0 | Home Renovations (Unsplash).jpg | 5472x3648 |
| `news/camera-storage-options` | CC0 | Ceiling surveillance camera (Unsplash).jpg | 4896x3264 |
| `news/gateway-single-or-dual-protocol` | CC0 | ELECOM WRC-300FEBK WPS WiFi router.jpg | 4032x3024 |
| `news/no-main-light-illuminance` | Public domain | This view shows the interior ceiling of the visitor center… | 4504x3048 |
| `news/do-you-need-ethernet` | CC0 | Wires and cables (Unsplash).jpg | 5184x3456 |
| `news/curtain-motor-for-wide-windows` | CC0 | Window frame shadow on a curtain (Unsplash).jpg | 4857x3137 |
| `news/smart-lock-buying-pitfalls` | CC0 | Martins Zemlickis 2014 (Unsplash).jpg | 4124x2839 |
| `news/handover-checklist` | Public domain | USMC-100914-M-0646Q-45.jpg | 5616x3744 |
| `news/service-coverage` | CC0 | 甘坑客家小镇 1.jpg | 4160x3120 |
| `news/h1-selection-review-draft` | CC0 | Stickers on a notebook (Unsplash).jpg | 5939x3965 |

两张公有领域的来自美国政府作品（USMC 拍摄的验收清单板、国家公园游客中心天花板），
按美国联邦政府作品规则属公有领域，无署名义务。
