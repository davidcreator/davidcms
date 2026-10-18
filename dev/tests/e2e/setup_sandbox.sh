#!/usr/bin/env bash
# ═══ setup_sandbox.sh — reconstrói o sandbox E2E após reset do Arena ═══
# Idempotente. Stack: PHP8.4 + Apache :8090 (bind-mount) + MariaDB + composer.
# Layout servido: http://127.0.0.1:8090/davidcms/dev/<app>  (espelho vivo /tmp/fui)
set -u
echo "── pacotes"
if ! command -v php >/dev/null 2>&1; then
  sudo apt-get update -qq
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    php-cli php-mysql php-mbstring php-curl php-gd php-xml php-zip \
    apache2 libapache2-mod-php mariadb-server unzip curl git
fi
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer -q
fi
echo "── mariadb"
sudo service mariadb start
sudo mysql -e "CREATE USER IF NOT EXISTS 'reamur'@'127.0.0.1' IDENTIFIED BY 'reamur123';
  CREATE USER IF NOT EXISTS 'reamur'@'localhost' IDENTIFIED BY 'reamur123';
  GRANT ALL PRIVILEGES ON *.* TO 'reamur'@'127.0.0.1';
  GRANT ALL PRIVILEGES ON *.* TO 'reamur'@'localhost';
  FLUSH PRIVILEGES;"
echo "── espelho vivo /tmp/fui ← /home/user/davidcms/dev"
mkdir -p /tmp/fui && cp -a /home/user/davidcms/dev /tmp/fui/dev
mkdir -p /tmp/fui/dev/catalog/view/image/{project,category,testimonial,client_logo,tool}
for d in project category testimonial client_logo tool; do
  [ -f /tmp/fui/dev/catalog/view/image/$d/index.html ] || echo '<h1>Forbidden</h1>' > /tmp/fui/dev/catalog/view/image/$d/index.html
done
rm -f /tmp/fui/dev/catalog/config.php /tmp/fui/dev/admin/config.php
chmod -R a+w /tmp/fui
echo "── composer install (twig, phpstan...)"
cd /tmp/fui/dev/system/storage && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction -q
echo "── apache :8090 (bind-mount /var/www/html/davidcms → /tmp/fui)"
sudo mkdir -p /var/www/html/davidcms
sudo a2enmod rewrite -q
sudo bash -c 'cat > /etc/apache2/sites-available/suite8090.conf << "EOF"
Listen 8090
<VirtualHost *:8090>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options FollowSymLinks Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF'
sudo a2ensite suite8090 -q
if ! mountpoint -q /var/www/html/davidcms; then sudo mount --bind /tmp/fui /var/www/html/davidcms; fi
sudo service apache2 restart
sleep 1
curl -s -o /dev/null -w "install:%{http_code}\n" http://127.0.0.1:8090/davidcms/dev/install/index.php
echo "══ sandbox pronto. Rodar: bash tools/e2e/e2e_regressao.sh e as baterias da tarefa"
