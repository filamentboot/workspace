#!/usr/bin/env python
"""
京东商品抓取器 — 吃 XHR JSON，不解析 DOM

踩点结论（recon.py 得出）：
  * 京东商品页已是前端渲染，传统 DOM 选择器（.sku-name/.p-price/.Ptable）全部失效
  * 主数据在 XHR functionId=pc_detailpage_wareBusiness
  * 图文详情在 functionId=pc_item_getWareGraphic，图片挂 data-lazyload 而非 src
  * 评论在 functionId=getLegoWareDetailComment
  * h5st 签名由页面自己算，被动监听响应即可，无需逆向

图片三路分流（路径前缀判据，实测得出）：
  主图    mainImageVO.carouselArea[].imageUrl  → 收
  详情图  graphicContent 的 data-lazyload      → 收（入待选区，人工挑）
  买家秀  路径含 /shaidan/                      → 丢弃，不下载

用法：
  ~/.claude/skills/webapp-testing/.venv/bin/python scrape.py [sku_id ...]
"""

import json
import re
import sys
import time
from pathlib import Path
from urllib.parse import unquote

from playwright.sync_api import sync_playwright

from refine import detail_images as refine_detail_images

CDP_URL = "http://127.0.0.1:9222"
OUT = Path(__file__).parent / "out" / "products"
RAW = Path(__file__).parent / "out" / "raw"

# 用户给的 4 个种子 SKU
SEED_SKUS = [
    "10112370809583",
    "10142494414452",
    "10224115403181",
    "10135289352562",
]

# 关心的 functionId → 存档名
WANTED = {
    "pc_detailpage_wareBusiness": "ware",
    "pc_item_getWareGraphic": "graphic",
    "getLegoWareDetailComment": "comment",
}

IMG_HOST = "https://img10.360buyimg.com"
SHAIDAN = re.compile(r"/shaidan/", re.I)


def fn_of(url: str) -> str | None:
    """从 URL 里取 functionId（可能被 urlencode）"""
    m = re.search(r"functionId=([A-Za-z_]+)", unquote(url))

    return m.group(1) if m else None


def clean_title(raw: str) -> str:
    """页面 title → 商品名：剥掉京东加的行情后缀"""
    t = re.sub(r"【[^】]*行情[^】]*】", "", raw)
    t = re.sub(r"[-—]\s*京东\s*$", "", t)

    return t.strip()


def abs_img(rel: str) -> str:
    """主图相对路径 jfs/t1/... → 原图绝对 URL（n1 是无尺寸裁切的大图目录）"""
    rel = rel.lstrip("/")
    if rel.startswith("http"):
        return rel

    return f"{IMG_HOST}/n1/{rel}"


def strip_size(url: str) -> str:
    """去掉 s500x500_ 之类尺寸前缀，取原图"""
    return re.sub(r"/s\d+x\d+_", "/", url)


def extract(sku: str, title: str, bucket: dict) -> dict:
    """把三个 XHR 响应揉成一条产品记录"""
    rec: dict = {"sku": sku, "name": clean_title(title), "url": f"https://item.jd.com/{sku}.html"}

    # ---- 主数据 ----
    ware = bucket.get("ware")
    if ware:
        pav = ware.get("productAttributeVO") or {}
        attrs = {a["labelName"]: a["labelValue"] for a in pav.get("attributes", []) if a.get("labelName")}
        core = {a["labelName"]: a["labelValue"] for a in pav.get("coreAttributes", []) if a.get("labelName")}
        rec["brand"] = attrs.get("品牌")
        rec["attributes"] = attrs
        rec["core_attributes"] = core

        price = ware.get("price") or {}
        rec["price"] = price.get("p")
        rec["price_original"] = price.get("op")

        rec["crumbs"] = [c.get("text") for c in (ware.get("crumbInfoVO") or {}).get("crumbs", [])]
        rec["cat_name"] = (ware.get("pageConfigVO") or {}).get("catName")
        rec["shop_id"] = (ware.get("pageConfigVO") or {}).get("shopId")

        mv = ware.get("mainImageVO") or {}
        mains = [i.get("imageUrl") for i in mv.get("carouselArea", []) if i.get("imageUrl")]
        main_area = (mv.get("mainImageArea") or {}).get("imageUrl")
        if main_area:
            mains.insert(0, main_area)
        # 去重保序
        seen: set[str] = set()
        rec["main_images"] = [
            abs_img(u) for u in mains if not (u in seen or seen.add(u))
        ]

    # ---- 图文详情 ----
    graphic = bucket.get("graphic")
    if graphic:
        data = graphic.get("data") or {}
        html = data.get("graphicContent") or ""
        rec["detail_html_len"] = len(html)
        # 复用 refine 的提取器：详情页有经典/SSD 两种模板，两份实现会漂移
        rec["detail_images"] = refine_detail_images(html)
        rec["after_sale"] = [
            x.get("title") or x.get("name")
            for x in (data.get("afterSaleGather") or {}).get("afterSaleList", [])
        ]

    # ---- 评论 ----
    comment = bucket.get("comment")
    if comment:
        rec["comment_raw_keys"] = list(comment.keys())[:12]

    return rec


