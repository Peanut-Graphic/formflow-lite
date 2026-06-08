<?php
/**
 * Standalone bootstrap for the Contract test suite (net 7).
 *
 * Pins the PowerPortal-API <-> plugin XML response contract: the upstream
 * enrollment API returns XML, the plugin parses it (FFFL\Api\XmlParser) and
 * validates the resulting shape (FFFL\Api\ResponseValidator). Both are pure PHP
 * (xml_* extension + native arrays) — no WordPress is booted here.
 *
 * NOTE: the /wp-json/fffl/v1/* HTTP route contract is a *separate* seam that
 * needs a booting WP REST harness (wp-phpunit / WP_TESTS_DIR) which is not
 * available in this environment — see tests/Contract/WpRestContractTest.php
 * (skipped, with reason) and the known-gaps note in the PR.
 *
 * @package FormFlow_Lite\Tests\Contract
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/fffl-contract/');
}

require_once dirname(__DIR__, 2) . '/includes/api/class-xml-parser.php';
require_once dirname(__DIR__, 2) . '/includes/api/class-response-validator.php';
