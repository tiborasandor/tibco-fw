<?php
declare(strict_types=1);

namespace app\middlewares;

class RequestLogMiddleware extends Middleware {

    public function __invoke(Request $request, RequestHandler $handler):Response {
        $start = microtime(true);
        $response = $handler->handle($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $statusCode = $response->getStatusCode();
        $level = 'info';
        if ($statusCode >= 500) {
            $level = 'error';
        } elseif ($statusCode >= 400) {
            $level = 'warning';
        }

        $this->log->log($level, $request->getMethod().' '.$request->getUri()->getPath(), [
            'status' => $statusCode,
            'ip'     => $this->helper->request->getClientIp($request),
            'ms'     => $durationMs
        ]);

        return $response;
    }

}
?>
