#!/bin/sh
set -eu

php /var/www/html/scripts/seed.php

exec apache2-foreground

