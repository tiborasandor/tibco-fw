<?php
/**
 * Database
 */
$container->set('db', function(\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings')['system']['database'];
    $db = new \stdClass;
    $capsule = new \Illuminate\Database\Capsule\Manager;

    // Create Illuminate connections
    foreach ($settings as $key => $value) {
        $capsule->addConnection($value,$key);
    }

    // init Illuminate db
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    // Separate connections
    foreach ($settings as $key => $value) {
        $db->{$key} = $capsule->connection($key);
    }

    return $db;
});
?>
