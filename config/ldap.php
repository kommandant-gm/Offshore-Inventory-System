<?php

return [
    'enabled' => env('LDAP_ENABLED', false),
    'connection' => env('LDAP_CONNECTION', 'default'),
    'host' => env('LDAP_HOST', ''),
    'username' => env('LDAP_USERNAME', ''),
    'password' => env('LDAP_PASSWORD', ''),
    'port' => env('LDAP_PORT', 389),
    'base_dn' => env('LDAP_BASE_DN', ''),
    'timeout' => env('LDAP_TIMEOUT', 5),
    'ssl' => env('LDAP_SSL', false),
    'tls' => env('LDAP_TLS', false),
    'logging' => env('LDAP_LOGGING', true),
    'cache' => env('LDAP_CACHE', false),
    'log_channel' => env('LDAP_LOG_CHANNEL', config('logging.default')),
];
