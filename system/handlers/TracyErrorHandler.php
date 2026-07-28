<?php
declare(strict_types=1);

namespace system\handlers;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;

final class TracyErrorHandler implements ErrorHandlerInterface {

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $statusCode = $exception instanceof HttpException ? $exception->getCode() : 500;
        $isServerError = $statusCode >= 500;

        if ($logErrors) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }

        // csak a nem várt (5xx) hibákról mentünk el egy Tracy blue screen pillanatképet
        if ($isServerError) {
            Debugger::log($exception, ILogger::EXCEPTION);
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $wantsHtml = $this->wantsHtml($request);

        if ($displayErrorDetails && $isServerError && $wantsHtml) {
            ob_start();
            Debugger::getBlueScreen()->render($exception);
            $response->getBody()->write((string) ob_get_clean());
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        if ($exception instanceof HttpException) {
            $payload = ['status' => 'error', 'message' => $exception->getMessage()];
        } elseif ($displayErrorDetails) {
            $payload = [
                'status' => 'error',
                'message' => $exception->getMessage(),
                'exception' => get_class($exception)
            ];
        } else {
            $payload = ['status' => 'error', 'message' => 'Szerver hiba történt.'];
        }

        $response->getBody()->write((string) json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function wantsHtml(ServerRequestInterface $request): bool {
        $accept = $request->getHeaderLine('Accept');
        return $accept === '' || str_contains($accept, 'text/html') || str_contains($accept, '*/*');
    }

}
?>
