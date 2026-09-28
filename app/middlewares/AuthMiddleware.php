<?php
declare(strict_types=1);

namespace app\middlewares;

/**
 * Example app-level authentication middleware.
 *
 * - The logged-in user is kept in the session under 'user' (whatever array
 *   your login action stores there - see the example module's AuthAction).
 * - If there is one, it is exposed as the request attribute 'user' (actions:
 *   $request->getAttribute('user'); Action::logException() logs its id too)
 *   and as the Twig variable `user`.
 * - Routes listed by name in settings.php
 *   (middlewares.AuthMiddleware.protected_routes) require a logged-in user;
 *   without one an HttpUnauthorizedException is thrown, which the error
 *   handler turns into a 401 page (error.twig) or a JSON response.
 *
 * Replace the session lookup with your own (database user, remember-me
 * cookie, API token, ...) and the route list with your own rule (route name
 * prefix, a per-module setting, roles, ...) as the project needs.
 */
class AuthMiddleware extends Middleware {

    public function __invoke(Request $request, RequestHandler $handler):Response {
        $user = $this->session->get('user');

        if (is_array($user)) {
            $request = $request->withAttribute('user', $user);
            $this->view['user'] = $user;
        } else {
            $user = null;
        }

        $routeName = $this->route?->getName();
        $protectedRoutes = $this->settings['middlewares']['AuthMiddleware']['protected_routes'] ?? [];

        if ($user === null && $routeName !== null && in_array($routeName, $protectedRoutes, true)) {
            throw new \Slim\Exception\HttpUnauthorizedException($request, 'Az oldal megtekintéséhez be kell jelentkezni.');
        }

        return $handler->handle($request);
    }

}
?>
