<?php
/** Registers every *Helper.php (system/helpers, then app/helpers) as $this->helper->name. */
$container->set('helper', function(\Psr\Container\ContainerInterface $container) {
    $helpers = new \stdClass;

    $helper_dirs = [
        'system\helpers' => SYSTEM_DIR.DS.'helpers',
        'app\helpers'    => APP_DIR.DS.'helpers',
    ];

    foreach ($helper_dirs as $namespace => $dir) {
        if (!is_dir($dir)) {
            continue;
        }

        foreach (glob($dir.DS.'*Helper.php') as $file) {
            $class_name = pathinfo($file, PATHINFO_FILENAME);
            $key = lcfirst(substr($class_name, 0, -strlen('Helper')));
            $class = $namespace.'\\'.$class_name;

            $helpers->{$key} = new $class($container);
        }
    }

    return $helpers;
});
?>
