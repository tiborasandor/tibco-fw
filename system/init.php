<?php
declare(strict_types=1);

namespace System;

/**
 * Default defines
 */
define('DS', DIRECTORY_SEPARATOR);
define('SYSTEM_DIR', __DIR__);
define('ROOT_DIR', dirname(SYSTEM_DIR, 1));
define('APP_DIR', ROOT_DIR.DS.'app');
define('CACHE_DIR', ROOT_DIR.DS.'cache');
define('MODULES_DIR', APP_DIR.DS.'modules');
define('RESOURCES_DIR', APP_DIR.DS.'resources');
define('TEMPLATES_DIR', RESOURCES_DIR.DS.'templates');
define('PUBLIC_DIR', ROOT_DIR.DS.'public');
define('VENDOR_DIR', ROOT_DIR.DS.'vendor');

/**
 * Reqiure composer autoload
 */
$composer_autoload = VENDOR_DIR.DS.'autoload.php';
if (!file_exists($composer_autoload)) {
    die('The composer autoload file ('.$composer_autoload.') load failed.');
}
require_once $composer_autoload;

/**
 * Create app
 */
$app = \Slim\Factory\AppFactory::createFromContainer(new \DI\Container());


/**
 * Container sets
 */
$container = $app->getContainer();

/**
 * Router
 */
$container->set('routeparser', function(\Psr\Container\ContainerInterface $container) use ($app) {
    return $app->getRouteCollector()->getRouteParser();
});

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

/**
 * Logger to container
 */
$container->set('log', function (\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings')['system'];
    $logger_settings = $settings['logger'];
    $logger = new \Monolog\Logger($logger_settings['name']);
    $logger->setTimezone(new \DateTimeZone($settings['timezone']));
    $logger->useMicrosecondTimestamps(false);
    $formatter = new \Monolog\Formatter\LineFormatter($logger_settings['format'].PHP_EOL, $logger_settings['time_format']);
    $formatter->ignoreEmptyContextAndExtra(true);
    // separate log files
    $levels = $logger->getLevels();
    foreach ($levels as $level_name => $level_number) {
        $stream_handler = new \Monolog\Handler\StreamHandler($logger_settings['log_dir'].DS.strtolower($level_name).'_log', $level_number);
        $stream_handler->setFormatter($formatter);
        $filter_handler = new \Monolog\Handler\FilterHandler($stream_handler, $level_number, $level_number);
        $logger->pushHandler($filter_handler);
    }
    // all log file
    $stream_handler = new \Monolog\Handler\StreamHandler($logger_settings['log_dir'].DS.'all_log', 100);
    $stream_handler->setFormatter($formatter);
    $logger->pushHandler($stream_handler);
    return $logger;
});

/**
 * Twig view
 */
$container->set('view', function(\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings');

    $template_dirs = [];
    $template_dirs[] = $settings['system']['twig']['template_dir'];

    foreach ($settings['modules'] as $module_name => $module_settings) {
        $module_templates_dir = MODULES_DIR.DS.$module_name.DS.'resources'.DS.'templates';
        if ($module_settings['enabled'] && is_dir($module_templates_dir)) {
            $template_dirs[] = $module_templates_dir;
        }
    }

    $cache = $settings['system']['twig']['cache'] ? $settings['system']['twig']['cache_dir'] : false;

    return \Slim\Views\Twig::create($template_dirs, ['cache' => $cache]);
});

/**
 * Database
 */
$container->set('db', function(\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings')['system']['database'];
    $db = new stdClass;
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

/**
 * Set timezone
 */
date_default_timezone_set($container->get('settings')['system']['timezone']);

/**
 * Set locale
 */
$locale = $container->get('settings')['system']['locale'];
putenv("LC_ALL=$locale");
setlocale(LC_ALL, $locale);

$modules = $container->get('settings')['modules'];
array_multisort(array_column($modules, 'weight'), $modules);

/**
 * Load modules
 */
foreach ($modules as $module => $params) {
    if ($params['enabled'] == true) {
        $module_dir = MODULES_DIR.DS.$module;

        /**
         * Class aliases
         */
        $prefix = "app\\modules\\$module";
        $class_aliases = [
            'Psr\\Http\\Server\\RequestHandlerInterface'    => "$prefix\\middlewares\\RequestHandler",
            'Psr\\Http\\Message\\ServerRequestInterface'    => [
                "$prefix\\middlewares\\Request",
                "$prefix\\actions\\Request"
            ],
            'Psr\\Http\\Message\\ResponseInterface'         => "$prefix\\actions\\Response",
            'GuzzleHttp\\Psr7\\Response'                    => "$prefix\\middlewares\\Response",
            'system\\Middleware'                            => "$prefix\\middlewares\\Middleware",
            'system\\Action'                                => "$prefix\\actions\\Action",
            'system\\Repository'                            => "$prefix\\repositories\\Repository",
            'system\\Factory'                               => "$prefix\\factories\\Factory"
        ];

        foreach ($class_aliases as $original => $aliases) {
            $aliases = is_array($aliases) ? $aliases : [$aliases];
            foreach ($aliases as $alias) {
                if (!class_exists($alias, false)) {
                    class_alias($original,$alias);
                }
            }
        }

        /**
         * Register classes
         */
        foreach (['actions','factories','repositories','middlewares'] as $type) {
            $dir = $module_dir.DS.$type;
            if (is_dir($dir)) {
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $file) {
                    $file_info = pathinfo($dir.DS.$file);
                    if ($file_info['extension'] == 'php') {
                        $class_name = $file_info['filename'];
                        $class = "app\modules\\$module\\$type\\$class_name";
                        $container_name = "@$module\\$type\\$class_name";
                        if ($type === 'middlewares') {
                            $app->add(new $class($container));
                        } else {
                            $container->set($container_name, function (\Psr\Container\ContainerInterface $container) use ($class) {
                                return new $class($container);
                            });
                        }
                    }
                }
            }
        }

        /**
         * Include routes
         */
        $route_file = $module_dir.DS.'routes.php';
        if (file_exists($route_file)) {
            require_once $route_file;

            /**
             * Routes mod
             */
            $routes = $app->getRouteCollector()->getRoutes();
            foreach ($routes as $key => $route) {
                $callable = $route->getCallable();
                $e = explode('\\', $callable);

                if (count($e) === 1) {
                    $callable = ["@$module",'actions',$e[0]];
                } elseif(count($e) === 2) {
                    $callable = [$e[0],'actions',$e[1]];
                }

                if (count($e) === 1 || count($e) === 2) {
                    $callable = implode('\\', $callable);
                    $route->setCallable($callable);
                    $callable_resolver = $route->getCallableResolver();
                    $callable_resolver->resolve($callable);
                }
            }
        }
    }
}

/**
 * Add actual route to container
 */
$app->add(function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler) {
    $this->set('route', function(\Psr\Container\ContainerInterface $container) use ($request) {
        $routeContext = \Slim\Routing\RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route;
    });
    return $handler->handle($request);
});

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true, $container->get('log'));
$app->run();
?>