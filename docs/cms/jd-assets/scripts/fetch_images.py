#!/usr/bin/env python
"""
图片三路分流下载

  main/    品牌官方主图 —— 全量下，这是可正当使用的那类（品牌方素材，
           渠道商展示所代理品牌属行业惯例）
  detail/  详情长图 —— 每 SKU 只下前 N 张作候选，需人工挑：这类混着
           店铺自制促销图（带「XX旗舰店」「京东自营」角标），放自己站上穿帮
  —        买家秀 —— 路径含 /shaidan/，refine 阶段已剔除，一张不下

被跳过的数量会打印出来，不做静默截断。

走浏览器的 request context 发起下载（带 cookie 与正确 Referer），
避免 CDN 对裸 HTTP 请求的风控。

用法：~/.claude/skills/webapp-testing/.venv/bin/python fetch_images.py [每SKU详情图上限]
"""

import json
import re
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = Path(__file__).parent / "out"
PRODUCTS = BASE / "products" / "products.json"
IMG_DIR = BASE / "images"

DETAIL_CAP_DEFAULT = 6


def safe_name(url: str, idx: int) -> str:
    ext = ".jpg"
    m = re.search(r"\.(jpg|jpeg|png|webp|gif)(?:$|\?)", url, re.I)
    if m:
        ext = "." + m.group(1).lower()

    return f"{idx:02d}{ext}"


def main() -> int:
    cap = int(sys.argv[1]) if len(sys.argv) > 1 else DETAIL_CAP_DEFAULT
    products = json.loads(PRODUCTS.read_text(encoding="utf-8"))

    stats = {"main": 0, "detail": 0, "fail": 0, "detail_skipped": 0}

    with sync_playwright() as p:
        browser = p.chromium.connect_over_cdp("http://127.0.0.1:9222")
        ctx = browser.contexts[0] if browser.contexts else browser.new_context()
        req = ctx.request

        for rec in products:
            sku = rec["sku"]
            referer = rec["url"]

            groups = {
                "main": rec.get("main_images", []),
                "detail": rec.get("detail_images", [])[:cap],
            }
            dropped = max(0, len(rec.get("detail_images", [])) - cap)
            stats["detail_skipped"] += dropped

            got = {}
            for kind, urls in groups.items():
                d = IMG_DIR / sku / kind
                d.mkdir(parents=True, exist_ok=True)
                n = 0
                for i, u in enumerate(urls):
                    dest = d / safe_name(u, i)
                    if dest.exists() and dest.stat().st_size > 0:
                        n += 1
                        continue
                    try:
                        resp = req.get(u, headers={"referer": referer}, timeout=30_000)
                        if resp.ok:
                            body = resp.body()
                            if len(body) > 1024:  # 过滤占位/错误小图
                                dest.write_bytes(body)
                                n += 1
                            else:
                                stats["fail"] += 1
                        else:
                            stats["fail"] += 1
                    except Exception:
                        stats["fail"] += 1
                got[kind] = n
                stats[kind] += n

            print(
                f"  {sku:<16} 主图 {got['main']:>2}  详情 {got['detail']:>2}"
                + (f"  (详情另有 {dropped} 张未取)" if dropped else "")
            )

    total_mb = sum(f.stat().st_size for f in IMG_DIR.rglob("*") if f.is_file()) / 1e6
    print(
        f"\n主图 {stats['main']} 张 | 详情候选 {stats['detail']} 张 | "
        f"失败 {stats['fail']} | 详情超出上限未取 {stats['detail_skipped']} 张"
    )
    print(f"买家秀（/shaidan/）：refine 阶段已全部剔除，未下载")
    print(f"合计 {total_mb:.1f} MB → {IMG_DIR}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
