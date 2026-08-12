<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$from = '2025-01-01';
$to = '2026-08-06';
$platform = 'tiktok';
$error = null;
$success = null;
$syncWarnings = [];
$requestParams = [];
$platformOptions = ['tiktok'];
$period = ['current' => ['from' => $from, 'to' => $to], 'previous' => ['from' => '2023-05-27', 'to' => '2024-12-31'], 'comparison_ready' => true, 'requested_days' => 583, 'covered_days' => 1];
$video = [
    'video_id' => 'tt-test', 'title' => 'Propiedad en Cartagena', 'description' => 'Video de prueba',
    'published_at' => '2026-08-04 12:00:00', 'cover_url' => 'https://example.com/cover.jpg',
    'permalink' => 'https://www.tiktok.com/@skc/video/123', 'duration_seconds' => 35,
    'views' => 1500, 'likes' => 120, 'comments' => 8, 'shares' => 15,
];
$summary = ['videos' => 1, 'views' => 1500, 'likes' => 120, 'comments' => 8, 'shares' => 15, 'interactions' => 143];
$data = [
    'summary' => [], 'previous' => [], 'platforms' => [], 'series' => [], 'topPosts' => [], 'posts' => [],
    'accounts' => [], 'ads' => [], 'audience' => [], 'period' => $period, 'hasLiveData' => true, 'youtube' => [],
    'tiktok' => [
        'configured' => true,
        'connection' => ['connected' => 1, 'display_name' => 'SK&C SuCasa', 'username' => 'sucasa', 'follower_count' => 1000, 'likes_count' => 5000, 'video_count' => 80, 'last_sync' => '2026-08-06 12:00:00'],
        'summary' => $summary, 'previous' => array_map(static fn ($value) => (int) ($value / 2), $summary),
        'series' => [['metric_date' => '2026-08-04', 'videos_published' => 1] + $summary],
        'videos' => [$video], 'topVideos' => [$video], 'period' => $period, 'hasLiveData' => true,
    ],
];

ob_start();
require dirname(__DIR__) . '/views/social-stats.php';
$html = (string) ob_get_clean();

$checks = [
    'pestaña TikTok' => 'data-social-platform-tab="tiktok"',
    'cuenta conectada' => 'SK&amp;C SuCasa',
    'sincronización' => 'Sincronizar TikTok',
    'gráfica' => 'Evolución de publicaciones',
    'video' => 'Propiedad en Cartagena',
    'detalle' => 'Ver en TikTok',
];
$failures = 0;
foreach ($checks as $label => $needle) {
    if (str_contains($html, $needle)) echo "OK  {$label}\n";
    else { $failures++; echo "FAIL {$label}\n"; }
}
exit($failures === 0 ? 0 : 1);
