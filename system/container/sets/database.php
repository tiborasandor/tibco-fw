<?php

$container->set('db', function(\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings')['system']['database'];
    $db = new \stdClass;
    $capsule = new \Illuminate\Database\Capsule\Manager;

    
    foreach ($settings as $key => $value) {
        $capsule->addConnection($value,$key);
    }

    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    
    foreach ($settings as $key => $value) {
        $db->{$key} = $capsule->connection($key);
    }

    return $db;
});
?>
