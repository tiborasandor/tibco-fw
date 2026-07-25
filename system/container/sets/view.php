<?php
/**
 * Twig view
 */
$container->set('view', function(\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings');

    $loader = new \Twig\Loader\FilesystemLoader($settings['system']['twig']['template_dir']);

    foreach ($settings['modules'] as $module_name => $module_settings) {
        $module_templates_dir = MODULES_DIR.DS.$module_name.DS.'resources'.DS.'templates';
        if ($module_settings['enabled'] && is_dir($module_templates_dir)) {
            $loader->addPath($module_templates_dir, $module_name);
        }
    }

    // Twig extra functions
    // (registered as an object method, not a closure: Twig 3.7.1 misidentifies closures
    // under PHP 8.4+ because Reflection's closure-name format changed - see TwigHelperFunctions.php)
    $twigHelperFunctions = new \system\TwigHelperFunctions($container);

    $functions[] = new \Twig\TwigFunction('is_active_path', [$twigHelperFunctions, 'isActivePath']);

    $cache = $settings['system']['twig']['cache'] ? $settings['system']['twig']['cache_dir'] : false;

    $twig = new \system\View($loader, ['cache' => $cache, 'debug' => (bool) $settings['system']['debug']]);
    $twig->addExtension(new \Twig\Extension\DebugExtension());

    $flash = $container->get('session')->getFlash();
    $twig->getEnvironment()->addGlobal('flash', $flash);
    foreach ($functions as $function) {
        $twig->getEnvironment()->addFunction($function);
    }

    return $twig;
});
?>
