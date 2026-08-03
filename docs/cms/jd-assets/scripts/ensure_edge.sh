#!/usr/bin/env bash
# 确保自动化 Edge 实例在 9222 上可用；已在跑则原样返回
#
# 用的是 profile 副本 ~/.config/edge-automation，与日常 Edge
# （~/.config/microsoft-edge）完全隔离，互不影响。
# 副本里的 cookie 是从日常 profile 热拷贝来的，京东登录态随之带过来。

set -u
DST="$HOME/.config/edge-automation"
LOG=/tmp/edge-automation.log

if curl -s --max-time 2 http://127.0.0.1:9222/json/version >/dev/null 2>&1; then
    echo "[ok] 9222 已在服务"
    exit 0
fi

# profile 副本不在（首次或被删）则重新热拷贝
if [ ! -f "$DST/Default/Cookies" ]; then
    SRC="$HOME/.config/microsoft-edge"
    echo "[..] 重建 profile 副本"
    mkdir -p "$DST/Default"
    cp "$SRC/Local State" "$DST/" 2>/dev/null
    cp "$SRC/Default/Cookies" "$SRC/Default/Preferences" "$DST/Default/" 2>/dev/null
    cp -r "$SRC/Default/Local Storage" "$DST/Default/" 2>/dev/null
fi

echo "[..] 启动自动化 Edge"
nohup microsoft-edge --remote-debugging-port=9222 \
    --user-data-dir="$DST" \
    --no-first-run --no-default-browser-check \
    --disable-session-crashed-bubble --hide-crash-restore-bubble \
    about:blank >"$LOG" 2>&1 &

for _ in $(seq 1 20); do
    sleep 1
    if curl -s --max-time 2 http://127.0.0.1:9222/json/version >/dev/null 2>&1; then
        echo "[ok] 9222 就绪"
        exit 0
    fi
done

echo "[FAIL] 20s 内未就绪，看 $LOG"
exit 1
