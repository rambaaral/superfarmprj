#!/bin/bash
set -euo pipefail
mkdir -p /var/www/html /var/hpc-private
if [ ! -f /var/www/html/.submission-source-ready ]; then
  cp -a /opt/hpc/site/. /var/www/html/
  cp -a /opt/hpc/private/. /var/hpc-private/
  touch /var/www/html/.submission-source-ready
fi
mkdir -p /var/www/html/wp-content/mu-plugins
cp /opt/hpc/docker/wp-config.php /var/www/html/wp-config.php
cp /opt/hpc/docker/local-mail.php /var/www/html/wp-content/mu-plugins/submission-local-mail.php
if [ ! -f /var/hpc-private/salts.php ]; then
  php -r '$s="<?php\n";foreach(["AUTH_KEY","SECURE_AUTH_KEY","LOGGED_IN_KEY","NONCE_KEY","AUTH_SALT","SECURE_AUTH_SALT","LOGGED_IN_SALT","NONCE_SALT"] as $k)$s.="define(".var_export($k,true).",".var_export(bin2hex(random_bytes(48)),true).");\n";file_put_contents("/var/hpc-private/salts.php",$s);'
fi
chmod 700 /var/hpc-private
chmod 600 /var/hpc-private/salts.php /var/www/html/wp-config.php
chmod +x /var/hpc-private/runtime/bin/rhwp /var/hpc-private/runtime/bin/rhwp.bin /var/hpc-private/runtime/lib/ld-linux-x86-64.so.2
chown -R www-data:www-data /var/www/html /var/hpc-private
php /opt/hpc/docker/initialize.php
# Cron needs the database/URL environment even when launched outside Apache.
php -r '$s="#!/bin/bash\n";foreach(["DB_PASSWORD","SITE_URL"] as $k)$s.="export ".$k."=".escapeshellarg(getenv($k))."\n";$s.="exec /usr/local/bin/php \"\$@\"\n";file_put_contents("/var/hpc-private/cron-php",$s);'
chown www-data:www-data /var/hpc-private/cron-php
chmod 700 /var/hpc-private/cron-php
printf '*/5 * * * * www-data /var/hpc-private/cron-php /var/www/html/wp-cron.php\n*/5 * * * * www-data /var/hpc-private/cron-php /var/www/html/wp-content/plugins/hpc-feature-lab/operations-cron.php\n' > /etc/cron.d/hpc
chmod 644 /etc/cron.d/hpc
cron
mkdir -p /var/lib/clamav
chown clamav:clamav /var/lib/clamav
# Updates run asynchronously; the application blocks downloads until a real scan passes.
(freshclam --stdout || true; freshclam --daemon --foreground --stdout) &
exec "$@"
