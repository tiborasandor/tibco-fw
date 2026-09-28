<?php
$app->get('/','PageAction:mainPage')->setName('main_page');
$app->post('/example/flash','PageAction:flashDemo')->setName('example_flash');
?>
