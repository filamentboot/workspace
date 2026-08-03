#!/usr/bin/env python
"""
从 out/raw/*.json 重新提取，不碰浏览器

相对 scrape.py 内联版本的修正（均为实测踩出来的）：
  1. 详情图有两种模板：
     经典模板 <img data-lazyload="...">
     SSD 模板  <div style="background-image:url(//img30...)">
     只匹配前者会让 SSD 模板商品的详情图数为 0
  2. attributes 里混着「商品编号」「店铺」等非规格项，需按黑名单剔除；
     部分 POP 商品没有 coreAttributes key
  3. 评论接口 getLegoWareDetailComment 返回的不是评论原文，而是京东聚合好的
     semanticTagList（语义标签+计数）和 questionList（用户问答）——正好是
     需要的洞察层，不含任何可搬运的评论正文

用法：~/.claude/skills/webapp-testing/.venv/bin/python refine.py
"""

import json
import re
from pathlib import Path

BASE = Path(__file__).parent / "out"
RAW = BASE / "raw"
OUT = BASE / "products"

IMG_HOST = "https://img10.360buyimg.com"
SHAIDAN = re.compile(r"/shaidan/", re.I)

# attributes 里这些不是商品规格，是元数据
NON_SPEC = {"商品编号", "店铺", "商品毛重", "商品产地", "货号", "CCC证书编号"}


def norm_img(u: str) -> str:
    """
    统一成绝对 URL，主图取最大变体

    京东按目录前缀切尺寸，实测同一张图：
        /n7/  → 220x220     /n1/ → 350x350
        /n0/  → 800x800     /n0/s1200x1200_jfs/ → 1200x1200
    早先用 /n1/ 拿到的全是 350x350，喂不饱站上的 card(800x600) 与 og(1200x630)
    两档转换，必须走 n0 + s1200x1200_jfs。
    """
    u = u.strip().strip("'\"")
    if u.startswith("//"):
        return "https:" + u
    if u.startswith("http"):
        return u

    rel = u.lstrip("/")
    # 相对路径形如 jfs/t1/...，插入尺寸段取 1200 版
    if rel.startswith("jfs/"):
        return f"{IMG_HOST}/n0/s1200x1200_jfs/{rel[len('jfs/'):]}"

    return f"{IMG_HOST}/n0/{rel}"


def detail_images(html: str) -> list[str]:
    """兼容经典模板与 SSD 模板两种图片挂载方式"""
    urls: list[str] = []
    urls += re.findall(r'data-lazyload="([^"]+)"', html)
    urls += re.findall(r'src="(https?://[^"]*360buyimg[^"]*)"', html)
    urls += re.findall(r"background-image:\s*url\(([^)]+)\)", html)

    out, seen = [], set()
    for u in urls:
        n = norm_img(u)
        if "360buyimg" not in n or SHAIDAN.search(n) or n in seen:
            continue
        seen.add(n)
        out.append(n)

    return out


def clean_title(raw: str) -> str:
    """页面 title → 商品名：剥掉京东加的行情后缀"""
    t = re.sub(r"【[^】]*行情[^】]*】", "", raw or "")
    t = re.sub(r"[-—]\s*京东\s*$", "", t)

    return t.strip()


def refine(sku: str, bucket: dict) -> dict:
    rec: dict = {
        "sku": sku,
        "name": clean_title(bucket.get("_title", "")),
        "url": f"https://item.jd.com/{sku}.html",
    }

    ware = bucket.get("ware") or {}
    pav = ware.get("productAttributeVO") or {}

    raw_attrs = {
        a["labelName"]: a["labelValue"]
        for a in pav.get("attributes", [])
        if a.get("labelName")
    }
    core = {
        a["labelName"]: a["labelValue"]
        for a in pav.get("coreAttributes", [])
        if a.get("labelName")
    }

    rec["brand"] = raw_attrs.get("品牌")
    rec["shop"] = raw_attrs.get("店铺")
    # 规格 = attributes 去掉元数据 + coreAttributes（品类属性）
    rec["specs"] = {k: v for k, v in raw_attrs.items() if k not in NON_SPEC and k != "品牌"}
    rec["specs"].update(core)

    price = ware.get("price") or {}
    rec["price"] = price.get("p")
    rec["price_original"] = price.get("op")

    rec["crumbs"] = [c.get("text") for c in (ware.get("crumbInfoVO") or {}).get("crumbs", [])]

    mv = ware.get("mainImageVO") or {}
    mains = []
    if (mv.get("mainImageArea") or {}).get("imageUrl"):
        mains.append(mv["mainImageArea"]["imageUrl"])
    mains += [i["imageUrl"] for i in mv.get("carouselArea", []) if i.get("imageUrl")]
    seen: set[str] = set()
    rec["main_images"] = [
        n for u in mains if (n := norm_img(u)) not in seen and not seen.add(n)
    ]

    graphic = (bucket.get("graphic") or {}).get("data") or {}
    rec["detail_images"] = detail_images(graphic.get("graphicContent") or "")

    return rec


def insight(sku: str, bucket: dict) -> dict:
    """评论洞察：只取聚合标签与问答，不取任何评论正文"""
    c = bucket.get("comment") or {}

    return {
        "sku": sku,
        "comment_total": c.get("allCntStr") or c.get("allCnt"),
        # 京东聚合的语义标签，天然是「客户在意什么」的频次表
        "tags": [
            {"name": t.get("name"), "count": t.get("count")}
            for t in c.get("semanticTagList", [])
            if t.get("name")
        ],
        # 真实用户提问 → 官网 FAQ 的选题来源
        "questions": [
            {
                "q": q.get("content"),
                "answers": q.get("answerCountText"),
            }
            for q in c.get("questionList", [])
            if q.get("content")
        ],
    }


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    products, insights = [], []

    for f in sorted(RAW.glob("*.json")):
        bucket = json.loads(f.read_text(encoding="utf-8"))
        sku = f.stem
        products.append(refine(sku, bucket))
        insights.append(insight(sku, bucket))

    # 早期抓的几条 raw 里没有 _title，从上一版 products.json 兜底补名
    old = OUT / "products.json"
    if old.exists():
        names = {r["sku"]: r.get("name") for r in json.loads(old.read_text(encoding="utf-8"))}
        for r in products:
            if not r.get("name"):
                r["name"] = names.get(r["sku"])

    (OUT / "products.json").write_text(
        json.dumps(products, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    (BASE / "reviews-insight.json").write_text(
        json.dumps(insights, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    print(f"{'SKU':<16}{'品牌':<12}{'价':>8}  规格 主图 详情图  标签 问答")
    print("-" * 72)
    for r, ins in zip(products, insights):
        print(
            f"{r['sku']:<16}{(r.get('brand') or '—'):<12}{str(r.get('price') or '—'):>8}"
            f"  {len(r['specs']):>3} {len(r['main_images']):>4} {len(r['detail_images']):>5}"
            f"   {len(ins['tags']):>4} {len(ins['questions']):>4}"
        )

    all_tags = [t for i in insights for t in i["tags"]]
    all_q = [q for i in insights for q in i["questions"]]
    print(f"\n合计：语义标签 {len(all_tags)} 条，用户问答 {len(all_q)} 条")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
