<?php
/**
 * WordPress configuration for Railway deployment
 * 
 * This file is used when WordPress is deployed on Railway.
 * Database credentials are pulled from Railway environment variables.
 */

// ** Railway Database Settings ** //
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'wordpress');
define('DB_USER', getenv('MYSQL_USER') ?: 'wordpress');
define('DB_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'wordpress');
define('DB_HOST', getenv('MYSQL_HOST') ?: 'mysql:3306');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// ** Railway URL Configuration ** //
define('WP_HOME', getenv('RAILWAY_PUBLIC_DOMAIN') 
    ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN')
    : 'http://localhost:8080'
);
define('WP_SITEURL', WP_HOME);

// ** Force SSL for admin ** //
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// ** Authentication Unique Keys and Salts ** //
// Generate your own at: https://api.wordpress.org/secret-key/1.1/salt/
define('AUTH_KEY',         getenv('WP_AUTH_KEY') ?: 'put your unique phrase here');
define('SECURE_AUTH_KEY',  getenv('WP_SECURE_AUTH_KEY') ?: 'put your unique phrase here');
define('LOGGED_IN_KEY',    getenv('WP_LOGGED_IN_KEY') ?: 'put your unique phrase here');
define('NONCE_KEY',        getenv('WP_NONCE_KEY') ?: 'put your unique phrase here');
define('AUTH_SALT',        getenv('WP_AUTH_SALT') ?: 'put your unique phrase here');
define('SECURE_AUTH_SALT', getenv('WP_SECURE_AUTH_SALT') ?: 'put your unique phrase here');
define('LOGGED_IN_SALT',   getenv('WP_LOGGED_IN_SALT') ?: 'put your unique phrase here');
define('NONCE_SALT',       getenv('WP_NONCE_SALT') ?: 'put your unique phrase here');

// ** WordPress Database Table prefix ** //
$table_prefix = 'wp_';

// ** WordPress debugging mode ** //
define('WP_DEBUG', getenv('WP_DEBUG') === 'true');
define('WP_DEBUG_LOG', getenv('WP_DEBUG_LOG') === 'true');
define('WP_DEBUG_DISPLAY', false);

// ** LSE API Configuration ** //
define('LSE_M_API_URL', getenv('LSE_M_API_URL') ?: 'https://m-api-production.up.railway.app');

// ** Absolute path to WordPress directory ** //
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// ** Sets up WordPress vars and included files ** //
require_once ABSPATH . 'wp-settings.php';
