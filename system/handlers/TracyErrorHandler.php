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
        private readonly LoggerInterface $logger,
        private readonly ?\Slim\Views\Twig $view = null
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

        // only save a Tracy blue screen snapshot for unexpected (5xx) errors
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

        // only for explicit browser navigation (fetch()/XHR send */* and expect JSON)
        if (str_contains($request->getHeaderLine('Accept'), 'text/html')) {
            $message = $exception instanceof HttpException ? $exception->getMessage() : 'Szerver hiba történt.';
            $response->getBody()->write($this->renderHtml($statusCode, $message));
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

    /**
     * HTML error page for browser requests: app/resources/templates/error.twig
     * if the project has one (variables: status, message), otherwise - or if
     * rendering it fails too - a minimal built-in page.
     */
    private function renderHtml(int $statusCode, string $message): string {
        if ($this->view !== null) {
            try {
                $environment = $this->view->getEnvironment();
                if ($environment->getLoader()->exists('error.twig')) {
                    return $environment->render('error.twig', ['status' => $statusCode, 'message' => $message]);
                }
            } catch (Throwable $th) {
                $this->logger->error('error.twig rendering failed: '.$th->getMessage(), ['exception' => $th]);
            }
        }

        $title = htmlspecialchars($statusCode.' '.$message, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html lang="hu"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="robots" content="noindex"><title>'.$title.'</title></head>'
            .'<body style="font-family:system-ui,sans-serif;text-align:center;padding:4rem 1rem">'
            .'<h1>'.$statusCode.'</h1><p>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p>'
            .'<p><a href="/">Főoldal</a></p></body></html>';
    }

    private function wantsHtml(ServerRequestInterface $request): bool {
        $accept = $request->getHeaderLine('Accept');
        return $accept === '' || str_contains($accept, 'text/html') || str_contains($accept, '*/*');
    }

}
?>
