<?php

declare(strict_types=1);

use App\InventoryService;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por CLI.\n");
}

$summary = (new InventoryService())->syncFromPph();
echo json_encode(
    ['ok' => true, 'synced_at' => date(DATE_ATOM)] + $summary,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
