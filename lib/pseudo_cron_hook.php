<?php
/**
 * Auto-include file for Pseudo-Cron functionality
 * 
 * This file should be included at the end of htdocs/main.inc.php
 * Add this line: @include_once DOL_DOCUMENT_ROOT.'/custom/contactimport/lib/pseudo_cron_hook.php';
 * 
 * Copyright (C) 2025 Kim Wittkowski <kim@wittkowski-it.de>
 */

// Only run if module is enabled and pseudo-cron is activated
if (empty($conf->contactimport->enabled) || empty($conf->global->CONTACTIMPORT_PSEUDO_CRON)) {
	return;
}

// Include the pseudo-cron library
@include_once __DIR__.'/pseudo_cron.lib.php';
