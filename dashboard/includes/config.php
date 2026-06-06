<?php
define('API_BASE',       rtrim(getenv('API_URL')        ?: 'http://skonadesk-api:21114', '/'));
define('APP_NAME',       'SkonaDesk');
define('APP_SECRET',     getenv('APP_SECRET')           ?: 'change_this_secret');
define('DATA_PATH',      getenv('DATA_PATH')            ?: '/data');
define('APP_VERSION',    '1.0.0');

$_apiPublic = getenv('API_PUBLIC_URL');
$_domain    = getenv('DOMAIN') ?: '';
define('API_PUBLIC_URL', rtrim(
    $_apiPublic ?: ($_domain ? 'https://' . $_domain : ''),
    '/'
));
define('API_USE_SSL', str_starts_with(API_PUBLIC_URL, 'https://'));
