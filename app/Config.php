<?php

declare(strict_types=1);

function app_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $localConfig = __DIR__ . '/../config/config.php';
    $config = is_file($localConfig)
        ? require $localConfig
        : require __DIR__ . '/../config/config.example.php';

    $config['db']['host'] = getenv('CANDIDATE_DB_HOST') ?: $config['db']['host'];
    $config['db']['port'] = (int) (getenv('CANDIDATE_DB_PORT') ?: $config['db']['port']);
    $config['db']['name'] = getenv('CANDIDATE_DB_NAME') ?: $config['db']['name'];
    $config['db']['user'] = getenv('CANDIDATE_DB_USER') ?: $config['db']['user'];
    $config['db']['password'] = getenv('CANDIDATE_DB_PASSWORD') ?: $config['db']['password'];
    $config['auth']['legacy_password_mode'] = getenv('CANDIDATE_LEGACY_PASSWORD_MODE') ?: $config['auth']['legacy_password_mode'];

    return $config;
}