<?php
namespace FFFL\Connectors\PowerportalJson;

if (!defined('ABSPATH')) { exit; }

define('FFFL_POWERPORTAL_JSON_PATH', __DIR__);

function load_connector(): void {
    require_once FFFL_POWERPORTAL_JSON_PATH . '/class-powerportal-json-connector.php';
}

function register_connector($registry): void {
    load_connector();
    $registry->register(new PowerportalJsonConnector());
}

add_action('fffl_register_connectors', __NAMESPACE__ . '\\register_connector');
