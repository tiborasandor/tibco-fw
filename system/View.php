<?php
declare(strict_types=1);

namespace system;

final class View extends \Slim\Views\Twig {
    /**
     * Flash messages can't be a Twig global (addGlobal('flash', ...)):
     * TwigMiddleware::createFromContainer() fetches the 'view' container entry
     * during bootstrap (init.php), before SessionMiddleware starts the session
     * for the request. A global set there would freeze the Flash object of a
     * not-yet-started (empty) session, disconnected from the one the actions
     * actually write to. So the flash data is added to $data freshly on every
     * render()/fetchBlock() call instead.
     */
    private ?\Odan\Session\SessionInterface $session = null;

    public function setSession(\Odan\Session\SessionInterface $session): void {
        $this->session = $session;
    }

    public function render(\Psr\Http\Message\ResponseInterface $response, string $template, array $data = []): \Psr\Http\Message\ResponseInterface {
        $templateArray = explode('/', $template);
        if (count($templateArray) === 1) {
            $template = '@'.explode('\\', debug_backtrace()[1]['class'])[2].'/'.$template;
        } elseif (count($templateArray) === 2) {
            if ($templateArray[0] === 'resources') {
                $template = $templateArray[1];
            } else {
                $template = '@'.$template;
            }
        }

        $data['flash'] = $data['flash'] ?? $this->session?->getFlash();

        return parent::render($response, $template, $data);
    }

    public function fetchBlock(string $template, string $block, array $data = []): string {
        $templateArray = explode('/', $template);
        if (count($templateArray) === 1) {
            $template = '@'.explode('\\', debug_backtrace()[1]['class'])[2].'/'.$template;
        } elseif (count($templateArray) === 2) {
            if ($templateArray[0] === 'resources') {
                $template = $templateArray[1];
            } else {
                $template = '@'.$template;
            }
        }

        $data['flash'] = $data['flash'] ?? $this->session?->getFlash();

        return parent::fetchBlock($template, $block, $data);
    }
}
?>
