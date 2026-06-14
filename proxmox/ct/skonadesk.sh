#!/usr/bin/env bash
source <(curl -fsSL https://raw.githubusercontent.com/community-scripts/ProxmoxVED/main/misc/build.func)
# Author: Mike Hayward (Skonamonkey)
# License: MIT | https://github.com/Skonamonkey/skonadesk/raw/main/LICENSE
# Source: https://github.com/Skonamonkey/skonadesk

COMMUNITY_SCRIPTS_URL="${COMMUNITY_SCRIPTS_URL:-https://raw.githubusercontent.com/Skonamonkey/skonadesk/main/proxmox}"

APP="SkonaDesk"
var_tags="${var_tags:-docker;remote-desktop}"
var_cpu="${var_cpu:-2}"
var_ram="${var_ram:-2048}"
var_disk="${var_disk:-8}"
var_os="${var_os:-debian}"
var_version="${var_version:-12}"
var_arm64="${var_arm64:-no}"
var_unprivileged="${var_unprivileged:-1}"

header_info "$APP"
variables
color
catch_errors

function update_script() {
  header_info
  check_container_storage
  check_container_resources

  if [[ ! -f /srv/skonadesk/docker-compose.yml ]]; then
    msg_error "No ${APP} Installation Found!"
    exit
  fi
  msg_info "Pulling latest SkonaDesk images"
  cd /srv/skonadesk
  $STD docker compose pull
  $STD docker compose up -d --force-recreate
  msg_ok "Updated ${APP}"
  exit
}

start
build_container
description

DASH_PORT=$(pct exec "$CTID" -- bash -c "grep '^DASHBOARD_PORT=' /srv/skonadesk/.env 2>/dev/null | cut -d= -f2" 2>/dev/null)
DASH_PORT="${DASH_PORT:-8080}"

msg_ok "Completed Successfully!\n"
echo -e "${CREATING}${GN}${APP} setup has been successfully initialized!${CL}"
echo -e "${INFO}${YW} Access the dashboard at:${CL}"
echo -e "${TAB}${GATEWAY}${BGN}http://${IP}:${DASH_PORT}${CL}"
echo -e "${INFO}${YW} RustDesk clients should point to: ${IP}${CL}"
echo -e "${INFO}${YW} Firewall ports required: 21114 (TCP), 21115-21119 (TCP), 21116 (UDP)${CL}"
