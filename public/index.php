<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new App\Router();
$router->dispatch();
