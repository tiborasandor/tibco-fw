<?php
declare(strict_types=1);

namespace app\modules\example\actions;

final class PageAction extends Action {

    private const FLASH_TYPES = ['success', 'info', 'warning', 'error'];

    public function mainPage(Request $request, Response $response, $args): Response {
        $name = $this->repository('MainRepository')->getName();
        $this->log->info('app\modules\example\actions:mainPage');

        return $this->view->render($response, 'main_page.twig', [
            'title'    => 'Kezdőlap - Tibco',
            'name'     => $name,
            'greeting' => $this->helper->example->greeting($name),
            'dates'    => [
                'most'             => new \DateTime(),
                '3 órája'          => new \DateTime('-3 hours'),
                'tegnap'           => new \DateTime('-1 day -2 hours'),
                '4 napja'          => new \DateTime('-4 days'),
                'egy hónapja'      => new \DateTime('-1 month'),
            ],
            'flashTypes' => self::FLASH_TYPES,
        ]);
    }

    /**
     * Flash + CSRF demo: ide küld az űrlap (a CsrfMiddleware ellenőrzi a
     * csrfToken mezőt), az üzenet flash-ként tárolódik, és az átirányítás
     * után az app.twig jeleníti meg.
     */
    public function flashDemo(Request $request, Response $response, $args): Response {
        $type = (string) $request->getParam('type', 'info');
        $message = trim((string) $request->getParam('message', ''));

        if (!in_array($type, self::FLASH_TYPES, true)) {
            $type = 'info';
        }

        $this->session->getFlash()->add($type, $message !== '' ? $message : 'Ez egy flash üzenet.');

        return $response
            ->withHeader('Location', $this->routeparser->urlFor('main_page'))
            ->withStatus(302);
    }

}
?>
