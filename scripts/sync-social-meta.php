<?php

declare(strict_types=1);

use App\MetaSocialSyncService;

require dirname(__DIR__) . '/app/bootstrap.php';

$args = [];
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        continue;
    }
    [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
    $args[$key] = $value;
}

$from = date('Y-m-d', strtotime((string) ($args['from'] ?? 'first day of january last year')));
$to = date('Y-m-d', strtotime((string) ($args['to'] ?? 'today')));
$platform = (string) ($args['platform'] ?? '');

try {
    $summary = (new MetaSocialSyncService())->sync($from, $to, $platform);
    echo json_encode([
        'ok' => true,
        'from' => $from,
        'to' => $to,
        'platform' => $platform === '' ? 'all' : $platform,
        'summary' => $summary,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'from' => $from,
        'to' => $to,
        'platform' => $platform === '' ? 'all' : $platform,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
