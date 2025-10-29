#!/bin/bash
# Wrapper script for ContactImport cron job
# Copyright (C) 2025 Kim Wittkowski <kim.wittkowski@gmx.de>

# Change to script directory
cd /usr/share/dolibarr/htdocs/custom/contactimport/scripts

# Run the import using PHP CLI with appropriate parameters
/usr/bin/php -f /usr/share/dolibarr/htdocs/custom/contactimport/scripts/cron_import_cli.php

# Exit with the same code as the PHP script
exit $?
