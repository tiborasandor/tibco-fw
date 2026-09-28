<?php
declare(strict_types=1);

namespace system\middlewares;

class CsrfMiddleware extends Middleware {

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __invoke(Request $request, RequestHandler $handler):Response {
        global $app;

        if (!in_array($request->getMethod(), self::SAFE_METHODS)) {
            // routes listed by name in settings (system.csrf.exempt_routes) skip the check,
            // e.g. webhooks or API endpoints called from outside the site
            $routeName = $this->route?->getName();
            $exemptRoutes = $this->settings['system']['csrf']['exempt_routes'] ?? [];
            $isExempt = $routeName !== null && in_array($routeName, $exemptRoutes, true);

            if (!$isExempt) {
                $sessionToken = (string) $this->session->get('csrfToken');
                $requestToken = $request->getHeaderLine('X-CSRF-Token');

                if (empty($requestToken)) {
                    $requestToken = (string) $request->getParam('csrfToken', '');
                }

                if (empty($sessionToken) || empty($requestToken) || !hash_equals($sessionToken, $requestToken)) {
                    $this->log->warning('invalid CSRF token', [
                        'route'  => $routeName,
                        'method' => $request->getMethod(),
                        'ip'     => $this->helper->request->getClientIp($request)
                    ]);

                    // NOTE: $app->getResponseFactory() resolves to the PSR-17 factory
                    // auto-detected from the installed PSR-7 implementation (guzzlehttp/psr7
                    // here), which does NOT have withJson() - that's only on the slim/http
                    // decorator classes, which nothing wraps this response in.
                    $response = $app->getResponseFactory()->createResponse(403);
                    $response->getBody()->write((string) json_encode([
                        'status' => 'error',
                        'message' => 'Érvénytelen vagy lejárt CSRF token, töltsd újra az oldalt'
                    ]));
                    return $response->withHeader('Content-Type', 'application/json');
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
        }

        return $handler->handle($request);
    }

}
?>