def scrape_one(ctx, sku: str) -> dict:
    url = f"https://item.jd.com/{sku}.html"
    bucket: dict = {}
    page = ctx.new_page()

    def on_response(resp):
        fn = fn_of(resp.url)
        if fn not in WANTED:
            return
        key = WANTED[fn]
        if key in bucket:  # 只留首个成功响应
            return
        try:
            bucket[key] = resp.json()
        except Exception:
            try:
                bucket[key] = {"_text": resp.text()[:20000]}
            except Exception:
                pass

    page.on("response", on_response)

    print(f"\n[..] {sku}")
    page.goto(url, wait_until="domcontentloaded", timeout=60_000)
    try:
        page.wait_for_load_state("networkidle", timeout=25_000)
    except Exception:
        pass

    title = page.title()

    # 触发评论与懒加载详情
    for sel in ["li[data-anchor='#comment']", "text=商品评价", "text=大家评"]:
        try:
            page.locator(sel).first.click(timeout=3000)
            break
        except Exception:
            continue
    for _ in range(8):
        page.mouse.wheel(0, 1600)
        page.wait_for_timeout(700)
    page.wait_for_timeout(2500)

    rec = extract(sku, title, bucket)

    # 商品名只存在于页面 title（XHR payload 里没有），必须随 raw 一起落盘，
    # 否则后续 refine 重跑时拿不回来
    bucket["_title"] = title

    RAW.mkdir(parents=True, exist_ok=True)
    (RAW / f"{sku}.json").write_text(
        json.dumps(bucket, ensure_ascii=False, indent=1), encoding="utf-8"
    )

    got = "+".join(sorted(bucket.keys())) or "无"
    print(f"     {rec['name'][:52]}")
    print(
        f"     品牌={rec.get('brand')} 价={rec.get('price')} "
        f"主图={len(rec.get('main_images', []))} 详情图={len(rec.get('detail_images', []))} "
        f"参数={len(rec.get('core_attributes', {}))} [XHR: {got}]"
    )
    page.close()

    return rec


def main() -> int:
    skus = sys.argv[1:] or SEED_SKUS
    OUT.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as p:
        try:
            browser = p.chromium.connect_over_cdp(CDP_URL)
        except Exception as exc:
            print(f"[FAIL] CDP 连接失败：{exc}")

            return 1

        ctx = browser.contexts[0] if browser.contexts else browser.new_context()
        records = []
        for i, sku in enumerate(skus):
            try:
                records.append(scrape_one(ctx, sku))
            except Exception as exc:
                print(f"[FAIL] {sku}: {type(exc).__name__}: {exc}")
            if i < len(skus) - 1:
                time.sleep(4)  # 节奏控制，串行不并发

        (OUT / "products.json").write_text(
            json.dumps(records, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        print(f"\n[done] {len(records)}/{len(skus)} 条 → {OUT / 'products.json'}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
