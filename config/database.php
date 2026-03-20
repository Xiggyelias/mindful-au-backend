<?php

use Illuminate\Support\Str;

$sqliteDatabase = env('DB_DATABASE', database_path('database.sqlite'));

if (
    $sqliteDatabase !== ':memory:'
    && ! preg_match('/^(\/|\\\\|[A-Za-z]:[\\\\\\/])/', (string) $sqliteDatabase)
) {
    // Resolve relative sqlite paths from the project root for consistent behavior.
    $sqliteDatabase = base_path((string) $sqliteDatabase);
}

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL', null),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA', null),
                PDO::MYSQL_ATTR_SSL_CERT => env('MYSQL_ATTR_SSL_CERT', null),
                PDO::MYSQL_ATTR_SSL_KEY => env('MYSQL_ATTR_SSL_KEY', null),
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => filter_var(
                    env('MYSQL_ATTR_SSL_VERIFY_SERVER_CERT', true),
                    FILTER_VALIDATE_BOOL
                ),
            ]) : [],
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL', null),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL', null),
            'database' => $sqliteDatabase,
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
    ],

    'migrations' => 'migrations',
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL', null),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME', null),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'url' => env('REDIS_URL', null),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME', null),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
