<?php
/**
 * Plugin Name: Luminate Strategy Engine Connector
 * Description: Bridges Project Aurora microservices with WordPress for blueprint management, strategic insights, analytics, and billing.
 * Version: 0.1.0
 * Author: Project Aurora Team
 * License: GPL-2.0-or-later
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Railway Production Service URLs
 * Only M-API is needed - all requests are routed through the Management API.
 * Internal services (O-API, S-API, A-API, C-API) are accessed via M-API proxying.
 */
if (! defined('LSE_M_API_URL')) {
    define('LSE_M_API_URL', getenv('LSE_M_API_URL') ?: 'https://m-api-production.up.railway.app');
}

require_once __DIR__ . '/includes/class-lse-headless-ai-plugin.php';
require_once __DIR__ . '/includes/class-lse-headless-ai-api-client.php';

function lse_headless_ai_bootstrap(): void
{
    $client = new LSE_Headless_AI_Api_Client();
    $plugin = new LSE_Headless_AI_Plugin($client);
    $plugin->init();
}

add_action('plugins_loaded', 'lse_headless_ai_bootstrap');
