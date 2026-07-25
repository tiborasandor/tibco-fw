<?php
declare(strict_types=1);

namespace system;

final class View extends \Slim\Views\Twig {
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

        return parent::fetchBlock($template, $block, $data);
    }
}
?>
