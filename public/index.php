<?php
declare(strict_types=1);

/**
 * PHP version check
 */
if (version_compare(PHP_VERSION, '8.1', '<')) {
    die("Upgrade your PHP version (".PHP_VERSION.") to 8.1 or newer!");
}

/**
 * Init
 */
require_once '../system/init.php';
?>