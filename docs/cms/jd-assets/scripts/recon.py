#!/usr/bin/env python
"""
京东商品页踩点脚本（reconnaissance）

不做提取，只回答三个问题：
  1. CDP 接管是否成功、登录态是否还在
  2. 商品页哪些 DOM 节点承载 标题/价格/参数/图片
  3. 评论数据走哪个 XHR 接口、响应长什么样

产物写到 out/recon/，终端只打印摘要（避免刷屏）。

用法：
  ~/.claude/skills/webapp-testing/.venv/bin/python recon.py [SKU_URL]
"""

import json
import re
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

CDP_URL = "http://127.0.0.1:9222"
DEFAULT_SKU = "https://item.jd.com/10112370809583.html"
OUT = Path(__file__).parent / "out" / "recon"

# 值得留意的接口特征：评论、价格、详情、推荐
INTERESTING = re.compile(
    r"(comment|club\.jd|api\.m\.jd|p\.3\.cn|prices|description|cd\.jd|recommend|rec\.jd)",
    re.I,
)


def main() -> int:
    url = sys.argv[1] if len(sys.argv) > 1 else DEFAULT_SKU
    OUT.mkdir(parents=True, exist_ok=True)

    captured: list[dict] = []

    with sync_playwright() as p:
        # attach 而非 launch：复用真实 profile 的 cookie / 指纹 / TLS 栈
        try:
            browser = p.chromium.connect_over_cdp(CDP_URL)
        except Exception as exc:
            print(f"[FAIL] 连不上 CDP {CDP_URL}")
            print(f"       {type(exc).__name__}: {exc}")
            print("       Edge 是否已带 --remote-debugging-port=9222 启动？")
            return 1

        ctx = browser.contexts[0] if browser.contexts else browser.new_context()
        print(f"[ok] CDP 已连接，contexts={len(browser.contexts)} pages={len(ctx.pages)}")

        page = ctx.new_page()

        def on_response(resp):
            if not INTERESTING.search(resp.url):
                return
            rec = {
                "url": resp.url[:300],
                "status": resp.status,
                "ctype": (resp.header_value("content-type") or "")[:60],
            }
            # 只留 JSON，且单独落盘，避免把正文塞进摘要
            try:
                if "json" in rec["ctype"].lower() or resp.url.endswith(".json"):
                    body = resp.text()
                    rec["len"] = len(body)
                    idx = len(captured)
                    (OUT / f"xhr-{idx:03d}.json").write_text(body, encoding="utf-8")
                    rec["saved"] = f"xhr-{idx:03d}.json"
            except Exception as exc:
                rec["err"] = f"{type(exc).__name__}"
            captured.append(rec)

        page.on("response", on_response)

        print(f"[..] 打开 {url}")
        page.goto(url, wait_until="domcontentloaded", timeout=60_000)
        try:
            page.wait_for_load_state("networkidle", timeout=30_000)
        except Exception:
            print("[warn] networkidle 超时，继续（京东常有长连接）")

        # ---- 登录态判定 ----
        html = page.content()
        logged_in = ("请登录" not in html[:200_000]) and ("passport.jd.com/uc/login" not in page.url)
        print(f"[{'ok' if logged_in else 'WARN'}] 登录态: {'已登录' if logged_in else '疑似未登录/被拦截'}")
        print(f"[..] 最终 URL: {page.url[:120]}")
        print(f"[..] 标题: {page.title()[:80]}")

        (OUT / "page.html").write_text(html, encoding="utf-8")
        page.screenshot(path=str(OUT / "page.png"), full_page=False)

        # ---- DOM 候选节点探测 ----
        probes = {
            "标题": ["div.sku-name", ".itemInfo-wrap .sku-name", "h1", ".product-intro .sku-name"],
            "价格": [".p-price .price", "span.price.J-p-" , ".summary-price .p-price", ".price"],
            "参数表": ["#detail .Ptable", ".Ptable", "#J-detail-content table", ".parameter2"],
            "主图": ["#spec-list img", ".spec-items img", "#spec-img", ".lh img"],
            "详情图": ["#J-detail-content img", ".detail-content img", "#detail img"],
            "评价Tab": ["#detail .tab-main li", ".tab-main a", "li[data-anchor='#comment']", "#comment"],
            "品牌": ["#parameter-brand li", ".parameter2 li", "#crumb-wrap .item a"],
        }
        print("\n=== DOM 候选（命中数 / 首个样本） ===")
        dom_report = {}
        for label, sels in probes.items():
            hits = []
            for sel in sels:
                try:
                    n = page.locator(sel).count()
                except Exception:
                    n = -1
                if n > 0:
                    sample = ""
                    try:
                        sample = (page.locator(sel).first.inner_text(timeout=2000) or "").strip()[:60]
                    except Exception:
                        try:
                            sample = (page.locator(sel).first.get_attribute("src") or "")[:80]
                        except Exception:
                            sample = "(取样失败)"
                    hits.append({"sel": sel, "count": n, "sample": sample.replace("\n", " ")})
            dom_report[label] = hits
            if hits:
                h = hits[0]
                print(f"  {label:<8} {h['count']:>4} × {h['sel']:<28} → {h['sample']}")
            else:
                print(f"  {label:<8} {'---':>4}   全部选择器未命中")

        # ---- 触发评论加载 ----
        print("\n[..] 尝试点开评价 tab")
        for sel in ["li[data-anchor='#comment']", "text=商品评价", ".tab-main li:has-text('评价')"]:
            try:
                page.locator(sel).first.click(timeout=4000)
                print(f"[ok] 点中 {sel}")
                break
            except Exception:
                continue
        else:
            print("[warn] 没点到评价 tab，改用滚动触发")

        for _ in range(6):
            page.mouse.wheel(0, 1400)
            page.wait_for_timeout(900)
        page.wait_for_timeout(2500)

        # ---- XHR 摘要 ----
        print(f"\n=== 捕获到 {len(captured)} 条相关响应 ===")
        for rec in captured:
            saved = rec.get("saved", "")
            size = rec.get("len", "")
            print(f"  [{rec['status']}] {size:>7} {saved:<16} {rec['url'][:110]}")

        (OUT / "xhr-index.json").write_text(
            json.dumps(captured, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        (OUT / "dom-report.json").write_text(
            json.dumps(dom_report, ensure_ascii=False, indent=2), encoding="utf-8"
        )

        # ---- 图片 URL 采样（判断分流规则是否成立）----
        imgs = page.eval_on_selector_all(
            "img",
            "els => els.map(e => e.currentSrc || e.src || e.getAttribute('data-lazy-img') || '')"
            ".filter(u => u && u.includes('360buyimg'))",
        )
        uniq = sorted(set(imgs))
        (OUT / "images-sample.txt").write_text("\n".join(uniq), encoding="utf-8")
        print(f"\n=== 360buyimg 图片 {len(uniq)} 张，路径前缀分布 ===")
        prefixes: dict[str, int] = {}
        for u in uniq:
            m = re.search(r"360buyimg\.com/([^/]+)/", u)
            key = m.group(1) if m else "(其他)"
            prefixes[key] = prefixes.get(key, 0) + 1
        for k, v in sorted(prefixes.items(), key=lambda kv: -kv[1]):
            print(f"  {k:<16} {v:>4}")

        print(f"\n[done] 产物在 {OUT}")
        page.close()

    return 0


if __name__ == "__main__":
    sys.exit(main())
