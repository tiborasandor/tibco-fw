<?php
$app->get('/','PageAction:mainPage')->setName('main_page');
$app->post('/example/flash','PageAction:flashDemo')->setName('example_flash');

// AuthMiddleware demo (app/middlewares/AuthMiddleware.php)
$app->post('/example/login','AuthAction:login')->setName('example_login');
$app->post('/example/logout','AuthAction:logout')->setName('example_logout');
$app->get('/example/secret','AuthAction:secret')->setName('example_secret');
?>
