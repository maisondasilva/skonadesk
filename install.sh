#!/usr/bin/env bash
set -euo pipefail

# ─── SkonaDesk Installer ─────────────────────────────────────────────────────

REPO="Skonamonkey/skonadesk"
INSTALL_DIR="/srv/skonadesk"
COMPOSE_URL="https://raw.githubusercontent.com/${REPO}/main/docker-compose.prod.yml"
ENV_EXAMPLE_URL="https://raw.githubusercontent.com/${REPO}/main/.env.example"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

banner() {
    echo ""
    echo -e "${CYAN}${BOLD}"
    echo "  ███████╗██╗  ██╗ ██████╗ ███╗   ██╗ █████╗ ██████╗ ███████╗███████╗██╗  ██╗"
    echo "  ██╔════╝██║ ██╔╝██╔═══██╗████╗  ██║██╔══██╗██╔══██╗██╔════╝██╔════╝██║ ██╔╝"
    echo "  ███████╗█████╔╝ ██║   ██║██╔██╗ ██║███████║██║  ██║█████╗  ███████╗█████╔╝ "
    echo "  ╚════██║██╔═██╗ ██║   ██║██║╚██╗██║██╔══██║██║  ██║██╔══╝  ╚════██║██╔═██╗ "
    echo "  ███████║██║  ██╗╚██████╔╝██║ ╚████║██║  ██║██████╔╝███████╗███████║██║  ██╗"
    echo "  ╚══════╝╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝╚═════╝ ╚══════╝╚══════╝╚═╝  ╚═╝"
    echo -e "${NC}"
    echo -e "  ${BOLD}Self-hosted RustDesk Pro-compatible stack${NC}"
    echo -e "  ${YELLOW}https://github.com/${REPO}${NC}"
    echo ""
}

info()    { echo -e "  ${GREEN}✓${NC}  $*"; }
warn()    { echo -e "  ${YELLOW}⚠${NC}  $*"; }
error()   { echo -e "  ${RED}✗${NC}  $*" >&2; }
prompt()  { echo -e "  ${CYAN}?${NC}  $*"; }
section() { echo -e "\n${BOLD}── $* ──${NC}"; }

gen_secret() {
    openssl rand -hex 32
}

# ─── Prerequisite checks ──────────────────────────────────────────────────────

banner

section "Checking prerequisites"

if [ "$EUID" -ne 0 ]; then
    error "Please run as root: sudo bash install.sh"
    exit 1
fi

for cmd in docker curl openssl; do
    if command -v "$cmd" &>/dev/null; then
        info "$cmd found"
    else
        error "$cmd is required but not installed"
        echo ""
        echo "  Install with:"
        [ "$cmd" = "docker" ] && echo "    curl -fsSL https://get.docker.com | sh"
        [ "$cmd" = "curl" ]   && echo "    apt-get install -y curl"
        [ "$cmd" = "openssl" ] && echo "    apt-get install -y openssl"
        exit 1
    fi
done

if docker compose version &>/dev/null 2>&1; then
    info "docker compose (v2) found"
elif docker-compose version &>/dev/null 2>&1; then
    error "Only Docker Compose v1 found. Please upgrade to Compose v2."
    echo "    https://docs.docker.com/compose/migrate/"
    exit 1
else
    error "Docker Compose not found"
    exit 1
fi

# ─── Installation directory ───────────────────────────────────────────────────

section "Installation directory"

prompt "Install to [${INSTALL_DIR}]? Press Enter to confirm or type a new path:"
read -r custom_dir < /dev/tty
INSTALL_DIR="${custom_dir:-$INSTALL_DIR}"

if [ -d "$INSTALL_DIR" ] && [ -f "$INSTALL_DIR/.env" ]; then
    warn "An existing installation was found at ${INSTALL_DIR}"
    prompt "Overwrite? This will NOT delete your data directory. (y/N):"
    read -r overwrite < /dev/tty
    [[ "$overwrite" =~ ^[Yy]$ ]] || { echo "  Aborted."; exit 0; }
fi

mkdir -p "$INSTALL_DIR/data"
info "Directory ready: ${INSTALL_DIR}"

# ─── Download files ───────────────────────────────────────────────────────────

section "Downloading stack files"

curl -fsSL "$COMPOSE_URL" -o "$INSTALL_DIR/docker-compose.yml"
info "docker-compose.yml downloaded"

