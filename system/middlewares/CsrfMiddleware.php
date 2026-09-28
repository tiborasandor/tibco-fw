<?php
declare(strict_types=1);

namespace system\middlewares;

class CsrfMiddleware extends Middleware {

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __invoke(Request $request, RequestHandler $handler):Response {
        global $app;

        if (!in_array($request->getMethod(), self::SAFE_METHODS)) {
            $sessionToken = (string) $this->session->get('csrfToken');
            $requestToken = $request->getHeaderLine('X-CSRF-Token');

            if (empty($requestToken)) {
                $requestToken = (string) $request->getParam('csrfToken', '');
            }

            if (empty($sessionToken) || empty($requestToken) || !hash_equals($sessionToken, $requestToken)) {
                $this->log->warning('invalid CSRF token', [
                    'route'  => $this->route ? $this->route->getName() : null,
                    'method' => $request->getMethod(),
                    'ip'     => $this->helper->request->getClientIp($request)
                ]);

                $response = $app->getResponseFactory()->createResponse();
                return $response->withJson([
                    'status' => 'error',
                    'message' => 'Érvénytelen vagy lejárt CSRF token, töltsd újra az oldalt'
                ], 403);
            }

            $parsedBody = $request->getParsedBody();
            if (is_array($parsedBody) && array_key_exists('csrfToken', $parsedBody)) {
                unset($parsedBody['csrfToken']);
                $request = $request->withParsedBody($parsedBody);
            }

            $queryParams = $request->getQueryParams();
            if (array_key_exists('csrfToken', $queryParams)) {
                unset($queryParams['csrfToken']);
                $request = $request->withQueryParams($queryParams);
            }
        }

        return $handler->handle($request);
    }

}
?>
