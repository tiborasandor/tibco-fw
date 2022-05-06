<?php
declare(strict_types=1);

namespace app\modules\example\actions;

final class PageAction extends Action {

    public function mainPage(Request $request, Response $response, $args): Response {
        $name = $this->repository('MainRepository')->getName();
        $this->log->info('app\modules\example\actions:mainPage');
        return $this->view->render($response, 'main_page.twig', [
            'name' => $name
        ]);
    }

}
?>