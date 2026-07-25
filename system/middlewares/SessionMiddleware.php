<?php
declare(strict_types=1);

namespace system\middlewares;

class SessionMiddleware extends Middleware {

    public function __invoke(Request $request, RequestHandler $handler):Response {
        $this->session->start();

        if (!$this->session->has('csrfToken')) {
            $this->session->set('csrfToken', bin2hex(random_bytes(32)));
        }

        $this->view['csrfToken'] = $this->session->get('csrfToken');

        $response = $handler->handle($request);
        $this->session->save();
        return $response;
    }

}

?>