curl -fsSL "$ENV_EXAMPLE_URL" -o "$INSTALL_DIR/.env.example"
info ".env.example downloaded"

# ─── Configuration ────────────────────────────────────────────────────────────

section "Configuration"

echo ""
echo "  You will need:"
echo "    • A domain name pointing to this server (e.g. rustdesk.example.com)"
echo "    • Port 21115-21119 open in your firewall (TCP)"
echo "    • Port 21116 open for UDP"
echo "    • A reverse proxy (e.g. Nginx Proxy Manager) for SSL on port 443"
echo ""

prompt "Your domain name (e.g. rustdesk.example.com):"
read -r domain < /dev/tty
while [ -z "$domain" ]; do
    warn "Domain cannot be empty."
    prompt "Your domain name:"
    read -r domain < /dev/tty
done

prompt "Admin username (do NOT use 'admin'):"
read -r admin_user < /dev/tty
while [ -z "$admin_user" ] || [ "$admin_user" = "admin" ]; do
    warn "Please choose a non-obvious admin username."
    prompt "Admin username:"
    read -r admin_user < /dev/tty
done

prompt "Admin password:"
read -rs admin_pass < /dev/tty
echo ""
while [ ${#admin_pass} -lt 10 ]; do
    warn "Password must be at least 10 characters."
    prompt "Admin password:"
    read -rs admin_pass < /dev/tty
    echo ""
done

jwt_secret=$(gen_secret)
app_secret=$(gen_secret)
info "JWT secret generated"
info "App secret generated"

cat > "$INSTALL_DIR/.env" <<EOF
RELAY_HOST=${domain}
DOMAIN=${domain}

JWT_SECRET=${jwt_secret}
APP_SECRET=${app_secret}

ADMIN_USER=${admin_user}
ADMIN_PASS=${admin_pass}

DB_PATH=/data/skonadesk.db
PORT=21114
EOF

info ".env written"

# ─── Pull images and start ────────────────────────────────────────────────────

section "Pulling Docker images"

cd "$INSTALL_DIR"
docker compose pull
info "All images pulled"

section "Starting SkonaDesk"

docker compose up -d
info "Stack started"

# ─── Verify ───────────────────────────────────────────────────────────────────

section "Verifying containers"

sleep 3
all_ok=true
for container in skonadesk-hbbs skonadesk-hbbr skonadesk-api skonadesk-dashboard; do
    status=$(docker inspect -f '{{.State.Status}}' "$container" 2>/dev/null || echo "missing")
    if [ "$status" = "running" ]; then
        info "$container — running"
    else
        error "$container — $status"
        all_ok=false
    fi
done

# ─── Post-install summary ─────────────────────────────────────────────────────

echo ""
echo -e "${BOLD}────────────────────────────────────────────────────────────${NC}"

if $all_ok; then
    echo -e "  ${GREEN}${BOLD}Installation complete!${NC}"
else
    echo -e "  ${YELLOW}${BOLD}Installation finished with warnings — check container logs above.${NC}"
fi

echo ""
echo -e "  ${BOLD}Next steps:${NC}"
echo ""
echo "  1. Set up your reverse proxy (Nginx Proxy Manager recommended):"
echo "     • ${domain}           → skonadesk-api:21114       (SSL, port 443)"
echo "     • dashboard.${domain} → skonadesk-dashboard:80    (SSL, port 443)"
echo "     The NPM container must be on the 'skonadesk' Docker network."
echo ""
echo "  2. Open firewall ports:"
echo "     ufw allow 21115:21119/tcp"
echo "     ufw allow 21116/udp"
echo ""
echo "  3. Configure the RustDesk client:"
echo "     ID/Relay Server : ${domain}"
echo "     API Server      : https://${domain}"
echo ""
echo "  4. Get your server's public key from the admin dashboard:"
echo "     https://dashboard.${domain}  →  Server page"
echo ""
echo "  5. Log in to the admin dashboard:"
echo "     Username: ${admin_user}"
echo "     URL: https://dashboard.${domain}"
echo ""
echo -e "  ${YELLOW}Your .env file is at: ${INSTALL_DIR}/.env${NC}"
echo -e "  ${YELLOW}Keep it safe — it contains your secrets.${NC}"
echo ""
echo -e "${BOLD}────────────────────────────────────────────────────────────${NC}"
echo ""
