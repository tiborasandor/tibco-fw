<?php

$container->set('view', function(\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings');

    $loader = new \Twig\Loader\FilesystemLoader($settings['system']['twig']['template_dir']);

    foreach ($settings['modules'] as $module_name => $module_settings) {
        $module_templates_dir = MODULES_DIR.DS.$module_name.DS.'resources'.DS.'templates';
        if ($module_settings['enabled'] && is_dir($module_templates_dir)) {
            $loader->addPath($module_templates_dir, $module_name);
        }
    }

    // object method, not a closure: Twig 3.7.1 misreads closures on PHP 8.4+
    
    
    $twigHelperFunctions = new \system\TwigHelperFunctions($container);

    $functions[] = new \Twig\TwigFunction('is_active_path', [$twigHelperFunctions, 'isActivePath']);

    
    $filters[] = new \Twig\TwigFilter('hu_date', [$twigHelperFunctions, 'hungarianDate']);
    $filters[] = new \Twig\TwigFilter('relative_date', [$twigHelperFunctions, 'relativeDate']);

    $cache = $settings['system']['twig']['cache'] ? $settings['system']['twig']['cache_dir'] : false;

    $twig = new \system\View($loader, ['cache' => $cache, 'debug' => (bool) $settings['system']['debug']]);
    $twig->addExtension(new \Twig\Extension\DebugExtension());

    // flash is set per render, see View::setSession()
    $twig->setSession($container->get('session'));
    foreach ($functions as $function) {
        $twig->getEnvironment()->addFunction($function);
    }
    foreach ($filters as $filter) {
        $twig->getEnvironment()->addFilter($filter);
    }

    // optional app/TwigExtension.php
    
    if (class_exists('app\TwigExtension')) {
        $twig->addExtension(new \app\TwigExtension($container));
    }

    return $twig;
});
?>
