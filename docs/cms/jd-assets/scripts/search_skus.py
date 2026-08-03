#!/usr/bin/env python
"""
按关键词从京东搜索页采集 SKU id

用户给的 4 个种子 SKU 全是「全屋智能方案定制/咨询」类（价 2.9~199），
对应站上的 SiteSolution 而非 SiteProduct。产品库还需要实体产品，
按官网现有 3 个产品分类（智能照明/智能安防/智能家电）分别搜。

只采 SKU id 与列表页标题，详情仍交给 scrape.py 逐个抓。

用法：~/.claude/skills/webapp-testing/.venv/bin/python search_skus.py
"""

import json
import re
import time
from pathlib import Path

from playwright.sync_api import sync_playwright

CDP_URL = "http://127.0.0.1:9222"
OUT = Path(__file__).parent / "out"

# 分类 → 搜索词（对齐 SiteDemoSeeder 的 3 个 productCategory slug）
QUERIES: dict[str, list[str]] = {
    "smart-lighting": ["智能灯带 米家", "智能开关面板 零火线", "智能吸顶灯 米家"],
    "smart-security": ["智能门锁 指纹 米家", "可视门铃 无线", "家用摄像头 云台"],
    "smart-appliances": ["智能窗帘电机 米家", "智能中控屏 全屋", "空调伴侣 红外遥控"],
}

PER_QUERY = 3  # 每个词取前 N 个，3 类 × 3 词 × 3 = 27 个候选，够挑 15-20


def collect(page, kw: str) -> list[dict]:
    """
    京东搜索页已改为 SPA（retail-mall/main_search 包），
    老的 li.gl-item / .p-name em 结构全部失效，改用商品卡容器上的 data-sku。
    标题只作人工挑选参考，准确名称由 scrape.py 从商品页 XHR 取。
    """
    url = f"https://search.jd.com/Search?keyword={kw}&enc=utf-8"
    page.goto(url, wait_until="domcontentloaded", timeout=60_000)
    try:
        page.wait_for_load_state("networkidle", timeout=20_000)
    except Exception:
        pass
    page.mouse.wheel(0, 2500)
    page.wait_for_timeout(2500)

    items = page.eval_on_selector_all(
        "[data-sku]",
        """els => {
             const seen = new Set(), out = [];
             for (const e of els) {
               const sku = e.getAttribute('data-sku');
               if (!sku || seen.has(sku)) continue;
               seen.add(sku);
               const t = (e.innerText || '').replace(/\\s+/g, ' ').trim();
               out.push({ sku, title: t.slice(0, 90), is_ad: t.startsWith('广告') });
             }
             return out;
           }""",
    )

    # 剔除广告位与非数字 SKU
    return [
        i for i in items
        if i.get("sku") and re.fullmatch(r"\d+", i["sku"]) and not i.get("is_ad")
    ]


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    found: dict[str, list[dict]] = {}

    with sync_playwright() as p:
        browser = p.chromium.connect_over_cdp(CDP_URL)
        ctx = browser.contexts[0] if browser.contexts else browser.new_context()
        page = ctx.new_page()

        for cat, kws in QUERIES.items():
            bucket: list[dict] = []
            for kw in kws:
                try:
                    items = collect(page, kw)
                except Exception as exc:
                    print(f"[fail] {kw}: {type(exc).__name__}")
                    items = []
                picked = items[:PER_QUERY]
                for it in picked:
                    it["query"] = kw
                    it["category"] = cat
                bucket += picked
                print(f"  [{cat}] {kw:<22} → {len(items):>2} 命中，取 {len(picked)}")
                time.sleep(3)
            found[cat] = bucket

        page.close()

    (OUT / "sku-candidates.json").write_text(
        json.dumps(found, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    total = sum(len(v) for v in found.values())
    print(f"\n=== 候选 {total} 个 ===")
    for cat, items in found.items():
        print(f"\n[{cat}]")
        for it in items:
            print(f"  {it['sku']:<16} {it['title'][:58]}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
