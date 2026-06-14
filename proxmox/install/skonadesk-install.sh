#!/usr/bin/env bash

# Copyright (c) 2021-2026 community-scripts ORG
# Author: Mike Hayward (Skonamonkey)
# License: MIT | https://github.com/community-scripts/ProxmoxVED/raw/main/LICENSE
# Source: https://github.com/Skonamonkey/skonadesk

source /dev/stdin <<<"$FUNCTIONS_FILE_PATH"
color
verb_ip6
catch_errors
setting_up_container
network_check
update_os

msg_info "Installing Dependencies"
$STD apt-get install -y curl openssl
msg_ok "Installed Dependencies"

msg_info "Installing Docker"
DOCKER_CONFIG_PATH='/etc/docker/daemon.json'
mkdir -p "$(dirname "$DOCKER_CONFIG_PATH")"
cat >"$DOCKER_CONFIG_PATH" <<'EOF'
{
  "log-driver": "journald"
}
EOF
$STD sh <(curl -fsSL https://get.docker.com)
msg_ok "Installed Docker"

msg_info "Downloading SkonaDesk stack"
install_dir="/srv/skonadesk"
mkdir -p "${install_dir}/data"
curl -fsSL "https://raw.githubusercontent.com/Skonamonkey/skonadesk/main/docker-compose.prod.yml" \
  -o "${install_dir}/docker-compose.yml"
msg_ok "Downloaded SkonaDesk stack"

jwt_secret=$(openssl rand -hex 32)
app_secret=$(openssl rand -hex 32)

detected_ip=$(hostname -I 2>/dev/null | awk '{print $1}')
echo -e "${TAB3}Enter your server's IP or domain name. RustDesk clients will connect to this address."
echo -e "${TAB3}If you plan to use a reverse proxy (e.g. Nginx Proxy Manager), enter your domain now."
read -r -p "${TAB3}Server address [${detected_ip}]: " domain
domain="${domain:-${detected_ip}}"
while [[ -z "$domain" ]]; do
  read -r -p "${TAB3}Server address cannot be empty: " domain
done

read -r -p "${TAB3}Dashboard port [8080]: " dashboard_port
dashboard_port="${dashboard_port:-8080}"

read -r -p "${TAB3}Admin username (not 'admin'): " admin_user
while [[ -z "$admin_user" || "$admin_user" == "admin" ]]; do
  read -r -p "${TAB3}Please choose a non-obvious admin username: " admin_user
done

read -r -s -p "${TAB3}Admin password (min 10 characters): " admin_pass
echo ""
while [[ ${#admin_pass} -lt 10 ]]; do
  read -r -s -p "${TAB3}Password must be at least 10 characters: " admin_pass
  echo ""
done
read -r -s -p "${TAB3}Confirm password: " admin_pass_confirm
echo ""
while [[ "$admin_pass" != "$admin_pass_confirm" ]]; do
  read -r -s -p "${TAB3}Passwords do not match. Admin password: " admin_pass
  echo ""
  read -r -s -p "${TAB3}Confirm password: " admin_pass_confirm
  echo ""
done

msg_info "Writing configuration"
cat >"${install_dir}/.env" <<EOF
RELAY_HOST=${domain}
DOMAIN=${domain}

JWT_SECRET=${jwt_secret}
APP_SECRET=${app_secret}

ADMIN_USER=${admin_user}
ADMIN_PASS=${admin_pass}

DB_PATH=/data/skonadesk.db
PORT=21114
DASHBOARD_PORT=${dashboard_port}
EOF
msg_ok "Configuration written"

msg_info "Pulling Docker images and starting SkonaDesk"
cd "${install_dir}"
$STD docker compose pull
$STD docker compose up -d
msg_ok "SkonaDesk started"

motd_ssh
customize
cleanup_lxc
