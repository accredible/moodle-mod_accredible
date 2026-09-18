#!/usr/bin/env bash
#
# Enable / disable / inspect Xdebug step debugging in a running moodle-docker
# webserver container, for debugging the mod_accredible plugin.
#
# The moodlehq/moodle-php-apache image does not ship Xdebug, so this compiles it
# with PECL inside the running container. That lives in the container's writable
# layer: it survives "docker restart/stop/start" but is LOST when the container
# is recreated ("docker compose down", image pull, etc). Just re-run this script.
#
# Usage:
#   scripts/xdebug.sh [enable|disable|status]
#
#     enable    install (if needed), configure and turn Xdebug on   (default)
#     disable   comment out the zend_extension line and restart
#     status    report whether Xdebug is loaded and with what settings
#
# Environment:
#   WEBSERVER_CONTAINER   container name (default: auto-detected)
#   XDEBUG_PORT           port your IDE listens on (default: 9003)
#   XDEBUG_IDEKEY         IDE key (default: PHPSTORM)
#
set -euo pipefail

ACTION="${1:-enable}"
XDEBUG_PORT="${XDEBUG_PORT:-9003}"
XDEBUG_IDEKEY="${XDEBUG_IDEKEY:-PHPSTORM}"
INI="/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"

detect_container() {
  local c
  c="$(docker ps --filter "ancestor=moodlehq/moodle-php-apache:8.3" --format '{{.Names}}' | head -1)"
  [ -n "$c" ] || c="$(docker ps --format '{{.Names}}' | grep -E 'webserver' | head -1)"
  echo "$c"
}

CONTAINER="${WEBSERVER_CONTAINER:-$(detect_container)}"
if [ -z "$CONTAINER" ]; then
  echo "Error: could not find a running moodle-docker webserver container." >&2
  echo "Start it first, or set WEBSERVER_CONTAINER=<name>." >&2
  exit 1
fi

dex() { docker exec -u 0 "$CONTAINER" "$@"; }

print_status() {
  echo "Container: $CONTAINER"
  if docker exec "$CONTAINER" php -m 2>/dev/null | grep -qi '^xdebug$'; then
    docker exec "$CONTAINER" php -r '
      printf("Xdebug:    %s (loaded)\n", phpversion("xdebug"));
      foreach (["xdebug.mode","xdebug.client_host","xdebug.client_port","xdebug.start_with_request","xdebug.idekey"] as $k) {
        printf("  %-26s %s\n", $k, ini_get($k));
      }'
  else
    echo "Xdebug:    not loaded"
  fi
}

case "$ACTION" in
  enable)
    echo "==> Using container: $CONTAINER"

    if dex test -f "$INI"; then
      echo "==> Xdebug already installed, re-enabling"
      dex sed -i 's/^; *zend_extension=/zend_extension=/' "$INI"
    else
      echo "==> Installing Xdebug with PECL (this compiles, ~1-2 min)"
      dex pecl install xdebug >/dev/null
      dex docker-php-ext-enable xdebug
      echo "==> Writing debug settings to $INI"
      dex bash -c "cat >> $INI <<EOF

; --- Live debugging settings (mod_accredible) ---
xdebug.mode = debug
xdebug.client_host = host.docker.internal
xdebug.client_port = ${XDEBUG_PORT}
xdebug.start_with_request = trigger
xdebug.idekey = ${XDEBUG_IDEKEY}
xdebug.discover_client_host = false
xdebug.log_level = 0
EOF"
    fi

    echo "==> Restarting $CONTAINER"
    docker restart "$CONTAINER" >/dev/null
    for _ in $(seq 1 30); do
      docker exec "$CONTAINER" php -v >/dev/null 2>&1 && break
      sleep 1
    done

    echo
    print_status
    echo
    echo "Start listening in your IDE on port ${XDEBUG_PORT}, then trigger a session:"
    echo "  browser: append ?XDEBUG_TRIGGER=1 (or use the Xdebug helper extension)"
    echo "  cli:     docker exec -e XDEBUG_TRIGGER=1 $CONTAINER php admin/cli/cron.php"
    ;;

  disable)
    echo "==> Disabling Xdebug in $CONTAINER"
    dex test -f "$INI" || { echo "Xdebug is not installed."; exit 0; }
    dex sed -i 's/^zend_extension=/; zend_extension=/' "$INI"
    docker restart "$CONTAINER" >/dev/null
    for _ in $(seq 1 30); do
      docker exec "$CONTAINER" php -v >/dev/null 2>&1 && break
      sleep 1
    done
    echo
    print_status
    ;;

  status)
    print_status
    ;;

  *)
    echo "Usage: scripts/xdebug.sh [enable|disable|status]" >&2
    exit 1
    ;;
esac
