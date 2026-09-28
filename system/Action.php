<?php
declare(strict_types=1);

namespace system;

class Action extends Core {

    /**
     * Logs a caught exception with context (route, user, IP), so errors
     * swallowed in an action's catch block don't disappear without a trace.
     * The user id is read from the request's 'user' attribute (if an auth
     * middleware sets one); extra context can be passed in $context.
     */
    protected function logException(\Throwable $th, \Psr\Http\Message\ServerRequestInterface $request, string $level = 'warning', array $context = []): void {
        $loggedUser = $request->getAttribute('user');

        $this->log->log($level, $th->getMessage(), $context + [
            'exception' => get_class($th),
            'route'     => $this->route ? $this->route->getName() : null,
            'userId'    => is_array($loggedUser) ? ($loggedUser['id'] ?? null) : null,
            'ip'        => $this->helper->request->getClientIp($request),
            'at'        => $th->getFile().':'.$th->getLine()
        ]);
    }

}
?>