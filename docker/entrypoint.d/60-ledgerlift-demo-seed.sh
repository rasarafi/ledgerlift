#!/bin/sh
# serversideup/php runs every /etc/entrypoint.d/*.sh before it starts nginx and PHP-FPM.
# On the first start this fills the new database (and adds the demo company to legacy's);
# afterwards it finds a user in the new database and does nothing.
php "${APP_BASE_DIR:-/var/www/html}/artisan" ledgerlift:demo-seed --no-interaction
