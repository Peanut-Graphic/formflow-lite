<?php
namespace FFFL\Connectors\DominionPtr;

if (!defined('ABSPATH')) { exit; }

define('FFFL_DOMINION_PTR_PATH', __DIR__);

function load_connector(): void {
    require_once FFFL_DOMINION_PTR_PATH . '/class-dominion-ptr-connector.php';
}

function register_connector($registry): void {
    load_connector();
    $registry->register(new DominionPtrConnector());
}

add_action('fffl_register_connectors', __NAMESPACE__ . '\\register_connector');
