<?php
declare(strict_types=1);

namespace app\modules\example\actions;

/**
 * Demo be- és kijelentkezés az app/middlewares/AuthMiddleware.php-hoz - jelszó
 * nélkül, csak elment egy fix demo usert a sessionbe. Egy valódi login action
 * előbb ellenőrizné a belépési adatokat (pl. password_verify() egy user
 * táblával szemben), utána ugyanezt csinálná.
 */
final class AuthAction extends Action {

    public function login(Request $request, Response $response, $args): Response {
        // belépéskor új session id (session fixation elleni védelem)
        $this->session->regenerateId();
        $this->session->set('user', [
            'id'   => 1,
            'name' => 'Demo Felhasználó',
        ]);
        $this->session->getFlash()->add('success', 'Bejelentkeztél demo felhasználóként.');

        return $response
            ->withHeader('Location', $this->routeparser->urlFor('example_secret'))
            ->withStatus(302);
    }

    public function logout(Request $request, Response $response, $args): Response {
        $this->session->delete('user');
        $this->session->regenerateId();
        $this->session->getFlash()->add('info', 'Kijelentkeztél.');

        return $response
            ->withHeader('Location', $this->routeparser->urlFor('main_page'))
            ->withStatus(302);
    }

    /**
     * Védett oldal: szerepel a settings.php middlewares.AuthMiddleware.protected_routes
     * listájában, ezért az AuthMiddleware csak bejelentkezett usert enged át.
     */
    public function secret(Request $request, Response $response, $args): Response {
        return $this->view->render($response, 'secret.twig', [
            'title' => 'Védett oldal - Tibco',
            'user'  => $request->getAttribute('user'),
        ]);
    }

}
?>
