<?php
declare(strict_types=1);

namespace system;

final class View extends \Slim\Views\Twig {
    // flash is added per render: the session is not started yet when the view is created
    private ?\Odan\Session\SessionInterface $session = null;

    public function setSession(\Odan\Session\SessionInterface $session): void {
        $this->session = $session;
    }

    public function render(\Psr\Http\Message\ResponseInterface $response, string $template, array $data = []): \Psr\Http\Message\ResponseInterface {
        $callerClass = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? null;
        $template = $this->resolveTemplateName($template, $callerClass);

        $data['flash'] = $data['flash'] ?? $this->session?->getFlash();

        return parent::render($response, $template, $data);
    }

    public function fetchBlock(string $template, string $block, array $data = []): string {
        $callerClass = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? null;
        $template = $this->resolveTemplateName($template, $callerClass);

        $data['flash'] = $data['flash'] ?? $this->session?->getFlash();

        return parent::fetchBlock($template, $block, $data);
    }

    private function resolveTemplateName(string $template, ?string $callerClass): string {
        $templateArray = explode('/', $template);
        if (count($templateArray) === 1) {
            $callerParts = $callerClass !== null ? explode('\\', $callerClass) : [];
            if (($callerParts[0] ?? null) !== 'app' || ($callerParts[1] ?? null) !== 'modules' || !isset($callerParts[2])) {
                throw new \Error(
                    "Couldn't determine the calling module for template '$template'".
                    " (called from ".($callerClass ?? 'unknown').") - ".
                    "use an explicit 'module/template' or 'resources/template' name instead."
                );
            }
            return '@'.$callerParts[2].'/'.$template;
        } elseif (count($templateArray) === 2) {
            if ($templateArray[0] === 'resources') {
                return $templateArray[1];
            }
            return '@'.$template;
        }

        return $template;
    }
}
?>
