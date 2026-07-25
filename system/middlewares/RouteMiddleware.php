<?php
declare(strict_types=1);

namespace system\middlewares;

class RouteMiddleware extends Middleware {

    public function __invoke(Request $request, RequestHandler $handler):Response {
        $this->route = function(\Psr\Container\ContainerInterface $container) use ($request) {
            $routeContext = \Slim\Routing\RouteContext::fromRequest($request);
            $route = $routeContext->getRoute();
            return $route;
        };
        return $handler->handle($request);
    }

}

?>
