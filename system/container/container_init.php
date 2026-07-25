<?php
/**
 * Container sets
 */
$container = $app->getContainer();

foreach ([
    'settings',
    'session',
    'router',
    'logger',
    'view',
    'database',
    'helper'
] as $name) {
    require_once SYSTEM_DIR.DS.'container'.DS.'sets'.DS.$name.'.php';
}
?>
