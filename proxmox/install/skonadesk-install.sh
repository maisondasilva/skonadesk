#!/usr/bin/env bash

# Copyright (c) 2021-2026 community-scripts ORG
# Author: Mike Hayward (Skonamonkey)
# License: MIT | https://github.com/community-scripts/ProxmoxVE/raw/main/LICENSE
# Source: https://github.com/Skonamonkey/skonadesk

source /dev/stdin <<<"$FUNCTIONS_FILE_PATH"
color
verb_ip6
catch_errors
setting_up_container
network_check
update_os

msg_info "Installing dependencies"
$STD apt-get install -y curl openssl
msg_ok "Installed dependencies"

msg_info "Installing Docker"
mkdir -p /etc/docker
echo -e '{\n  "log-driver": "journald"\n}' >/etc/docker/daemon.json
$STD sh <(curl -fsSL https://get.docker.com)
msg_ok "Installed Docker"

msg_info "Downloading SkonaDesk stack"
INSTALL_DIR="/srv/skonadesk"
mkdir -p "${INSTALL_DIR}/data"
curl -fsSL "https://raw.githubusercontent.com/Skonamonkey/skonadesk/main/docker-compose.prod.yml" \
  -o "${INSTALL_DIR}/docker-compose.yml"
msg_ok "Downloaded SkonaDesk stack"

JWT_SECRET=$(openssl rand -hex 32)
APP_SECRET=$(openssl rand -hex 32)

read -r -p "${TAB3}Server address — domain name or IP (e.g. 192.168.1.100): " DOMAIN
while [[ -z "$DOMAIN" ]]; do
  read -r -p "${TAB3}Server address cannot be empty: " DOMAIN
done

read -r -p "${TAB3}Dashboard port [8080]: " DASHBOARD_PORT
DASHBOARD_PORT="${DASHBOARD_PORT:-8080}"

read -r -p "${TAB3}Admin username (not 'admin'): " ADMIN_USER
while [[ -z "$ADMIN_USER" || "$ADMIN_USER" == "admin" ]]; do
  read -r -p "${TAB3}Please choose a non-obvious admin username: " ADMIN_USER
done

read -r -s -p "${TAB3}Admin password (min 10 characters): " ADMIN_PASS
echo ""
while [[ ${#ADMIN_PASS} -lt 10 ]]; do
  read -r -s -p "${TAB3}Password must be at least 10 characters: " ADMIN_PASS
  echo ""
done
read -r -s -p "${TAB3}Confirm password: " ADMIN_PASS_CONFIRM
echo ""
while [[ "$ADMIN_PASS" != "$ADMIN_PASS_CONFIRM" ]]; do
  read -r -s -p "${TAB3}Passwords do not match. Admin password: " ADMIN_PASS
  echo ""
  read -r -s -p "${TAB3}Confirm password: " ADMIN_PASS_CONFIRM
  echo ""
done

cat > "${INSTALL_DIR}/.env" <<EOF
RELAY_HOST=${DOMAIN}
DOMAIN=${DOMAIN}

JWT_SECRET=${JWT_SECRET}
APP_SECRET=${APP_SECRET}

ADMIN_USER=${ADMIN_USER}
ADMIN_PASS=${ADMIN_PASS}

DB_PATH=/data/skonadesk.db
PORT=21114
DASHBOARD_PORT=${DASHBOARD_PORT}
EOF

msg_info "Pulling Docker images and starting SkonaDesk"
cd "${INSTALL_DIR}"
$STD docker compose pull
$STD docker compose up -d
msg_ok "SkonaDesk started"

motd_ssh
customize
cleanup_lxc
