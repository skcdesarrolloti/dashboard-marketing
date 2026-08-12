<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$from = '2026-07-01';
$to = '2026-08-06';
$platform = 'youtube';
$error = null;
$success = null;
$syncWarnings = [];
$requestParams = [];
$platformOptions = ['youtube'];
$video = [
    'video_id' => 'abc123',
    'channel_id' => 'UC_TEST',
    'title' => 'Video de prueba',
    'description' => 'Descripción',
    'published_at' => '2026-08-01 14:00:00',
    'thumbnail_url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg',
    'permalink' => 'https://www.youtube.com/watch?v=abc123',
    'duration_seconds' => 95,
    'content_type' => 'video',
    'public_views' => 150,
    'public_likes' => 12,
    'public_comments' => 2,
    'views' => 100,
    'engaged_views' => 74,
    'watch_minutes' => 190,
    'average_view_duration' => 62,
    'average_view_percentage' => 65.2,
    'likes' => 10,
    'comments' => 2,
    'shares' => 3,
    'subscribers_gained' => 4,
    'subscribers_lost' => 1,
    'analytics_from' => $from,
    'analytics_to' => $to,
];
$summary = [
    'views' => 100,
    'engaged_views' => 74,
    'watch_minutes' => 190,
    'average_view_duration' => 62,
    'average_view_percentage' => 65.2,
    'likes' => 10,
    'comments' => 2,
    'shares' => 3,
    'subscribers_gained' => 4,
    'subscribers_lost' => 1,
    'subscribers_net' => 3,
    'videos_published' => 1,
    'interactions' => 15,
];
$period = [
    'current' => ['from' => $from, 'to' => $to],
    'previous' => ['from' => '2026-05-25', 'to' => '2026-06-30'],
    'requested_days' => 37,
    'covered_days' => 2,
    'comparison_ready' => true,
];
$data = [
    'summary' => [],
    'previous' => [],
    'platforms' => [],
    'series' => [],
    'topPosts' => [],
    'posts' => [],
    'accounts' => [],
    'ads' => [],
    'audience' => [],
    'period' => $period,
    'hasLiveData' => true,
    'youtube' => [
        'configured' => true,
        'connection' => [
            'connected' => 1,
            'channel_title' => 'SK&C SuCasa Inmobiliaria',
            'channel_handle' => '@sucasainmobiliaria',
            'subscriber_count' => 250,
            'view_count' => 12000,
            'video_count' => 80,
            'last_sync' => '2026-08-06 10:00:00',
        ],
        'summary' => $summary,
        'previous' => array_map(static fn (mixed $value): mixed => is_numeric($value) ? ((float) $value / 2) : $value, $summary),
        'series' => [
            array_merge($summary, ['metric_date' => '2026-08-05', 'views' => 40, 'watch_minutes' => 80]),
            array_merge($summary, ['metric_date' => '2026-08-06', 'views' => 60, 'watch_minutes' => 110]),
        ],
        'videos' => [$video],
        'topVideos' => [$video],
        'breakdowns' => [
            'creator_content_type' => [['label' => 'VIDEO_ON_DEMAND', 'value' => 100, 'watch_minutes' => 190]],
            'traffic_source' => [['label' => 'YT_SEARCH', 'value' => 60, 'watch_minutes' => 100]],
            'search_term' => [['label' => 'inmobiliaria cartagena', 'value' => 25, 'watch_minutes' => 50]],
            'country' => [['label' => 'CO', 'value' => 90, 'watch_minutes' => 170]],
            'device' => [['label' => 'MOBILE', 'value' => 80, 'watch_minutes' => 150]],
            'subscribed_status' => [['label' => 'SUBSCRIBED', 'value' => 35, 'watch_minutes' => 90]],
        ],
        'period' => $period,
        'hasLiveData' => true,
    ],
];

ob_start();
require dirname(__DIR__) . '/views/social-stats.php';
$html = (string) ob_get_clean();

$checks = [
    'pestaña YouTube' => 'data-social-platform-tab="youtube"',
    'encabezado YouTube' => 'Estadísticas de YouTube',
    'canal conectado' => 'SK&amp;C SuCasa Inmobiliaria',
    'indicadores' => 'Vistas con interés',
    'gráfica' => 'Evolución del canal',
    'descubrimiento' => 'Descubrimiento y audiencia',
    'acordeón de videos' => 'Videos publicados',
    'modal de detalle' => 'data-social-detail-modal',
];

$failures = 0;
foreach ($checks as $label => $needle) {
    if (str_contains($html, $needle)) {
        echo "OK  {$label}\n";
    } else {
        $failures++;
        echo "FAIL {$label}\n";
    }
}

exit($failures === 0 ? 0 : 1);
