#!/usr/bin/env python
"""
生成图片联系表 HTML，供人工挑图

抽样发现：京东的主图轮播里混着三类东西，不能整批直接用——
  a. 干净产品图
  b. 品牌官方营销图（带功能文案，可作场景图用）
  c. 店铺自制营销图 / 带京东认证角标（不可用，放自己站上一眼是扒的）
详情长图同理。所以主图也需要人工过一遍，不只是详情图。

产出 out/contact-sheet.html，浏览器打开即可。每张图标了
「SKU/组别/序号」，挑好后把编号报回来即可。

用法：~/.claude/skills/webapp-testing/.venv/bin/python contact_sheet.py
"""

import json
import subprocess
from pathlib import Path

BASE = Path(__file__).parent / "out"
IMG_DIR = BASE / "images"
OUT = BASE / "contact-sheet.html"


def dims(p: Path) -> str:
    """用 ffprobe 取尺寸（本机无 ImageMagick/PIL）"""
    try:
        r = subprocess.run(
            ["ffprobe", "-v", "error", "-select_streams", "v:0",
             "-show_entries", "stream=width,height", "-of", "csv=p=0:s=x", str(p)],
            capture_output=True, text=True, timeout=10,
        )

        return r.stdout.strip() or "?"
    except Exception:
        return "?"


def main() -> int:
    products = {r["sku"]: r for r in json.loads(
        (BASE / "products" / "products.json").read_text(encoding="utf-8")
    )}

    parts = ["""<meta charset="utf-8"><title>京东素材联系表</title>
<style>
 body{font:14px/1.5 system-ui,sans-serif;margin:0;padding:24px;background:#111;color:#eee}
 h2{margin:32px 0 4px;font-size:16px;border-left:3px solid #4ade80;padding-left:8px}
 .meta{color:#888;font-size:12px;margin-bottom:10px}
 .grp{margin:10px 0 18px}
 .lbl{color:#4ade80;font-size:12px;margin:6px 0}
 .row{display:flex;flex-wrap:wrap;gap:10px}
 figure{margin:0;width:150px}
 img{width:150px;height:150px;object-fit:contain;background:#222;border:1px solid #333;border-radius:4px}
 figcaption{font-size:11px;color:#999;text-align:center;margin-top:3px;word-break:break-all}
 .sum{position:sticky;top:0;background:#000;padding:12px;border-bottom:1px solid #333;z-index:9}
</style>
<div class="sum"><b>京东素材联系表</b> — 每图下方是「组别/序号 尺寸」。
挑好后把要留的编号报回来（如 <code>100193155629 main 2,4</code>）。
<br>注意剔除：带京东角标、店铺自制营销图、含真人的场景图。</div>
"""]

    stats = {"main": 0, "detail": 0}
    for sku in sorted(IMG_DIR.iterdir()):
        if not sku.is_dir():
            continue
        rec = products.get(sku.name, {})
        name = (rec.get("name") or "?")[:70]
        parts.append(
            f'<h2>{sku.name}</h2><div class="meta">{name}<br>'
            f'品牌 {rec.get("brand") or "—"} · ¥{rec.get("price") or "—"} · '
            f'<a style="color:#60a5fa" href="{rec.get("url","")}" target="_blank">京东页</a></div>'
        )
        for kind in ("main", "detail"):
            d = sku / kind
            if not d.is_dir():
                continue
            files = sorted(f for f in d.iterdir() if f.is_file())
            if not files:
                continue
            stats[kind] += len(files)
            parts.append(f'<div class="grp"><div class="lbl">{kind} ({len(files)})</div><div class="row">')
            for f in files:
                rel = f.relative_to(BASE)
                parts.append(
                    f'<figure><img loading="lazy" src="{rel}">'
                    f'<figcaption>{kind[0]}{f.stem} {dims(f)}</figcaption></figure>'
                )
            parts.append("</div></div>")

    OUT.write_text("\n".join(parts), encoding="utf-8")
    print(f"主图 {stats['main']} 张，详情候选 {stats['detail']} 张")
    print(f"→ file://{OUT}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
