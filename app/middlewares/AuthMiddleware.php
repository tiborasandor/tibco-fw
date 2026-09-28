<?php
declare(strict_types=1);

namespace app\middlewares;

/**
 * Példa app-szintű bejelentkezés-kezelő middleware.
 *
 * - A bejelentkezett user a sessionben van, 'user' kulcs alatt (az a tömb,
 *   amit a login action oda elment - lásd az example modul AuthAction-jét).
 * - Ha van ilyen, a request 'user' attribútumaként (action-ökben:
 *   $request->getAttribute('user'); az Action::logException() is naplózza
 *   az id-ját) és a Twig `user` változójaként is elérhető lesz.
 * - A settings.php-ban név szerint felsorolt route-ok
 *   (middlewares.AuthMiddleware.protected_routes) bejelentkezést igényelnek;
 *   enélkül HttpUnauthorizedException keletkezik, amiből a hibakezelő
 *   401-es oldalt (error.twig) vagy JSON választ csinál.
 *
 * A session-lekérdezést cseréld a sajátodra (adatbázisbeli user,
 * remember-me süti, API token, ...), a route-listát pedig a saját
 * szabályodra (route-név előtag, modulonkénti beállítás, szerepkörök, ...),
 * ahogy a projekt igényli.
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
