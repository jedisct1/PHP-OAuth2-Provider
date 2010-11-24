<?php

error_reporting(E_ALL);
ignore_user_abort(TRUE);
ini_alter('register_globals', '0');
ini_alter('arg_separator.output', '&amp;');
ini_set('url_rewriter.tags', '');

function_exists('mb_internal_encoding') &&
  mb_internal_encoding('UTF-8');

define('SHARED_LIB_DIR', dirname(__FILE__) . '/../../lib');
define('APP_LIB_DIR', dirname(__FILE__));
define('APP_CONFIG_DIR', dirname(__FILE__) . '/../config');

require_once APP_CONFIG_DIR . '/config.inc.php';
require_once SHARED_LIB_DIR . '/errors.inc.php';
require_once SHARED_LIB_DIR . '/class.route.inc.php';
require_once SHARED_LIB_DIR . '/class.generic_utils.inc.php';
