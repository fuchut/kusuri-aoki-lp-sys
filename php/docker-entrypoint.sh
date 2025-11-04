#!/usr/bin/env bash
set -e
DOCROOT="/var/www/html"

# ===========================
# WP_VERSIONによるモード自動判定
# ===========================
if [ -z "${WP_VERSION}" ]; then
  MOUNT_MODE="all"
else
  MOUNT_MODE="content"
fi

echo "[init] MODE=${MOUNT_MODE}, WP_VERSION=${WP_VERSION:-<empty>}"

# ===========================
# モードA: 自動インストール (content)
# ===========================
if [ "${MOUNT_MODE}" = "content" ]; then
  echo "[init] content mode: installing WordPress into ${DOCROOT}"

  mkdir -p "$DOCROOT/wp-content"
  find "$DOCROOT/wp-content" -type d -print0 | xargs -0 chmod 777 || true
  find "$DOCROOT/wp-content" -type f -print0 | xargs -0 chmod 666 || true

  # WordPress が存在しない場合のみダウンロード
  if [ ! -f "$DOCROOT/wp-includes/version.php" ]; then
    echo "[init] Downloading WordPress ${WP_VERSION:-latest}..."
    wp core download --path="$DOCROOT" --version="${WP_VERSION:-latest}" --locale="${WP_LOCALE:-ja}"
  else
    echo "[init] WordPress core already exists — skip download."
  fi

  # wp-config.php 自動生成
  if [ ! -f "$DOCROOT/wp-config.php" ] && [ -n "$DB_HOST" ]; then
    echo "[init] Creating wp-config.php"
    wp config create \
      --path="$DOCROOT" \
      --dbname="${DB_NAME}" \
      --dbuser="${DB_USER}" \
      --dbpass="${DB_PASSWORD}" \
      --dbhost="${DB_HOST}:${DB_PORT}" \
      --skip-check
  fi

# ===========================
# モードB: 手動管理 (all)
# ===========================
else
  echo "[init] all mode: manual management — no WP install"

  # root 実行で開発用パーミッション
  sed -i 's/^export APACHE_RUN_USER=.*/export APACHE_RUN_USER=root/' /etc/apache2/envvars
  sed -i 's/^export APACHE_RUN_GROUP=.*/export APACHE_RUN_GROUP=root/' /etc/apache2/envvars
  echo 'umask 0002' >> /etc/apache2/envvars

  chown -R root:root "$DOCROOT" || true
  find "$DOCROOT" -type d -exec chmod 777 {} \;
  find "$DOCROOT" -type f -exec chmod 666 {} \;
fi

exec "$@"
