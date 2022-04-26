<?php
declare(strict_types=1);

namespace app\modules\example\actions;

final class MainAction extends Action {

    public function welcome(Request $request, Response $response, $args) {
        $name = $this->repository('MainRepository')->getName();
        $this->log->info('welcome');
        return $this->view->render($response, 'welcome.twig', [
            'name' => $name
        ]);
    }

}
?>