<?php
/**
 * Helpers
 *
 * Every *Helper.php class in system/helpers/ (system\helpers namespace) and
 * app/helpers/ (app\helpers namespace) is registered automatically, keyed by
 * its lowercased name without the "Helper" suffix (MailHelper -> $this->helper->mail).
 * App helpers are loaded last, so an app helper with the same name overrides
 * the system one. Every helper receives the container in its constructor
 * (helpers without a constructor simply ignore it).
 */
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
