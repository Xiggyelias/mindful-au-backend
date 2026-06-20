<?php

$frontendUrl = trim((string) env('FRONTEND_URL', ''));
$rawOrigins = trim((string) env('CORS_ALLOWED_ORIGINS', $frontendUrl));
$allowedOrigins = array_values(
    array_filter(
        array_map(
            static fn (string $origin): string => trim($origin),
            explode(',', $rawOrigins !== '' ? $rawOrigins : '*')
        ),
        static fn (string $origin): bool => $origin !== ''
    )
);

$defaultProductionOrigins = array_values(
    array_filter(
        array_map(
            static fn (string $origin): string => trim($origin),
            explode(
                ',',
                (string) env(
                    'CORS_DEFAULT_PRODUCTION_ORIGINS',
                    'https://counseling.africau.edu,https://www.counseling.africau.edu'
                )
            )
        ),
        static fn (string $origin): bool => $origin !== ''
    )
);

if (! in_array('*', $allowedOrigins, true)) {
    $allowedOrigins = array_values(array_unique(array_merge($allowedOrigins, $defaultProductionOrigins)));
}

// Local DX: accept localhost and 127.0.0.1 variants when either is configured.
if (! in_array('*', $allowedOrigins, true)) {
    $expandedOrigins = [];

    foreach ($allowedOrigins as $origin) {
        $expandedOrigins[] = $origin;

        $parts = parse_url($origin);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $port = $parts['port'] ?? null;

        if (! $scheme || ! $host) {
            continue;
        }

        if ($host === 'localhost') {
            $expandedOrigins[] = sprintf('%s://127.0.0.1%s', $scheme, $port ? ':'.$port : '');
        } elseif ($host === '127.0.0.1') {
            $expandedOrigins[] = sprintf('%s://localhost%s', $scheme, $port ? ':'.$port : '');
        }
    }

    $allowedOrigins = array_values(array_unique($expandedOrigins));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    // Token-based auth does not require browser credential cookies.
    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOL),
];
