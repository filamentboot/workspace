#!/bin/bash
# =============================================================================
# Filamentboot 服务器一次性迁移脚本
# 目标服务器：root@118.25.27.49（腾讯云 Debian 12）
# 执行方式：bash scripts/migrate-server.sh
#
# 前置条件（执行前请确认）：
#   1. Wave 1 改名已落地（代码跑绿，13-01~13-03 完成）
#   2. 核心包已提交 Packagist 并收录（packagist.org/packages/filamentboot/filamentboot 存在）
#      — 演示站步骤(7)依赖 Packagist，请先确认：
#      — curl -sf https://packagist.org/p2/filamentboot/filamentboot.json
#   3. filamentboot-demo repo 已 scaffold 并 push 到 GitHub（13-05 Task 3 完成）
#   4. 本脚本中 nginx vhost 模板 (nginx/demo.xitongapp.com.conf) 已在服务器上可用
#
# 执行顺序（RESEARCH Critical ordering，D-20）：
#   (1) 停旧 worker
#   (2) 移目录（破坏性，有确认提示）
#   (3) supervisor 改名
#   (4) 新建 demo 数据库
#   (5) nginx vhost
#   (6) webhook token 轮换
#   (7) 演示站部署（gate: Packagist 收录后）
#   (8) 验证
# =============================================================================

set -e

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo_step() {
    echo -e "\n${GREEN}[STEP $1]${NC} $2"
}

echo_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo_err() {
    echo -e "${RED}[ERROR]${NC} $1"
}

confirm() {
    echo -e "${YELLOW}[CONFIRM]${NC} $1"
    read -p "继续？(yes/no) " -r REPLY
    if [[ ! "$REPLY" == "yes" ]]; then
        echo "已取消。"
        exit 0
    fi
}

# =============================================================================
# 前置检查
# =============================================================================

echo "=================================================================="
echo "  Filamentboot 服务器迁移脚本"
echo "  目标服务器：118.25.27.49"
echo "=================================================================="
echo ""
echo_warn "执行前请确认以下条件："
echo "  1. Wave 1 改名已落地（代码跑绿）"
echo "  2. 核心包已提交 Packagist 并收录"
echo "     验证：curl -sf https://packagist.org/p2/filamentboot/filamentboot.json"
echo "  3. filamentboot-demo repo 已 scaffold 并 push 到 GitHub"
echo ""

# 检查 Packagist 收录状态（D-25，RESEARCH Pitfall 5）
echo_step "0" "检查 Packagist 收录状态（D-25 前置）"
if curl -sf https://packagist.org/p2/filamentboot/filamentboot.json > /dev/null 2>&1; then
    echo -e "${GREEN}[OK]${NC} packagist.org/packages/filamentboot/filamentboot 已收录"
else
    echo_err "Packagist 尚未收录 filamentboot/filamentboot"
    echo "演示站部署步骤 (7) 将被跳过，请先完成 INFRA-CHECKLIST.md Step 4 后重跑本脚本。"
    SKIP_DEMO_DEPLOY=true
fi

confirm "以上条件已确认，即将开始服务器迁移"

# =============================================================================
# 步骤 1：停旧 worker
# =============================================================================
echo_step "1" "停止旧 Supervisor worker（filament-admin-worker）"
if supervisorctl status filament-admin-worker: 2>/dev/null | grep -q RUNNING; then
    supervisorctl stop filament-admin-worker:*
    echo "[OK] filament-admin-worker 已停止"
else
    echo_warn "filament-admin-worker 未运行（可能已停止或未注册），跳过"
fi

# =============================================================================
# 步骤 2：移目录（破坏性操作，带确认）
# =============================================================================
echo_step "2" "迁移应用目录 /data/filament-admin → /data/filamentboot"

if [ -d /data/filamentboot ]; then
    echo_warn "/data/filamentboot 已存在，跳过目录移动"
elif [ ! -d /data/filament-admin ]; then
    echo_err "/data/filament-admin 不存在，无法迁移"
    echo "请检查旧目录路径，或手动创建 /data/filamentboot"
    exit 1
else
    echo_warn "将执行：cp -a /data/filament-admin /data/filamentboot"
    echo_warn "（保留旧目录 /data/filament-admin 作为回滚后路，稳定后手动删除）"
    confirm "确认复制目录（不删除旧目录）"
    cp -a /data/filament-admin /data/filamentboot
    echo "[OK] 目录已复制到 /data/filamentboot（旧目录保留）"
fi

# =============================================================================
# 步骤 3：Supervisor 改名
# =============================================================================
echo_step "3" "更新 Supervisor 配置（filament-admin-worker → filamentboot-worker）"

