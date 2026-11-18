<?php
declare(strict_types=1);

/**
 * Copy to server/config.php and fill with values from the hosting control panel.
 * Never commit server/config.php or put credentials in frontend code.
 */
return [
    'app_origin' => 'https://www.fixxarestudios.com.br',
    'session_name' => 'fixxar_admin',
    'session_secure' => true,
    'security_key' => 'REPLACE_WITH_AT_LEAST_64_RANDOM_HEX_CHARACTERS',
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'REPLACE_WITH_DATABASE_NAME',
        'user' => 'REPLACE_WITH_DATABASE_USER',
        'password' => 'REPLACE_WITH_STRONG_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
