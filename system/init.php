<?php
declare(strict_types=1);

namespace system;

/**
 * Default defines
 */
define('DS', DIRECTORY_SEPARATOR);
define('SYSTEM_DIR', __DIR__);
define('ROOT_DIR', dirname(SYSTEM_DIR, 1));
define('APP_DIR', ROOT_DIR.DS.'app');
define('CACHE_DIR', ROOT_DIR.DS.'cache');
define('LOG_DIR', ROOT_DIR.DS.'log');
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
 * Load settings
 * (needed already at this point for Tracy, before the container exists;
 * system/container/sets/settings.php reuses this same $settings variable
 * instead of loading the file a second time)
 */
$app_settings_file = APP_DIR.DS.'settings.php';
$settings = file_exists($app_settings_file) ? require $app_settings_file : [];

/**
 * Tracy debugger
 */
$debug = (bool) ($settings['system']['debug'] ?? false);
$tracyLogDir = LOG_DIR.DS.'tracy';
if (!is_dir($tracyLogDir)) {
    mkdir($tracyLogDir, 0775, true);
}
\Tracy\Debugger::enable($debug ? \Tracy\Debugger::DEVELOPMENT : \Tracy\Debugger::PRODUCTION, $tracyLogDir);

/**
 * Create app
 */
$app = \Slim\Factory\AppFactory::createFromContainer(new \DI\Container());
$app->setBasePath('/');

/**
 * Container sets
 */
require_once SYSTEM_DIR.DS.'container'.DS.'container_init.php';

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

/**
 * System class aliases
 */
class_alias('\system\Middleware','\system\middlewares\Middleware');
class_alias('\Psr\Http\Message\ServerRequestInterface','\system\middlewares\Request');
class_alias('\Psr\Http\Server\RequestHandlerInterface','\system\middlewares\RequestHandler');
class_alias('\Slim\Http\Response','\system\middlewares\Response');
class_alias('\system\Middleware','\app\middlewares\Middleware');
class_alias('\Psr\Http\Message\ServerRequestInterface','\app\middlewares\Request');
class_alias('\Psr\Http\Server\RequestHandlerInterface','\app\middlewares\RequestHandler');
class_alias('\Slim\Http\Response','\app\middlewares\Response');
class_alias('\Slim\Routing\RouteCollectorProxy','RouteCollectorProxy');

/**
 * Respect validator custom rules
 */
\Respect\Validation\ContainerRegistry::setContainer(
    \Respect\Validation\ContainerRegistry::createContainer([
        'respect.validation.rule_factory.namespaces' => [
            'system\\validatorcustomrules',
            'Respect\\Validation\\Validators',
        ],
    ])
);

/**
 * System middlewares
 */
$middlewares[] = 'system\middlewares\RouteMiddleware';
$middlewares[] = 'system\middlewares\SessionMiddleware';
$middlewares[] = 'system\middlewares\CsrfMiddleware';

$settingsMiddlewares = $container->get('settings')['middlewares'] ?? [];

$settingsMiddlewares = array_filter($settingsMiddlewares, function($v, $k) {
    return $v['enabled'] ?? false;
}, ARRAY_FILTER_USE_BOTH);

// entries without a weight are sorted to the end, same as for the module middlewares below
$settingsMiddlewareWeights = array_map(function($v) {
    return $v['weight'] ?? PHP_INT_MAX;
}, $settingsMiddlewares);
array_multisort($settingsMiddlewareWeights, $settingsMiddlewares);
$settingsMiddlewares = array_keys($settingsMiddlewares);
$settingsMiddlewares = preg_filter('/^/', 'app\middlewares\\', $settingsMiddlewares);
$middlewares = array_merge($middlewares, $settingsMiddlewares);

$modules = $container->get('settings')['modules'];
$moduleWeights = array_map(function($v) {
    return $v['weight'] ?? PHP_INT_MAX;
}, $modules);
array_multisort($moduleWeights, $modules);

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
            'Slim\\Http\\Response'                          => "$prefix\\middlewares\\Response",
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

                if ($type === 'middlewares') {
                    // súly szerint rendezve (settings.php modules.<modul>.middlewares.<Osztaly>.weight);
                    // akinek nincs megadva súlya, az a scandir szerinti (ábécé-) sorrendjét megtartva a végén marad
                    $moduleMiddlewareSettings = $params['middlewares'] ?? [];
                    $moduleMiddlewareClasses = [];
                    foreach ($files as $file) {
                        $file_info = pathinfo($dir.DS.$file);
                        if ($file_info['extension'] == 'php') {
                            $moduleMiddlewareClasses[] = $file_info['filename'];
                        }
                    }

                    $moduleMiddlewareWeights = array_map(function($class_name) use ($moduleMiddlewareSettings) {
                        return $moduleMiddlewareSettings[$class_name]['weight'] ?? PHP_INT_MAX;
                    }, $moduleMiddlewareClasses);
                    array_multisort($moduleMiddlewareWeights, $moduleMiddlewareClasses);

                    foreach ($moduleMiddlewareClasses as $class_name) {
                        $middlewares[] = "app\modules\\$module\\middlewares\\$class_name";
                    }
                } else {
                    foreach ($files as $file) {
                        $file_info = pathinfo($dir.DS.$file);
                        if ($file_info['extension'] == 'php') {
                            $class_name = $file_info['filename'];
                            $class = "app\modules\\$module\\$type\\$class_name";
                            $container_name = "@$module\\$type\\$class_name";
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

foreach (array_reverse($middlewares) as $middleware) {
    $app->add(new $middleware($container));
}

$app->addRoutingMiddleware();
$app->add(new \Selective\BasePath\BasePathMiddleware($app));
$app->add(\Slim\Views\TwigMiddleware::createFromContainer($app));
$displayErrorDetails = (bool) $container->get('settings')['system']['debug'];
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true, $container->get('log'));
$errorMiddleware->setDefaultErrorHandler(
    new \system\handlers\TracyErrorHandler($app->getResponseFactory(), $container->get('log'), $container->get('view'))
);
$app->run();
?>