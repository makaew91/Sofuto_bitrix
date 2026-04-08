#!/bin/bash
#
# Restore pre-configured DB dump and upload files into a freshly-installed Bitrix.
#
# Run this after you've installed Bitrix via bitrixsetup.php with DB credentials
# host=db, database=bitrix, user=bitrix, password=bitrix.
#
# What it does:
#   1. Drops the current bitrix DB
#   2. Restores docker/db/dump.sql.gz (iblocks, products, offers, prices, pictures meta)
#   3. Extracts docker/db/upload.tar.gz into /var/www/html/upload/ (product images)
#
# After this the main page shows 2 products at http://localhost:8080/
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DUMP="$SCRIPT_DIR/db/dump.sql.gz"
UPLOAD="$SCRIPT_DIR/db/upload.tar.gz"

if [ ! -f "$DUMP" ]; then
    echo "ERROR: $DUMP not found"
    exit 1
fi

if [ ! -f "$UPLOAD" ]; then
    echo "ERROR: $UPLOAD not found"
    exit 1
fi

echo "Dropping and recreating bitrix database..."
docker compose exec -T db mysql -uroot -proot -e "DROP DATABASE IF EXISTS bitrix; CREATE DATABASE bitrix CHARACTER SET utf8 COLLATE utf8_unicode_ci; GRANT ALL ON bitrix.* TO 'bitrix'@'%';"

echo "Restoring DB dump..."
gunzip -c "$DUMP" | docker compose exec -T db mysql -ubitrix -pbitrix bitrix

echo "Copying upload archive into the container..."
docker compose cp "$UPLOAD" web:/tmp/upload.tar.gz

echo "Extracting upload files..."
docker compose exec -T web bash -c "cd /var/www/html && rm -rf upload && tar -xzf /tmp/upload.tar.gz && chown -R www-data:www-data upload && rm /tmp/upload.tar.gz"

echo "Installing custom main page..."
docker compose exec -T web bash -c "cp /var/www/html/local/public/index.php /var/www/html/index.php && chown www-data:www-data /var/www/html/index.php"

echo ""
echo "Done!"
echo ""
echo "Open http://localhost:8080/ to see the product list."
echo "Admin: http://localhost:8080/bitrix/admin/ (use the credentials you set during install)"
