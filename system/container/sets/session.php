<?php
/**
 * Session
 */
$container->set('session', function(\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings')['system']['session'];
    $session = new \Odan\Session\PhpSession((array)$settings);
    return $session;
});
?>
