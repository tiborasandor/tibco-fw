<?php
declare(strict_types=1);

namespace app\modules\example\actions;

/**
 * Demo login/logout for app/middlewares/AuthMiddleware.php - no password,
 * it just stores a fixed demo user in the session. A real login action would
 * validate the credentials (e.g. password_verify() against a user table)
 * before doing the same.
 */
final class AuthAction extends Action {

    public function login(Request $request, Response $response, $args): Response {
        // new session id on login (session fixation protection)
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
     * Protected page: listed in settings.php middlewares.AuthMiddleware.protected_routes,
     * so AuthMiddleware only lets logged-in users through.
     */
    public function secret(Request $request, Response $response, $args): Response {
        return $this->view->render($response, 'secret.twig', [
            'title' => 'Védett oldal - Tibco',
            'user'  => $request->getAttribute('user'),
        ]);
    }

}
?>
