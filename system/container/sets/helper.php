<?php
/**
 * Helpers
 */
$container->set('helper', function(\Psr\Container\ContainerInterface $container) {
    $helpers = new \stdClass;

    $key = 'array';
    $helpers->{$key} = new \system\helpers\ArrayHelper;

    $key = 'request';
    $helpers->{$key} = new \system\helpers\RequestHelper;

    return $helpers;
});
?>