SUPERVISOR_CONF_DIR=/etc/supervisor/conf.d
OLD_CONF="$SUPERVISOR_CONF_DIR/filament-admin-worker.conf"
NEW_CONF="$SUPERVISOR_CONF_DIR/filamentboot-worker.conf"

if [ -f "$NEW_CONF" ]; then
    echo_warn "filamentboot-worker.conf 已存在，跳过创建"
elif [ -f "$OLD_CONF" ]; then
    echo "从旧配置生成新配置..."
    # 替换 program 名称和目录路径
    sed \
        -e 's/\[program:filament-admin-worker\]/[program:filamentboot-worker]/g' \
        -e 's|/data/filament-admin|/data/filamentboot|g' \
        -e 's/filament-admin-worker/filamentboot-worker/g' \
        "$OLD_CONF" > "$NEW_CONF"
    echo "[OK] 已生成 $NEW_CONF"
    echo "请检查内容："
    cat "$NEW_CONF"
    confirm "确认 supervisor 配置正确"
else
    echo_warn "未找到旧 supervisor 配置文件 $OLD_CONF，手动创建..."
    cat > "$NEW_CONF" << 'SUPERVISORCONF'
[program:filamentboot-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /data/filamentboot/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/data/filamentboot/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISORCONF
    echo "[OK] 已创建默认 supervisor 配置 $NEW_CONF"
    echo "如有自定义需求请手动修改 $NEW_CONF"
fi

echo "重新加载 supervisor..."
supervisorctl reread
supervisorctl update
supervisorctl start filamentboot-worker:*
supervisorctl status filamentboot-worker:*

# =============================================================================
# 步骤 4：新建 demo 数据库
# =============================================================================
echo_step "4" "新建演示站数据库 filamentboot_demo"
mysql -e "CREATE DATABASE IF NOT EXISTS filamentboot_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
    && echo "[OK] filamentboot_demo 数据库已创建（或已存在）" \
    || echo_warn "数据库创建失败，请手动执行：mysql -e \"CREATE DATABASE IF NOT EXISTS filamentboot_demo CHARACTER SET utf8mb4\""

# =============================================================================
# 步骤 5：nginx vhost
# =============================================================================
echo_step "5" "配置 nginx vhost（demo.xitongapp.com）"

NGINX_CONF_SRC=/data/filamentboot/nginx/demo.xitongapp.com.conf
NGINX_CONF_DEST=/etc/nginx/conf.d/demo.xitongapp.com.conf

if [ -f "$NGINX_CONF_DEST" ]; then
    echo_warn "$NGINX_CONF_DEST 已存在，跳过复制"
elif [ -f "$NGINX_CONF_SRC" ]; then
    cp "$NGINX_CONF_SRC" "$NGINX_CONF_DEST"
    echo "[OK] 已复制 vhost 配置"
else
    echo_warn "未找到 nginx 模板 $NGINX_CONF_SRC，请手动配置"
    echo "模板内容见本仓库 nginx/demo.xitongapp.com.conf"
fi

# 申请/续期 SSL 证书（若尚无 demo.xitongapp.com 证书）
if [ ! -d /root/.acme.sh/demo.xitongapp.com ]; then
    echo "正在申请 demo.xitongapp.com SSL 证书..."
    echo_warn "请确保 nginx 已在监听 80 端口且 demo.xitongapp.com DNS 已解析到此服务器"
    confirm "确认 DNS 已配置，即将申请证书"
    /root/.acme.sh/acme.sh --issue -d demo.xitongapp.com --webroot /data/filamentboot/public
    /root/.acme.sh/acme.sh --install-cert -d demo.xitongapp.com \
        --cert-file /etc/nginx/ssl/demo.xitongapp.com.crt \
        --key-file /etc/nginx/ssl/demo.xitongapp.com.key \
        --reloadcmd "systemctl reload nginx"
    echo "[OK] SSL 证书已申请"
else
    echo "[OK] demo.xitongapp.com SSL 证书已存在，跳过申请"
fi

nginx -t && systemctl reload nginx && echo "[OK] nginx 配置验证通过，已 reload"

# =============================================================================
# 步骤 6：webhook token 轮换（D-18）
# =============================================================================
echo_step "6" "轮换 webhook token（D-18）"
WEBHOOK_TOKEN_FILE=/data/filamentboot/.webhook_token

if [ -f "$WEBHOOK_TOKEN_FILE" ]; then
    echo_warn "$WEBHOOK_TOKEN_FILE 已存在，跳过生成（如需强制轮换请手动删除后重跑）"
else
    openssl rand -hex 32 > "$WEBHOOK_TOKEN_FILE"
    chmod 600 "$WEBHOOK_TOKEN_FILE"
    echo "[OK] 新 webhook token 已生成：$WEBHOOK_TOKEN_FILE"
fi

echo ""
echo_warn "请手动完成以下操作："
echo "  1. 查看新 token：cat $WEBHOOK_TOKEN_FILE"
echo "  2. 登录 Gitee → workspace 仓库 → 管理 → WebHooks"
echo "  3. 将旧 webhook URL 中的 token 替换为新值"
echo "  （见 INFRA-CHECKLIST.md Step 6）"

# =============================================================================
# 步骤 7：演示站部署（gate: Packagist 收录后，D-25）
# =============================================================================
echo_step "7" "演示站部署（filamentboot-demo repo）"

if [ "${SKIP_DEMO_DEPLOY:-false}" = "true" ]; then
    echo_warn "Packagist 尚未收录，跳过演示站部署（D-25）"
    echo "Packagist 收录后，请在 /data 目录执行："
    echo "  git clone https://github.com/filamentboot/filamentboot-demo.git /data/filamentboot"
    echo "  cd /data/filamentboot && composer2 install --no-dev && php artisan filamentboot:install"
    echo "  php artisan migrate --force && php artisan optimize"
else
    echo_warn "演示站部署：将 clone filamentboot-demo repo 并安装..."
    confirm "确认 Packagist 已收录 filamentboot/filamentboot，即将部署演示站"

    # 若 /data/filamentboot 已是完整 laravel 应用则 pull，否则 clone
    if [ -d /data/filamentboot/.git ]; then
        cd /data/filamentboot
        git pull origin main
    else
        # /data/filamentboot 是 cp 的 workspace，替换为 demo repo
        echo_warn "/data/filamentboot 当前为 workspace 副本（cp -a）"
        echo "演示站需要 filamentboot-demo repo，请选择处理方式："
        echo "  a) 将 /data/filamentboot 替换为 filamentboot-demo clone"
        echo "  b) 使用不同路径（如 /data/filamentboot-demo）"
        read -p "选择 (a/b): " -r DEPLOY_CHOICE
        if [[ "$DEPLOY_CHOICE" == "a" ]]; then
            confirm "将删除 /data/filamentboot 并 clone filamentboot-demo"
            rm -rf /data/filamentboot
            git clone https://github.com/filamentboot/filamentboot-demo.git /data/filamentboot
            cd /data/filamentboot
        else
            git clone https://github.com/filamentboot/filamentboot-demo.git /data/filamentboot-demo
            cd /data/filamentboot-demo
        fi
    fi

    echo "[deploy] 安装生产依赖..."
    COMPOSER_ALLOW_SUPERUSER=1 composer2 install --no-dev --optimize-autoloader --no-interaction

    echo "[deploy] 运行安装命令..."
    php artisan filamentboot:install || echo_warn "filamentboot:install 失败，请手动检查"

    echo "[deploy] 执行数据库迁移..."
    php artisan migrate --force

    echo "[deploy] 优化缓存..."
    php artisan optimize

    echo "[OK] 演示站部署完成"
fi

# =============================================================================
# 步骤 8：验证
# =============================================================================
echo_step "8" "验证（本地 curl 测试）"
HTTP_STATUS=$(curl -sI -o /dev/null -w "%{http_code}" \
    http://127.0.0.1 -H 'Host: demo.xitongapp.com' 2>/dev/null || echo "000")

if [[ "$HTTP_STATUS" =~ ^(200|301|302)$ ]]; then
    echo -e "${GREEN}[OK]${NC} demo.xitongapp.com 本地响应：HTTP $HTTP_STATUS"
else
    echo_warn "demo.xitongapp.com 本地响应：HTTP $HTTP_STATUS（非 200/301/302）"
    echo "请检查 nginx 配置和应用状态"
fi

# =============================================================================
# 完成
# =============================================================================
echo ""
echo "=================================================================="
echo -e "${GREEN}迁移脚本执行完成${NC}"
echo "=================================================================="
echo ""
echo "后续手动操作（请参照 INFRA-CHECKLIST.md）："
echo "  - Step 6: 更新 Gitee webhook URL（使用新 token）"
echo "  - Step 7: 更新所有 DEPLOY_KEY secret，移除旧 SSH key"
echo "  - 验证 demo.xitongapp.com 可通过浏览器登录（demo@example.com / demo123）"
echo ""
echo "回滚参考（若需要）："
echo "  - 旧目录：/data/filament-admin（已保留）"
echo "  - 旧 supervisor conf：$OLD_CONF（已保留）"
echo "  - 旧数据库：filamentadmin_demo（已保留）"
