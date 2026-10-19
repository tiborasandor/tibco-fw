<?php
declare(strict_types=1);

namespace system;

class Action extends Core {

    /** Logs a caught exception with route, user and IP context. */
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