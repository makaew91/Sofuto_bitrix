#!/bin/bash
# Download Bitrix installer into the running container
echo "Downloading Bitrix installer..."
docker compose exec web bash -c "curl -fSL https://www.1c-bitrix.ru/download/scripts/bitrixsetup.php -o /var/www/html/bitrixsetup.php && chown www-data:www-data /var/www/html/bitrixsetup.php"
echo ""
echo "Done! Open http://localhost:8080/bitrixsetup.php in your browser."
echo ""
echo "DB settings:"
echo "  Host:     db"
echo "  Database: bitrix"
echo "  User:     bitrix"
echo "  Password: bitrix"
