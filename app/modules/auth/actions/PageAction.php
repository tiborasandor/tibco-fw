<?php
declare(strict_types=1);

namespace app\modules\auth\actions;

final class PageAction extends Action {

    public function loginPage(Request $request, Response $response, $args): Response {
        $this->log->info('app\modules\auth\actions:loginPage');
        return $this->view->render($response, 'login_page.twig');
    }

}
?>