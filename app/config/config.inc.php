<?php

define('BASE_SERVER_NAME', 'example.com');

define('DYNAMIC_HOST_NAME', 'www.' . BASE_SERVER_NAME);
define('STATIC_HOST_NAME',  'static.' . BASE_SERVER_NAME);

define('DYNAMIC_URL', 'https://' . rawurlencode(DYNAMIC_HOST_NAME));
define('STATIC_URL',  'https://' . rawurlencode(STATIC_HOST_NAME));

define('HOME_URL', DYNAMIC_URL . '/');
define('IDENT_REQUIRED_URL', HOME_URL);

define('JS_VERSION', '1');
define('CSS_VERSION', '1');
define('IMAGES_VERSION', '1');

