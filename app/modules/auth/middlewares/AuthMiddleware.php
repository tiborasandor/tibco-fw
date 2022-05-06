<?php
declare(strict_types=1);

namespace app\modules\auth\middlewares;


class AuthMiddleware extends Middleware {

    public function __invoke(Request $request, RequestHandler $handler):Response {
        if (0) {
            // nincs belépve felhasználó
            if ($this->route->getName() != 'login_page') {
                $response = new Response(302);
                return $response->withHeader('Location', $this->routeparser->urlFor('login_page'));
            }
        } else {
            // van belépve felhasználó
            if ($this->route->getName() == 'login_page') {
                $response = new Response(302);
                return $response->withHeader('Location', $this->routeparser->urlFor('main_page'));
            }
        }


        $response = $handler->handle($request);
        return $response;
    }

}

?>