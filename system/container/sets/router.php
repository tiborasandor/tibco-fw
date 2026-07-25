<?php
/**
 * Router
 */
$container->set('routeparser', function(\Psr\Container\ContainerInterface $container) use ($app) {
    return $app->getRouteCollector()->getRouteParser();
});
?>
