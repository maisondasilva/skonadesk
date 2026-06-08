#!/usr/bin/env bash
source <(curl -fsSL https://raw.githubusercontent.com/community-scripts/ProxmoxVE/main/misc/build.func)
# Copyright (c) 2021-2026 community-scripts ORG
# Author: Mike Hayward (Skonamonkey)
# License: MIT | https://github.com/community-scripts/ProxmoxVE/raw/main/LICENSE
# Source: https://github.com/Skonamonkey/skonadesk

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
  if [[ ! -f /srv/skonadesk/docker-compose.yml ]]; then
    msg_error "No ${APP} Installation Found!"
    exit
  fi
  msg_info "Pulling latest SkonaDesk images"
  cd /srv/skonadesk
  docker compose pull
  docker compose up -d --force-recreate
  msg_ok "Updated ${APP}"
  exit
}

start
build_container
description

msg_ok "Completed successfully!\n"
echo -e "${CREATING}${GN}${APP} setup has been successfully initialized!${CL}"
echo -e "${INFO}${YW}Access the dashboard at:${CL}"
echo -e "${GATEWAY}${BGN}http://${IP}:8080${CL}"
echo -e "${INFO}${YW}RustDesk clients should point to: ${IP}${CL}"
echo -e "${INFO}${YW}To reconfigure: nano /srv/skonadesk/.env && cd /srv/skonadesk && docker compose up -d${CL}"
