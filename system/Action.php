<?php
declare(strict_types=1);

namespace system;

class Action extends Core {

    /**
     * Logs a caught exception with context (route, user, IP), so errors
     * swallowed in an action's catch block don't disappear without a trace.
     * The user id is read from the request's 'user' attribute (if an auth
     * middleware sets one). Project-wide extra context (e.g. a tenant/company
     * id) can be put into the request's 'logContext' attribute (an array) by a
     * middleware; per-call extra context can be passed in $context.
     */
    protected function logException(\Throwable $th, \Psr\Http\Message\ServerRequestInterface $request, string $level = 'warning', array $context = []): void {
        $loggedUser = $request->getAttribute('user');
        $requestContext = $request->getAttribute('logContext');

        $this->log->log($level, $th->getMessage(), $context + (is_array($requestContext) ? $requestContext : []) + [
            'exception' => get_class($th),
            'route'     => $this->route ? $this->route->getName() : null,
            'userId'    => is_array($loggedUser) ? ($loggedUser['id'] ?? null) : null,
            'ip'        => $this->helper->request->getClientIp($request),
            'at'        => $th->getFile().':'.$th->getLine()
        ]);
    }

}
?>