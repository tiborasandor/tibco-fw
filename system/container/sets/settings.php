<?php
/**
 * Load settings to container
 */
$container->set('settings', function (\Psr\Container\ContainerInterface $container) {
    $app_settings = APP_DIR.DS.'settings.php';
    if (!file_exists($app_settings)) {
        return [];
    } else {
        return require_once $app_settings;
    }
});
?>
