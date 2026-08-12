<?php
$summary = $data['summary'] ?? [];
$previous = $data['previous'] ?? [];
$platforms = $data['platforms'] ?? [];
$series = $data['series'] ?? [];
$topPosts = $data['topPosts'] ?? [];
$posts = $data['posts'] ?? [];
$accounts = $data['accounts'] ?? [];
$ads = $data['ads'] ?? ['summary' => [], 'campaigns' => [], 'ads' => [], 'date' => ''];
$audience = $data['audience'] ?? [];
$youtube = $data['youtube'] ?? [];
$tiktok = $data['tiktok'] ?? [];
$period = $data['period'] ?? ['current' => ['from' => $from, 'to' => $to], 'previous' => []];
$hasLiveData = (bool) ($data['hasLiveData'] ?? false);
$platformLabels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube'];
$platformTone = ['instagram' => 'ig', 'facebook' => 'fb', 'youtube' => 'yt'];
$activeTab = in_array($platform, ['instagram', 'facebook', 'ads', 'youtube', 'tiktok', 'linkedin', 'google'], true) ? $platform : 'instagram';
$isInstagramTab = $activeTab === 'instagram';
$isFacebookTab = $activeTab === 'facebook';
$isAdsTab = $activeTab === 'ads';
$isYouTubeTab = $activeTab === 'youtube';
$isTikTokTab = $activeTab === 'tiktok';
$isLinkedInTab = $activeTab === 'linkedin';
$isGoogleTab = $activeTab === 'google';
$isComingSoonTab = in_array($activeTab, ['linkedin', 'google'], true);
$isOrganicTab = in_array($activeTab, ['instagram', 'facebook'], true);
$tabLabels = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook',
    'ads' => 'Meta Ads',
    'youtube' => 'YouTube',
    'tiktok' => 'TikTok',
    'linkedin' => 'LinkedIn',
    'google' => 'Google',
];
$heroCopy = [
    'instagram' => ['eyebrow' => 'Instagram', 'title' => 'Estadísticas de Instagram', 'description' => 'Seguidores, alcance, interacción, audiencia y publicaciones de Instagram.'],
    'facebook' => ['eyebrow' => 'Facebook', 'title' => 'Estadísticas de Facebook', 'description' => 'Seguidores, interacción y publicaciones realizadas en la Página de Facebook.'],
    'ads' => ['eyebrow' => 'Meta Ads', 'title' => 'Campañas y anuncios', 'description' => 'Rendimiento publicitario de campañas y anuncios administrados desde Meta.'],
    'youtube' => ['eyebrow' => 'YouTube', 'title' => 'Estadísticas de YouTube', 'description' => 'Visualizaciones, tiempo de reproducción, audiencia y rendimiento de cada video del canal.'],
    'tiktok' => ['eyebrow' => 'TikTok', 'title' => 'Estadísticas de TikTok', 'description' => 'Perfil, videos, interacción orgánica y, cuando se autorice, rendimiento de TikTok Ads.'],
    'linkedin' => ['eyebrow' => 'LinkedIn', 'title' => 'Estadísticas de LinkedIn', 'description' => 'Rendimiento de la página empresarial, publicaciones, audiencia y campañas profesionales.'],
    'google' => ['eyebrow' => 'Google', 'title' => 'Perfil de Negocio de Google', 'description' => 'Interacciones, búsquedas, llamadas, rutas, reservas y clics del Perfil de Negocio.'],
][$activeTab];
$fmt = static fn (mixed $value): string => number_format((float) $value, is_float($value) ? 2 : 0, ',', '.');
$pct = static fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
$selectedChartDays = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
$chartGranularity = $selectedChartDays > 550 ? 'month' : ($selectedChartDays > 45 ? 'week' : 'day');
$chartGranularityCopy = [
    'day' => ['label' => 'día', 'plural' => 'días', 'description' => 'por día'],
    'week' => ['label' => 'semana', 'plural' => 'semanas', 'description' => 'por semana'],
    'month' => ['label' => 'mes', 'plural' => 'meses', 'description' => 'por mes'],
][$chartGranularity];
$chartMonthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
$chartMonthShort = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
$aggregateChartSeries = static function (array $rows, string $dateKey, array $sumKeys, array $lastKeys, string $granularity) use ($chartMonthNames, $chartMonthShort): array {
    $buckets = [];
    foreach ($rows as $row) {
        $rawDate = substr((string) ($row[$dateKey] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
            continue;
        }
        try {
            $date = new DateTimeImmutable($rawDate);
        } catch (Throwable) {
            continue;
        }
        if ($granularity === 'month') {
            $start = $date->modify('first day of this month');
            $end = $date->modify('last day of this month');
            $key = $start->format('Y-m');
            $label = ($chartMonthNames[(int) $start->format('n')] ?? $start->format('m')) . ' ' . $start->format('Y');
            $short = ($chartMonthShort[(int) $start->format('n')] ?? $start->format('m')) . ' ' . $start->format('y');
        } elseif ($granularity === 'week') {
            $start = $date->modify('monday this week');
            $end = $start->modify('+6 days');
            $key = $start->format('Y-m-d');
            $label = $start->format('d') . ' ' . ($chartMonthShort[(int) $start->format('n')] ?? '') . ' – ' . $end->format('d') . ' ' . ($chartMonthShort[(int) $end->format('n')] ?? '') . ' ' . $end->format('Y');
            $short = $start->format('d') . ' ' . ($chartMonthShort[(int) $start->format('n')] ?? '');
        } else {
            $start = $date;
            $end = $date;
            $key = $date->format('Y-m-d');
            $label = $date->format('d') . ' ' . ($chartMonthShort[(int) $date->format('n')] ?? '') . ' ' . $date->format('Y');
            $short = $date->format('d') . ' ' . ($chartMonthShort[(int) $date->format('n')] ?? '');
        }
        if (!isset($buckets[$key])) {
            $buckets[$key] = $row;
            foreach ($sumKeys as $metric) {
                $buckets[$key][$metric] = 0;
            }
            $buckets[$key][$dateKey] = $start->format('Y-m-d');
            $buckets[$key]['_chart_label'] = $label;
            $buckets[$key]['_chart_short'] = $short;
            $buckets[$key]['_chart_from'] = $start->format('Y-m-d');
            $buckets[$key]['_chart_to'] = $end->format('Y-m-d');
        }
        foreach ($sumKeys as $metric) {
            $buckets[$key][$metric] += (float) ($row[$metric] ?? 0);
        }
        foreach ($lastKeys as $metric) {
            $buckets[$key][$metric] = $row[$metric] ?? 0;
        }
    }
    foreach ($buckets as &$bucket) {
        foreach ($sumKeys as $metric) {
            if (!in_array($metric, ['spend', 'watch_minutes'], true)) {
                $bucket[$metric] = (int) round((float) ($bucket[$metric] ?? 0));
            }
        }
    }
    unset($bucket);
    return array_values($buckets);
};
$comparisonReady = (bool) ($period['comparison_ready'] ?? false);
$delta = static function (float|int $current, float|int $past, bool $ready): array {
    if (!$ready) {
        return ['label' => 'Sin base anterior', 'direction' => 'flat'];
    }
    if ((float) $past === 0.0) {
        return ['label' => 'Sin datos previos', 'direction' => 'flat'];
    }
    $change = (($current - $past) / max(1, abs((float) $past))) * 100;
    return ['label' => number_format(round($change, 1), 1, ',', '.') . '% vs anterior', 'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat')];
};
$exposureKey = $isFacebookTab ? 'impressions' : 'reach';
$exposureLabel = $isFacebookTab ? 'Visualizaciones' : 'Alcance';
$exposureDescription = $isFacebookTab ? 'Veces que el contenido de la página apareció en pantalla.' : 'Cuentas únicas alcanzadas por el contenido.';
$reachIsAvailable = (int) ($summary[$exposureKey] ?? 0) > 0;
$engagementRateValue = $reachIsAvailable ? round(((int) ($summary['engagement'] ?? 0) / max(1, (int) ($summary[$exposureKey] ?? 0))) * 100, 2) : 0;
$previousEngagementRate = (int) ($previous[$exposureKey] ?? 0) > 0 ? round(((int) ($previous['engagement'] ?? 0) / max(1, (int) ($previous[$exposureKey] ?? 0))) * 100, 2) : 0;
$metricCards = [
    ['key' => 'followers', 'label' => 'Seguidores', 'value' => $summary['followers'] ?? 0, 'prev' => $previous['followers'] ?? 0],
    ['key' => $exposureKey, 'label' => $exposureLabel, 'value' => $summary[$exposureKey] ?? 0, 'prev' => $previous[$exposureKey] ?? 0, 'available' => $reachIsAvailable, 'unavailable' => 'Meta no entregó esta métrica para el periodo.'],
    ['key' => 'engagement', 'label' => 'Interacciones', 'value' => $summary['engagement'] ?? 0, 'prev' => $previous['engagement'] ?? 0],
    ['key' => 'engagement_rate', 'label' => $isFacebookTab ? 'Interacción / vistas' : 'Tasa engagement', 'value' => $engagementRateValue, 'prev' => $previousEngagementRate, 'percent' => true, 'available' => $reachIsAvailable, 'unavailable' => 'No se puede calcular sin ' . mb_strtolower($exposureLabel) . '.'],
    ['key' => 'profile_views', 'label' => 'Visitas perfil', 'value' => $summary['profile_views'] ?? 0, 'prev' => $previous['profile_views'] ?? 0],
    ['key' => 'content_count', 'label' => 'Publicaciones', 'value' => $summary['content_count'] ?? 0, 'prev' => $previous['content_count'] ?? 0],
];
$maxPlatformReach = max(1, ...array_values(array_map(static fn ($row): int => (int) ($row[$exposureKey] ?? 0), $platforms ?: [[$exposureKey => 1]])));
$series = $aggregateChartSeries($series, 'date', ['reach', 'impressions', 'engagement'], ['followers'], $chartGranularity);
$trendWidth = max(760, max(1, count($series)) * 72);
$trendHeight = 320;
$plotLeft = 54;
$plotRight = $trendWidth - 34;
$reachTop = 34;
$reachBottom = 142;
$engagementTop = 188;
$engagementBottom = 264;
$maxReach = max(1, ...array_values(array_map(static fn ($row): int => (int) ($row[$exposureKey] ?? 0), $series ?: [[$exposureKey => 1]])));
$maxEngagement = max(1, ...array_values(array_map(static fn ($row): int => (int) ($row['engagement'] ?? 0), $series ?: [['engagement' => 1]])));
$barWidth = count($series) > 0 ? max(10, min(24, (($plotRight - $plotLeft) / max(1, count($series))) * .46)) : 12;
$xStep = count($series) > 1 ? (($plotRight - $plotLeft) / (count($series) - 1)) : 0;
$labelEvery = max(1, (int) ceil(max(1, count($series)) / 10));
$reachPoints = [];
$engagementPoints = [];
foreach (array_values($series) as $index => $point) {
    $point['exposure'] = (int) ($point[$exposureKey] ?? 0);
    $x = count($series) > 1 ? $plotLeft + ($xStep * $index) : ($plotLeft + $plotRight) / 2;
    $reachY = $reachBottom - (((int) ($point['exposure'] ?? 0) / $maxReach) * ($reachBottom - $reachTop));
    $engagementY = $engagementBottom - (((int) ($point['engagement'] ?? 0) / $maxEngagement) * ($engagementBottom - $engagementTop));
    $reachPoints[] = ['x' => round($x, 1), 'y' => round($reachY, 1), 'bar' => round($reachBottom - $reachY, 1), 'point' => $point];
    $engagementPoints[] = ['x' => round($x, 1), 'y' => round($engagementY, 1), 'point' => $point];
}
$engagementPolyline = implode(' ', array_map(static fn ($p): string => $p['x'] . ',' . $p['y'], $engagementPoints));
$adsSummary = $ads['summary'] ?? [];
$adsCampaigns = $ads['campaigns'] ?? [];
$adsRows = $ads['ads'] ?? [];
$adsSeries = $aggregateChartSeries($ads['series'] ?? [], 'metric_date', ['spend', 'impressions', 'reach', 'clicks', 'link_clicks'], [], $chartGranularity);
$money = static fn (mixed $value): string => '$' . number_format((float) $value, 0, ',', '.');
$actionLabels = [
    'lead' => 'Leads',
    'link_click' => 'Clics en enlace',
    'onsite_conversion.messaging_conversation_started_7d' => 'Conversaciones',
    'onsite_conversion.total_messaging_connection' => 'Conexiones por mensaje',
    'onsite_conversion.messaging_first_reply' => 'Primeras respuestas',
    'onsite_conversion.post_save' => 'Guardados',
    'onsite_conversion.post_net_save' => 'Guardados netos',
    'onsite_conversion.post_net_like' => 'Me gusta netos',
    'post_engagement' => 'Interacción post',
    'post_interaction_gross' => 'Interacciones brutas',
    'post_interaction_net' => 'Interacciones netas',
    'post_reaction' => 'Reacciones',
    'comment' => 'Comentarios',
    'like' => 'Me gusta',
    'page_engagement' => 'Interacción página',
    'video_view' => 'Reproducciones',
];
$actionDescriptions = [
    'lead' => 'Clientes potenciales registrados desde el anuncio.',
    'link_click' => 'Clics hacia el enlace, sitio, WhatsApp u otro destino del anuncio.',
    'onsite_conversion.messaging_conversation_started_7d' => 'Conversaciones iniciadas por el anuncio dentro de la ventana de atribución.',
    'onsite_conversion.total_messaging_connection' => 'Total de conexiones o contactos por mensajería atribuidos al anuncio.',
    'onsite_conversion.messaging_first_reply' => 'Primeras respuestas recibidas en conversaciones iniciadas desde el anuncio.',
    'onsite_conversion.post_save' => 'Veces que las personas guardaron la publicación promocionada.',
    'onsite_conversion.post_net_save' => 'Guardados netos de la publicación luego de ajustes de Meta.',
    'onsite_conversion.post_net_like' => 'Me gusta netos luego de ajustes de Meta.',
    'post_engagement' => 'Suma de acciones sobre la publicación, como clics, reacciones, comentarios, guardados o compartidos.',
    'post_interaction_gross' => 'Interacciones totales antes de ajustes o deduplicaciones de Meta.',
    'post_interaction_net' => 'Interacciones netas después de ajustes o deduplicaciones de Meta.',
    'post_reaction' => 'Reacciones registradas en la publicación promocionada.',
    'comment' => 'Comentarios atribuidos al anuncio.',
    'like' => 'Me gusta atribuidos al anuncio.',
    'page_engagement' => 'Interacciones con la página o el contenido originadas por el anuncio.',
    'video_view' => 'Reproducciones de video atribuidas al anuncio.',
];
$adDetail = static function (array $row, string $kind) use ($fmt, $money, $pct, $actionLabels, $actionDescriptions): string {
    $actions = json_decode((string) ($row['actions_json'] ?? '[]'), true);
    $actionRows = [];
    if (is_array($actions)) {
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $type = (string) ($action['action_type'] ?? '');
            $value = (int) ($action['value'] ?? 0);
            if ($type !== '' && $value > 0) {
                $label = $actionLabels[$type] ?? ucwords(str_replace(['onsite_conversion.', '_'], ['', ' '], $type));
                $actionRows[] = [
                    'label' => $label,
                    'value' => $fmt($value),
                    'help' => $actionDescriptions[$type] ?? 'Acción reportada por Meta Ads para este anuncio o campaña.',
                ];
            }
        }
    }
    usort($actionRows, static fn (array $a, array $b): int => (int) str_replace('.', '', $b['value']) <=> (int) str_replace('.', '', $a['value']));
    $payload = [
        'title' => ($kind === 'campaign' ? 'Campaña' : 'Anuncio') . ': ' . (string) ($row['name'] ?: 'Sin nombre'),
        'subtitle' => 'Periodo ' . (string) ($row['period_start'] ?? '') . ' a ' . (string) ($row['metric_date'] ?? '') . (!empty($row['status']) ? ' · ' . (string) $row['status'] : ''),
        'thumbnail' => (string) ($row['thumbnail_url'] ?? ''),
        'metrics' => [
            ['label' => 'Gasto', 'value' => $money($row['spend'] ?? 0), 'help' => 'Dinero invertido en Meta Ads durante el periodo guardado.'],
            ['label' => 'Impresiones', 'value' => $fmt($row['impressions'] ?? 0), 'help' => 'Veces que el anuncio se mostró en pantalla. Una persona puede verlo varias veces.'],
            ['label' => 'Alcance', 'value' => $fmt($row['reach'] ?? 0), 'help' => 'Personas únicas que vieron el anuncio al menos una vez.'],
            ['label' => 'Clics', 'value' => $fmt($row['clicks'] ?? 0), 'help' => 'Todos los clics registrados por Meta en el anuncio.'],
            ['label' => 'Clics enlace', 'value' => $fmt($row['link_clicks'] ?? 0), 'help' => 'Clics que llevan a un enlace, WhatsApp, sitio o destino configurado.'],
            ['label' => 'CTR', 'value' => $pct($row['ctr'] ?? 0), 'help' => 'Porcentaje de impresiones que terminaron en clic.'],
            ['label' => 'CPC', 'value' => $money($row['cpc'] ?? 0), 'help' => 'Costo promedio por clic.'],
            ['label' => 'CPM', 'value' => $money($row['cpm'] ?? 0), 'help' => 'Costo promedio por cada mil impresiones.'],
            ['label' => 'Frecuencia', 'value' => number_format((float) ($row['frequency'] ?? 0), 2, ',', '.'), 'help' => 'Promedio de veces que cada persona alcanzada vio el anuncio.'],
        ],
        'actions' => array_slice($actionRows, 0, 8),
    ];
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
};
$adsKpiCards = [
    ['label' => 'Gasto', 'value' => $money($adsSummary['spend'] ?? 0), 'help' => 'Dinero invertido en Meta Ads durante el periodo guardado.'],
    ['label' => 'Impresiones', 'value' => $fmt($adsSummary['impressions'] ?? 0), 'help' => 'Veces que los anuncios se mostraron. Una persona puede generar varias impresiones.'],
    ['label' => 'Alcance', 'value' => $fmt($adsSummary['reach'] ?? 0), 'help' => 'Personas únicas que vieron los anuncios al menos una vez.'],
    ['label' => 'Clics', 'value' => $fmt($adsSummary['clicks'] ?? 0), 'help' => 'Todos los clics registrados por Meta en los anuncios.'],
    ['label' => 'CTR', 'value' => $pct($adsSummary['ctr'] ?? 0), 'help' => 'Porcentaje de impresiones que terminaron en clic.'],
    ['label' => 'CPC', 'value' => $money($adsSummary['cpc'] ?? 0), 'help' => 'Costo promedio pagado por cada clic.'],
];
$adsTrendWidth = max(760, max(1, count($adsSeries)) * 72);
$adsTrendHeight = 300;
$adsPlotLeft = 54;
$adsPlotRight = $adsTrendWidth - 34;
$adsPlotTop = 34;
$adsPlotBottom = 238;
$adsMaxImpressions = max(1, ...array_values(array_map(static fn (array $row): int => (int) ($row['impressions'] ?? 0), $adsSeries ?: [['impressions' => 1]])));
$adsMaxClicks = max(1, ...array_values(array_map(static fn (array $row): int => (int) ($row['link_clicks'] ?? $row['clicks'] ?? 0), $adsSeries ?: [['clicks' => 1]])));
$adsXStep = count($adsSeries) > 1 ? (($adsPlotRight - $adsPlotLeft) / (count($adsSeries) - 1)) : 0;
$adsBarWidth = count($adsSeries) > 0 ? max(10, min(28, (($adsPlotRight - $adsPlotLeft) / max(1, count($adsSeries))) * .5)) : 12;
$adsLabelEvery = max(1, (int) ceil(max(1, count($adsSeries)) / 10));
$adsTrendPoints = [];
foreach (array_values($adsSeries) as $index => $row) {
    $x = count($adsSeries) > 1 ? $adsPlotLeft + ($adsXStep * $index) : ($adsPlotLeft + $adsPlotRight) / 2;
    $impressionsY = $adsPlotBottom - (((int) ($row['impressions'] ?? 0) / $adsMaxImpressions) * ($adsPlotBottom - $adsPlotTop));
    $clickValue = (int) ($row['link_clicks'] ?? $row['clicks'] ?? 0);
    $clickY = $adsPlotBottom - (($clickValue / $adsMaxClicks) * ($adsPlotBottom - $adsPlotTop));
    $adsTrendPoints[] = ['x' => round($x, 1), 'impressions_y' => round($impressionsY, 1), 'clicks_y' => round($clickY, 1), 'row' => $row];
}
$adsClicksPolyline = implode(' ', array_map(static fn (array $point): string => $point['x'] . ',' . $point['clicks_y'], $adsTrendPoints));
$postDetail = static function (array $post) use ($fmt, $pct, $platformLabels): string {
    $platform = (string) ($post['platform'] ?? '');
    $platformLabel = $platformLabels[$platform] ?? ucfirst($platform ?: 'red social');
    $reach = max(0, (int) ($post['reach'] ?? 0));
    $impressions = max(0, (int) ($post['impressions'] ?? 0));
    $likes = max(0, (int) ($post['likes'] ?? 0));
    $comments = max(0, (int) ($post['comments'] ?? 0));
    $shares = max(0, (int) ($post['shares'] ?? 0));
    $saves = max(0, (int) ($post['saves'] ?? 0));
    $engagement = $likes + $comments + $shares + $saves;
    $reachAvailable = $reach > 0;
    $impressionsAvailable = $impressions > 0;
    $exposure = $platform === 'facebook' ? $impressions : $reach;
    $exposureAvailable = $exposure > 0;
    $exposureName = $platform === 'facebook' ? 'visualizaciones' : 'alcance';
    $rawMetrics = json_decode((string) ($post['metrics_json'] ?? '{}'), true);
    $rawMetrics = is_array($rawMetrics) ? $rawMetrics : [];
    $link = trim((string) ($post['permalink'] ?? ''));
    if ($link !== '' && !preg_match('~^https?://~i', $link)) {
        $link = '';
    }
    $payload = [
        'title' => 'Publicación de ' . $platformLabel,
        'subtitle' => trim((string) ($post['post_date'] ?? '') . (!empty($post['post_time']) ? ' · ' . (string) $post['post_time'] : '') . ' · ' . (string) ($post['content_type'] ?? 'post')),
        'description' => (string) ($post['title'] ?: 'Publicación sin texto disponible.'),
        'thumbnail' => (string) ($post['thumbnail_url'] ?? ''),
        'notice' => !$exposureAvailable ? 'Meta no entregó ' . $exposureName . ' para esta publicación; por eso la tasa de interacción se muestra como N/D.' : ($platform === 'facebook' ? 'Desde Graph API v25 Meta entrega visualizaciones, pero ya no alcance orgánico único por publicación.' : ''),
        'link' => $link,
        'linkLabel' => 'Ver publicación',
        'metrics' => [
            ['label' => 'Interacciones', 'value' => $fmt($engagement), 'help' => 'Suma de me gusta, comentarios, compartidos y guardados disponibles.'],
            ['label' => 'Me gusta', 'value' => $fmt($likes), 'help' => 'Reacciones o me gusta registrados por Meta.'],
            ['label' => 'Comentarios', 'value' => $fmt($comments), 'help' => 'Comentarios registrados en la publicación.'],
            ['label' => 'Compartidos', 'value' => $fmt($shares), 'help' => 'Veces que la publicación fue compartida.'],
            ['label' => 'Guardados', 'value' => $fmt($saves), 'help' => $platform === 'facebook' ? 'Facebook no entrega guardados en esta integración.' : 'Veces que las personas guardaron la publicación.'],
            ['label' => 'Alcance', 'value' => $reachAvailable ? $fmt($reach) : 'N/D', 'help' => $reachAvailable ? 'Cuentas únicas alcanzadas por la publicación.' : 'Dato no entregado por Meta para esta publicación.'],
            ['label' => $platform === 'facebook' ? 'Visualizaciones' : 'Impresiones', 'value' => $impressionsAvailable ? $fmt($impressions) : 'N/D', 'help' => $impressionsAvailable ? 'Veces totales que se mostró la publicación.' : 'Dato no entregado por Meta para esta publicación.'],
            ['label' => 'Clics', 'value' => array_key_exists('post_clicks', $rawMetrics) ? $fmt($rawMetrics['post_clicks']) : 'N/D', 'help' => 'Clics en cualquier parte de la publicación reportados por Meta.'],
            ['label' => 'Reproducciones', 'value' => array_key_exists('post_video_views', $rawMetrics) ? $fmt($rawMetrics['post_video_views']) : (array_key_exists('views', $rawMetrics) ? $fmt($rawMetrics['views']) : 'N/D'), 'help' => 'Visualizaciones o reproducciones reportadas por Meta para contenido visual.'],
            ['label' => 'Tasa de interacción', 'value' => $exposureAvailable ? $pct(($engagement / max(1, $exposure)) * 100) : 'N/D', 'help' => 'Interacciones divididas por ' . $exposureName . ', multiplicadas por 100.'],
        ],
        'actions' => [],
    ];
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
};
$monthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
$postMonths = [];
foreach ($posts as $post) {
    $date = (string) ($post['post_date'] ?? '');
    $monthKey = preg_match('/^\d{4}-\d{2}/', $date) ? substr($date, 0, 7) : 'sin-fecha';
    if (!isset($postMonths[$monthKey])) {
        $monthNumber = (int) substr($monthKey, 5, 2);
        $year = substr($monthKey, 0, 4);
        $postMonths[$monthKey] = [
            'label' => $monthKey === 'sin-fecha' ? 'Sin fecha' : (($monthNames[$monthNumber] ?? 'Mes') . ' ' . $year),
            'posts' => [],
            'engagement' => 0,
        ];
    }
    $postMonths[$monthKey]['posts'][] = $post;
    $postMonths[$monthKey]['engagement'] += (int) ($post['likes'] ?? 0) + (int) ($post['comments'] ?? 0) + (int) ($post['shares'] ?? 0) + (int) ($post['saves'] ?? 0);
}
$periodText = e(($period['current']['from'] ?? $from) . ' a ' . ($period['current']['to'] ?? $to));
$previousText = !empty($period['previous']['from']) ? e($period['previous']['from'] . ' a ' . $period['previous']['to']) : 'Sin periodo anterior';
$coveredDays = (int) ($period['covered_days'] ?? count($series));
$requestedDays = (int) ($period['requested_days'] ?? max(1, count($series)));
$availableFrom = (string) ($period['available_from'] ?? '');
$availableTo = (string) ($period['available_to'] ?? '');
$coverageText = $coveredDays > 0
    ? 'Datos guardados: ' . e($availableFrom . ' a ' . $availableTo) . ' (' . e($coveredDays) . ' de ' . e($requestedDays) . ' días seleccionados).'
    : 'No hay datos guardados para este rango.';
$adsPeriodLabel = !empty($ads['period_start']) && !empty($ads['date']) ? e($ads['period_start'] . ' a ' . $ads['date']) : '';
$adsExact = (bool) ($ads['exact'] ?? false);
$requestParams = is_array($requestParams ?? null) ? $requestParams : $_GET;
$syncOk = isset($requestParams['meta_synced']);
$syncCounts = [
    'accounts' => (int) ($requestParams['meta_accounts'] ?? 0),
    'daily' => (int) ($requestParams['meta_daily'] ?? 0),
    'posts' => (int) ($requestParams['meta_posts'] ?? 0),
    'insights' => (int) ($requestParams['meta_insights'] ?? 0),
    'ads' => (int) ($requestParams['meta_ads'] ?? 0),
    'audience' => (int) ($requestParams['meta_audience'] ?? 0),
    'leads' => (int) ($requestParams['meta_leads'] ?? 0),
    'conversations' => (int) ($requestParams['meta_conversations'] ?? 0),
    'warnings' => (int) ($requestParams['meta_warnings'] ?? 0),
];
$syncTotal = $syncCounts['accounts'] + $syncCounts['daily'] + $syncCounts['posts'] + $syncCounts['insights'] + $syncCounts['ads'] + $syncCounts['audience'] + $syncCounts['leads'] + $syncCounts['conversations'];
$youtubeConnection = $youtube['connection'] ?? [];
$youtubeConfigured = (bool) ($youtube['configured'] ?? false);
$youtubeConnected = (int) ($youtubeConnection['connected'] ?? 0) === 1;
$youtubeChannelMatches = (bool) ($youtube['channel_matches_configuration'] ?? true);
$youtubeConfiguredChannelId = trim((string) ($youtube['configured_channel_id'] ?? ''));
$youtubeConfiguredChannelName = trim((string) ($youtube['configured_channel_name'] ?? ''));
$youtubeExpectedChannel = $youtubeConfiguredChannelName !== '' ? $youtubeConfiguredChannelName : $youtubeConfiguredChannelId;
$youtubeSummary = array_merge([
    'views' => 0, 'engaged_views' => 0, 'watch_minutes' => 0, 'average_view_duration' => 0,
    'average_view_percentage' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0,
    'subscribers_gained' => 0, 'subscribers_lost' => 0, 'subscribers_net' => 0,
    'videos_published' => 0, 'interactions' => 0,
], $youtube['summary'] ?? []);
$youtubePrevious = array_merge($youtubeSummary, $youtube['previous'] ?? []);
$youtubeSeries = $aggregateChartSeries(
    $youtube['series'] ?? [],
    'metric_date',
    ['views', 'engaged_views', 'watch_minutes', 'likes', 'comments', 'shares', 'subscribers_gained', 'subscribers_lost', 'videos_published'],
    [],
    $chartGranularity
);
$youtubeVideos = $youtube['videos'] ?? [];
$youtubeTopVideos = $youtube['topVideos'] ?? [];
$youtubeBreakdowns = $youtube['breakdowns'] ?? [];
$youtubePeriod = $youtube['period'] ?? $period;
$youtubeSyncOk = isset($requestParams['youtube_synced']);
$youtubeSyncCounts = [
    'channels' => (int) ($requestParams['youtube_channels'] ?? 0),
    'daily' => (int) ($requestParams['youtube_daily'] ?? 0),
    'videos' => (int) ($requestParams['youtube_videos'] ?? 0),
    'analytics' => (int) ($requestParams['youtube_analytics'] ?? 0),
    'breakdowns' => (int) ($requestParams['youtube_breakdowns'] ?? 0),
    'warnings' => (int) ($requestParams['youtube_warnings'] ?? 0),
];
$duration = static function (mixed $seconds): string {
    $seconds = max(0, (int) round((float) $seconds));
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;
    return $hours > 0
        ? $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT)
        : $minutes . ':' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT);
};
$watchHours = static fn (mixed $minutes): string => number_format(((float) $minutes) / 60, 1, ',', '.');
$youtubeMetricCards = [
    ['key' => 'views', 'label' => 'Visualizaciones', 'value' => $youtubeSummary['views'], 'prev' => $youtubePrevious['views'], 'format' => $fmt],
    ['key' => 'engaged_views', 'label' => 'Vistas con interés', 'value' => $youtubeSummary['engaged_views'], 'prev' => $youtubePrevious['engaged_views'], 'format' => $fmt],
    ['key' => 'watch_minutes', 'label' => 'Horas vistas', 'value' => $youtubeSummary['watch_minutes'], 'prev' => $youtubePrevious['watch_minutes'], 'format' => $watchHours],
    ['key' => 'average_view_duration', 'label' => 'Duración promedio', 'value' => $youtubeSummary['average_view_duration'], 'prev' => $youtubePrevious['average_view_duration'], 'format' => $duration],
    ['key' => 'average_view_percentage', 'label' => 'Porcentaje visto', 'value' => $youtubeSummary['average_view_percentage'], 'prev' => $youtubePrevious['average_view_percentage'], 'format' => static fn (mixed $value): string => number_format((float) $value, 1, ',', '.') . '%'],
    ['key' => 'subscribers_net', 'label' => 'Suscriptores netos', 'value' => $youtubeSummary['subscribers_net'], 'prev' => $youtubePrevious['subscribers_net'], 'format' => $fmt],
    ['key' => 'interactions', 'label' => 'Interacciones', 'value' => $youtubeSummary['interactions'], 'prev' => $youtubePrevious['interactions'], 'format' => $fmt],
    ['key' => 'videos_published', 'label' => 'Videos publicados', 'value' => $youtubeSummary['videos_published'], 'prev' => $youtubePrevious['videos_published'], 'format' => $fmt],
];

$youtubeTrendWidth = max(760, max(1, count($youtubeSeries)) * 72);
$youtubeTrendHeight = 300;
$youtubePlotLeft = 54;
$youtubePlotRight = $youtubeTrendWidth - 34;
$youtubePlotTop = 34;
$youtubePlotBottom = 238;
$youtubeMaxViews = max(1, ...array_values(array_map(static fn (array $row): int => (int) ($row['views'] ?? 0), $youtubeSeries ?: [['views' => 1]])));
$youtubeMaxWatch = max(1, ...array_values(array_map(static fn (array $row): float => (float) ($row['watch_minutes'] ?? 0), $youtubeSeries ?: [['watch_minutes' => 1]])));
$youtubeXStep = count($youtubeSeries) > 1 ? (($youtubePlotRight - $youtubePlotLeft) / (count($youtubeSeries) - 1)) : 0;
$youtubeBarWidth = count($youtubeSeries) > 0 ? max(10, min(24, (($youtubePlotRight - $youtubePlotLeft) / max(1, count($youtubeSeries))) * .46)) : 12;
$youtubeLabelEvery = max(1, (int) ceil(max(1, count($youtubeSeries)) / 10));
$youtubeTrendPoints = [];
foreach (array_values($youtubeSeries) as $index => $row) {
    $x = count($youtubeSeries) > 1 ? $youtubePlotLeft + ($youtubeXStep * $index) : ($youtubePlotLeft + $youtubePlotRight) / 2;
    $viewsY = $youtubePlotBottom - (((int) ($row['views'] ?? 0) / $youtubeMaxViews) * ($youtubePlotBottom - $youtubePlotTop));
    $watchY = $youtubePlotBottom - (((float) ($row['watch_minutes'] ?? 0) / $youtubeMaxWatch) * ($youtubePlotBottom - $youtubePlotTop));
    $youtubeTrendPoints[] = ['x' => round($x, 1), 'views_y' => round($viewsY, 1), 'watch_y' => round($watchY, 1), 'row' => $row];
}
$youtubeWatchPolyline = implode(' ', array_map(static fn (array $point): string => $point['x'] . ',' . $point['watch_y'], $youtubeTrendPoints));

$youtubeVideoMonths = [];
foreach ($youtubeVideos as $video) {
    $date = substr((string) ($video['published_at'] ?? ''), 0, 10);
    $monthKey = preg_match('/^\d{4}-\d{2}/', $date) ? substr($date, 0, 7) : 'sin-fecha';
    if (!isset($youtubeVideoMonths[$monthKey])) {
        $monthNumber = (int) substr($monthKey, 5, 2);
        $year = substr($monthKey, 0, 4);
        $youtubeVideoMonths[$monthKey] = [
            'label' => $monthKey === 'sin-fecha' ? 'Sin fecha' : (($monthNames[$monthNumber] ?? 'Mes') . ' ' . $year),
            'videos' => [],
            'views' => 0,
        ];
    }
    $youtubeVideoMonths[$monthKey]['videos'][] = $video;
    $youtubeVideoMonths[$monthKey]['views'] += (int) (($video['views'] ?? 0) ?: ($video['public_views'] ?? 0));
}

$youtubeDimensionLabels = [
    'SHORTS' => 'Shorts', 'VIDEO_ON_DEMAND' => 'Videos', 'LIVE_STREAM' => 'En vivo',
    'YT_SEARCH' => 'Búsqueda de YouTube', 'EXT_URL' => 'Sitios externos', 'RELATED_VIDEO' => 'Videos sugeridos',
    'SUBSCRIBER' => 'Suscripciones', 'BROWSE' => 'Inicio y exploración',
    'END_SCREEN' => 'Pantallas finales', 'PLAYLIST' => 'Listas de reproducción', 'NOTIFICATION' => 'Notificaciones',
    'MOBILE' => 'Móvil', 'DESKTOP' => 'Computador', 'TV' => 'Televisor', 'TABLET' => 'Tableta',
    'SUBSCRIBED' => 'Suscritos', 'UNSUBSCRIBED' => 'No suscritos',
];
$youtubeLabel = static fn (string $value): string => $youtubeDimensionLabels[$value] ?? ucwords(strtolower(str_replace('_', ' ', $value)));
$youtubeVideoDetail = static function (array $video) use ($fmt, $watchHours, $duration): string {
    $analyticsViews = (int) ($video['views'] ?? 0);
    $publicViews = (int) ($video['public_views'] ?? 0);
    $gained = (int) ($video['subscribers_gained'] ?? 0);
    $lost = (int) ($video['subscribers_lost'] ?? 0);
    $description = trim((string) ($video['description'] ?? ''));
    $payload = [
        'title' => (string) ($video['title'] ?: 'Video de YouTube'),
        'subtitle' => substr((string) ($video['published_at'] ?? ''), 0, 16) . ' · ' . (($video['content_type'] ?? '') === 'live' ? 'Transmisión' : 'Video'),
        'thumbnail' => (string) ($video['thumbnail_url'] ?? ''),
        'description' => mb_substr($description, 0, 700),
        'notice' => 'Las métricas de rendimiento corresponden al periodo ' . (string) ($video['analytics_from'] ?? '') . ' a ' . (string) ($video['analytics_to'] ?? '') . '. Las visualizaciones públicas son el acumulado actual del video.',
        'metrics' => [
            ['label' => 'Visualizaciones del periodo', 'value' => $fmt($analyticsViews), 'help' => 'Reproducciones atribuidas al rango seleccionado.'],
            ['label' => 'Visualizaciones públicas', 'value' => $fmt($publicViews), 'help' => 'Acumulado visible actualmente en YouTube.'],
            ['label' => 'Vistas con interés', 'value' => $fmt($video['engaged_views'] ?? 0), 'help' => 'Vistas que superaron los segundos iniciales.'],
            ['label' => 'Horas vistas', 'value' => $watchHours($video['watch_minutes'] ?? 0), 'help' => 'Tiempo total de reproducción en el periodo.'],
            ['label' => 'Duración promedio', 'value' => $duration($video['average_view_duration'] ?? 0), 'help' => 'Tiempo promedio reproducido.'],
            ['label' => 'Porcentaje promedio', 'value' => number_format((float) ($video['average_view_percentage'] ?? 0), 1, ',', '.') . '%', 'help' => 'Porcentaje medio del video visualizado.'],
            ['label' => 'Me gusta', 'value' => $fmt(($video['likes'] ?? 0) ?: ($video['public_likes'] ?? 0)), 'help' => 'Me gusta registrados.'],
            ['label' => 'Comentarios', 'value' => $fmt(($video['comments'] ?? 0) ?: ($video['public_comments'] ?? 0)), 'help' => 'Comentarios registrados.'],
            ['label' => 'Compartidos', 'value' => $fmt($video['shares'] ?? 0), 'help' => 'Veces que se compartió durante el periodo.'],
            ['label' => 'Suscriptores netos', 'value' => $fmt($gained - $lost), 'help' => $fmt($gained) . ' ganados y ' . $fmt($lost) . ' perdidos.'],
            ['label' => 'Duración del video', 'value' => $duration($video['duration_seconds'] ?? 0), 'help' => 'Duración total publicada.'],
        ],
        'actions' => [],
        'link' => (string) ($video['permalink'] ?? ''),
        'linkLabel' => 'Ver en YouTube',
    ];
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
};

$tiktokConnection = $tiktok['connection'] ?? [];
$tiktokConfigured = (bool) ($tiktok['configured'] ?? false);
$tiktokConnected = (int) ($tiktokConnection['connected'] ?? 0) === 1;
$tiktokGrantedScopes = array_filter(array_map('trim', explode(',', (string) ($tiktokConnection['scopes'] ?? ''))));
$tiktokHasStatsScope = in_array('user.info.stats', $tiktokGrantedScopes, true);
$tiktokSummary = array_merge(['videos' => 0, 'views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'interactions' => 0], $tiktok['summary'] ?? []);
$tiktokPrevious = array_merge(['videos' => 0, 'views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'interactions' => 0], $tiktok['previous'] ?? []);
$tiktokSeries = $aggregateChartSeries($tiktok['series'] ?? [], 'metric_date', ['videos_published', 'views', 'likes', 'comments', 'shares', 'interactions'], [], $chartGranularity);
$tiktokVideos = $tiktok['videos'] ?? [];
$tiktokTopVideos = $tiktok['topVideos'] ?? [];
$tiktokPeriod = $tiktok['period'] ?? $period;
$tiktokVideoMonths = [];
foreach ($tiktokVideos as $video) {
    $date = substr((string) ($video['published_at'] ?? ''), 0, 10);
    $monthKey = preg_match('/^\d{4}-\d{2}/', $date) ? substr($date, 0, 7) : 'sin-fecha';
    if (!isset($tiktokVideoMonths[$monthKey])) {
        $monthNumber = (int) substr($monthKey, 5, 2);
        $tiktokVideoMonths[$monthKey] = [
            'label' => $monthKey === 'sin-fecha' ? 'Sin fecha' : (($chartMonthNames[$monthNumber] ?? 'Mes') . ' ' . substr($monthKey, 0, 4)),
            'videos' => [], 'views' => 0,
        ];
    }
    $tiktokVideoMonths[$monthKey]['videos'][] = $video;
    $tiktokVideoMonths[$monthKey]['views'] += (int) ($video['views'] ?? 0);
}
$tiktokVideoDetail = static function (array $video) use ($fmt, $duration): string {
    $views = max(0, (int) ($video['views'] ?? 0));
    $interactions = max(0, (int) ($video['likes'] ?? 0)) + max(0, (int) ($video['comments'] ?? 0)) + max(0, (int) ($video['shares'] ?? 0));
    return json_encode([
        'title' => (string) ($video['title'] ?: 'Video de TikTok'),
        'subtitle' => substr((string) ($video['published_at'] ?? ''), 0, 16) . ' · Video',
        'thumbnail' => (string) ($video['cover_url'] ?? ''),
        'description' => mb_substr(trim((string) ($video['description'] ?? '')), 0, 700),
        'notice' => 'TikTok Display API entrega acumulados actuales por video; no son métricas históricas por día.',
        'metrics' => [
            ['label' => 'Visualizaciones', 'value' => $fmt($views)],
            ['label' => 'Me gusta', 'value' => $fmt($video['likes'] ?? 0)],
            ['label' => 'Comentarios', 'value' => $fmt($video['comments'] ?? 0)],
            ['label' => 'Compartidos', 'value' => $fmt($video['shares'] ?? 0)],
            ['label' => 'Interacciones', 'value' => $fmt($interactions)],
            ['label' => 'Engagement / vistas', 'value' => $views > 0 ? number_format(($interactions / $views) * 100, 2, ',', '.') . '%' : 'N/D'],
            ['label' => 'Duración', 'value' => $duration($video['duration_seconds'] ?? 0)],
        ],
        'actions' => [], 'link' => (string) ($video['permalink'] ?? ''), 'linkLabel' => 'Ver en TikTok',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
};
$tiktokTrendWidth = max(760, max(1, count($tiktokSeries)) * 72);
$tiktokPlotLeft = 54; $tiktokPlotRight = $tiktokTrendWidth - 34; $tiktokPlotTop = 34; $tiktokPlotBottom = 238;
$tiktokMaxViews = max(1, ...array_values(array_map(static fn (array $row): int => (int) ($row['views'] ?? 0), $tiktokSeries ?: [['views' => 1]])));
$tiktokMaxInteractions = max(1, ...array_values(array_map(static fn (array $row): int => (int) ($row['interactions'] ?? 0), $tiktokSeries ?: [['interactions' => 1]])));
$tiktokXStep = count($tiktokSeries) > 1 ? (($tiktokPlotRight - $tiktokPlotLeft) / (count($tiktokSeries) - 1)) : 0;
$tiktokBarWidth = count($tiktokSeries) > 0 ? max(10, min(24, (($tiktokPlotRight - $tiktokPlotLeft) / max(1, count($tiktokSeries))) * .46)) : 12;
$tiktokLabelEvery = max(1, (int) ceil(max(1, count($tiktokSeries)) / 10));
$tiktokTrendPoints = [];
foreach (array_values($tiktokSeries) as $index => $row) {
    $x = count($tiktokSeries) > 1 ? $tiktokPlotLeft + ($tiktokXStep * $index) : ($tiktokPlotLeft + $tiktokPlotRight) / 2;
    $tiktokTrendPoints[] = [
        'x' => round($x, 1),
        'views_y' => round($tiktokPlotBottom - (((int) ($row['views'] ?? 0) / $tiktokMaxViews) * ($tiktokPlotBottom - $tiktokPlotTop)), 1),
        'interactions_y' => round($tiktokPlotBottom - (((int) ($row['interactions'] ?? 0) / $tiktokMaxInteractions) * ($tiktokPlotBottom - $tiktokPlotTop)), 1),
        'row' => $row,
    ];
}
$tiktokInteractionsPolyline = implode(' ', array_map(static fn (array $point): string => $point['x'] . ',' . $point['interactions_y'], $tiktokTrendPoints));
?>

<div
  class="social-stats-root"
  data-social-stats-root
  data-social-endpoint="<?= e(url_page('redes')) ?>"
  data-social-token="<?= e(csrf_token()) ?>"
  data-social-clean-url="<?= e(url_page('redes')) ?>"
  data-social-platform="<?= e($activeTab) ?>"
  data-social-from="<?= e($from) ?>"
  data-social-to="<?= e($to) ?>"
  data-social-default-from="<?= e(date('Y-01-01', strtotime('-1 year'))) ?>"
  data-social-default-to="<?= e(date('Y-m-d')) ?>"
  aria-busy="false"
>
<div class="social-fetch-status" data-social-fetch-status role="status" aria-live="polite"></div>

<header class="social-hero">
  <div>
    <span class="eyebrow"><?= e($heroCopy['eyebrow']) ?></span>
    <h1><?= e($heroCopy['title']) ?></h1>
    <p><?= e($heroCopy['description']) ?></p>
  </div>
  <?php
    $heroConnected = $isComingSoonTab ? false : ($isYouTubeTab ? $youtubeConnected : ($isTikTokTab ? $tiktokConnected : $hasLiveData));
    $heroStatusTitle = $isComingSoonTab
      ? 'Integración en desarrollo'
      : ($isYouTubeTab
        ? ($youtubeConnected ? 'Canal conectado' : 'YouTube sin conectar')
        : ($isTikTokTab ? ($tiktokConnected ? 'Cuenta conectada' : 'TikTok sin conectar') : ($hasLiveData ? 'Datos conectados' : 'Sin datos Meta')));
    $heroStatusCopy = $isComingSoonTab
      ? 'La pestaña ya está reservada en el dashboard'
      : ($isYouTubeTab
        ? ($youtubeConnected ? ((string) ($youtubeConnection['channel_title'] ?? 'Sincronización disponible')) : 'Autoriza la cuenta administradora del canal')
        : ($isTikTokTab
          ? ($tiktokConnected ? ((string) ($tiktokConnection['display_name'] ?? 'Sincronización disponible')) : 'Autoriza la cuenta que publica los videos')
          : ($hasLiveData ? 'Sincronización activa' : 'Sincroniza para cargar información real')));
  ?>
  <div class="social-hero-status <?= $heroConnected ? 'live' : 'empty' ?> <?= $isYouTubeTab ? 'youtube' : '' ?> <?= $isComingSoonTab ? 'development' : '' ?>">
    <strong><?= e($heroStatusTitle) ?></strong>
    <span><?= e($heroStatusCopy) ?></span>
  </div>
</header>

<nav class="social-network-tabs" aria-label="Red social">
  <?php foreach ($tabLabels as $tabKey => $tabLabel) : ?>
    <?php $tabIsDevelopment = in_array($tabKey, ['linkedin', 'google'], true); ?>
    <button
      type="button"
      class="social-network-tab <?= e($tabKey) ?> <?= $activeTab === $tabKey ? 'is-active' : '' ?>"
      data-social-platform-tab="<?= e($tabKey) ?>"
      aria-pressed="<?= $activeTab === $tabKey ? 'true' : 'false' ?>"
      <?= $activeTab === $tabKey ? 'aria-current="page"' : '' ?>
    >
      <span class="social-network-dot" aria-hidden="true"></span>
      <?php if ($tabIsDevelopment) : ?>
        <span class="social-network-tab-copy">
          <b><?= e($tabLabel) ?></b>
          <small>
            <svg class="social-gear-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15a1.7 1.7 0 0 0-1.56-1.03H3v-4h.08A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63a1.7 1.7 0 0 0 1.03-1.56V3h4v.08A1.7 1.7 0 0 0 15 4.64a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9a1.7 1.7 0 0 0 1.56 1.03H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z"></path></svg>
            En desarrollo
          </small>
        </span>
      <?php else : ?>
        <?= e($tabLabel) ?>
      <?php endif; ?>
    </button>
  <?php endforeach; ?>
</nav>

<?php if (!$isComingSoonTab) : ?>
<form class="social-filters" action="<?= e(url_page('redes')) ?>" method="get" data-social-filter-form>
  <label>Desde<input type="date" value="<?= e($from) ?>" data-social-from required></label>
  <label>Hasta<input type="date" value="<?= e($to) ?>" data-social-to required></label>
  <button class="primary">Aplicar rango</button>
  <button class="button ghost" type="button" data-social-range="all">Desde el año pasado</button>
  <button class="button ghost" type="button" data-social-range="30">Últimos 30 días</button>
</form>

<div class="social-sync-row <?= $isYouTubeTab ? 'youtube' : '' ?>">
  <?php if ($isYouTubeTab && !$youtubeConnected) : ?>
    <form method="post" class="social-youtube-connect-form">
      <input type="hidden" name="action" value="youtube_connect">
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <button class="button sync youtube" type="submit" <?= !$youtubeConfigured ? 'disabled' : '' ?>>Conectar con YouTube</button>
    </form>
    <span><?= $youtubeConfigured ? 'Inicia sesión con la cuenta que administra el canal. Google solicitará acceso de solo lectura.' : 'Faltan GOOGLE_YOUTUBE_CLIENT_ID o GOOGLE_YOUTUBE_CLIENT_SECRET en el archivo .env.' ?></span>
  <?php elseif ($isTikTokTab && !$tiktokConnected) : ?>
    <form method="post" class="social-youtube-connect-form">
      <input type="hidden" name="action" value="tiktok_connect">
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <button class="button sync tiktok" type="submit" <?= !$tiktokConfigured ? 'disabled' : '' ?>>Conectar con TikTok</button>
    </form>
    <span><?= $tiktokConfigured ? 'Autoriza la cuenta que publica los videos. TikTok solicitará acceso de solo lectura.' : 'Faltan TIKTOK_CLIENT_KEY o TIKTOK_CLIENT_SECRET en el archivo .env.' ?></span>
  <?php else : ?>
    <form class="social-sync-form" method="post" data-social-sync-form data-social-provider="<?= $isYouTubeTab ? 'youtube' : ($isTikTokTab ? 'tiktok' : 'meta') ?>">
      <input type="hidden" name="action" value="sync_social_stats">
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="from" value="<?= e($from) ?>">
      <input type="hidden" name="to" value="<?= e($to) ?>">
      <input type="hidden" name="platform" value="<?= e($activeTab) ?>">
      <button
        class="button sync <?= $isYouTubeTab ? 'youtube' : ($isTikTokTab ? 'tiktok' : '') ?>"
        type="submit"
        data-loading-label="Sincronizando <?= e($tabLabels[$activeTab]) ?>…"
        <?= $isYouTubeTab && !$youtubeChannelMatches ? 'disabled aria-disabled="true" title="Desconecta y autoriza el canal de YouTube configurado."' : '' ?>
      >Sincronizar <?= e($tabLabels[$activeTab]) ?></button>
    </form>
    <?php if ($isYouTubeTab) : ?>
      <span>Actualiza el canal, videos y Analytics del <strong><?= e($from) ?></strong> al <strong><?= e($to) ?></strong>. La información se guarda para consultas rápidas.</span>
      <form method="post" class="social-youtube-disconnect-form" onsubmit="return confirm('¿Desconectar YouTube? Las estadísticas guardadas se conservarán.');">
        <input type="hidden" name="action" value="youtube_disconnect">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <button class="button ghost mini" type="submit">Desconectar</button>
      </form>
    <?php elseif ($isTikTokTab) : ?>
      <span>Actualiza el perfil y todos los videos públicos publicados entre <strong><?= e($from) ?></strong> y <strong><?= e($to) ?></strong>.</span>
      <form method="post" class="social-youtube-disconnect-form" onsubmit="return confirm('¿Desconectar TikTok? Los videos guardados se conservarán.');">
        <input type="hidden" name="action" value="tiktok_disconnect">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <button class="button ghost mini" type="submit">Desconectar</button>
      </form>
    <?php else : ?>
      <span><?= $isAdsTab ? 'Importa únicamente campañas y anuncios de Meta Ads' : 'Importa únicamente los datos y publicaciones de ' . e($tabLabels[$activeTab]) ?> del <?= e($from) ?> al <?= e($to) ?>.</span>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($isYouTubeTab && $youtubeConnected && !$youtubeChannelMatches) : ?>
  <div class="social-notice danger" role="alert">
    <strong>El canal conectado no es el canal de SK&amp;C</strong>
    <span>Ahora está conectado “<?= e($youtubeConnection['channel_title'] ?? 'Otro canal') ?>”. Desconecta YouTube y vuelve a autorizar seleccionando “<?= e($youtubeExpectedChannel !== '' ? $youtubeExpectedChannel : 'SK&C SuCasa Inmobiliaria') ?>”. La sincronización queda bloqueada para no mezclar estadísticas.</span>
  </div>
<?php endif; ?>

<?php if (!empty($error)) : ?>
  <div class="social-notice danger">
    <strong>No se pudo completar <?= $isYouTubeTab ? 'la operación de YouTube' : ($isTikTokTab ? 'la operación de TikTok' : 'la sincronización de Meta') ?></strong>
    <span><?= e($error) ?></span>
  </div>
<?php elseif (!empty($success)) : ?>
  <div class="social-notice success">
    <strong>Operación completada</strong>
    <span><?= e($success) ?></span>
  </div>
<?php elseif ($youtubeSyncOk) : ?>
  <div class="social-notice success">
    <strong>YouTube sincronizado</strong>
    <span><?= e($youtubeSyncCounts['channels']) ?> canal, <?= e($youtubeSyncCounts['daily']) ?> días, <?= e($youtubeSyncCounts['videos']) ?> videos, <?= e($youtubeSyncCounts['analytics']) ?> filas de Analytics y <?= e($youtubeSyncCounts['breakdowns']) ?> segmentos<?= $youtubeSyncCounts['warnings'] > 0 ? ' · ' . e($youtubeSyncCounts['warnings']) . ' observaciones' : '' ?>.</span>
  </div>
<?php elseif ($syncOk) : ?>
  <div class="social-notice success">
    <strong>Meta sincronizado</strong>
    <?php if ($syncTotal === 0 && $syncCounts['warnings'] > 0) : ?>
      <span>Meta no devolvió nuevos datos para el periodo seleccionado. La pantalla conserva el último periodo guardado disponible.</span>
    <?php else : ?>
      <?php if ($isAdsTab) : ?>
        <span><?= e($syncCounts['ads']) ?> registros de campañas y anuncios sincronizados<?= $syncCounts['warnings'] > 0 ? ' · ' . e($syncCounts['warnings']) . ' avisos de Meta' : '' ?>.</span>
      <?php else : ?>
        <span><?= e($syncCounts['accounts']) ?> cuenta, <?= e($syncCounts['daily']) ?> días, <?= e($syncCounts['posts']) ?> publicaciones y <?= e($syncCounts['insights']) ?> Insights de <?= e($tabLabels[$activeTab]) ?><?= $syncCounts['warnings'] > 0 ? ' · ' . e($syncCounts['warnings']) . ' avisos de Meta' : '' ?>.</span>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!empty($syncWarnings)) : ?>
  <div class="social-notice danger">
    <strong><?= $isYouTubeTab ? 'YouTube' : ($isTikTokTab ? 'TikTok' : 'Meta') ?> sincronizó con observaciones</strong>
    <span><?= e(implode(' · ', array_slice($syncWarnings, 0, 3))) ?></span>
  </div>
<?php endif; ?>

<?php if ($isComingSoonTab) : ?>
  <section class="social-development-panel <?= e($activeTab) ?>" aria-labelledby="development-panel-title">
    <div class="social-development-heading">
      <span class="social-development-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15a1.7 1.7 0 0 0-1.56-1.03H3v-4h.08A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63a1.7 1.7 0 0 0 1.03-1.56V3h4v.08A1.7 1.7 0 0 0 15 4.64a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9a1.7 1.7 0 0 0 1.56 1.03H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z"></path></svg>
      </span>
      <div>
        <span class="eyebrow">En desarrollo</span>
        <h2 id="development-panel-title">Preparando la integración con <?= e($tabLabels[$activeTab]) ?></h2>
        <p>Esta pestaña ya forma parte del dashboard, pero todavía no solicita credenciales ni sincroniza información.</p>
      </div>
    </div>

    <?php if ($isTikTokTab) : ?>
      <div class="social-development-callout">
        <strong>Hay dos conexiones diferentes</strong>
        <p><b>TikTok orgánico</b> obtiene el perfil y las publicaciones. <b>TikTok Ads</b> obtiene inversión, impresiones, alcance, clics, campañas y anuncios; requiere una aprobación adicional en TikTok for Business.</p>
      </div>

      <div class="social-setup-grid">
        <article><span>01</span><h3>Crear la app</h3><p>Registra una aplicación web en TikTok for Developers y agrega Login Kit y Display API.</p></article>
        <article><span>02</span><h3>Solicitar permisos</h3><p>Para publicaciones orgánicas se necesitan <code>user.info.basic</code> y <code>video.list</code>.</p></article>
        <article><span>03</span><h3>Registrar el callback</h3><p>Autoriza exactamente la URL HTTPS que usará el dashboard al volver de TikTok.</p></article>
        <article><span>04</span><h3>Pasar revisión</h3><p>TikTok debe aprobar los productos y permisos antes de conectar la cuenta empresarial en producción.</p></article>
      </div>

      <div class="social-development-columns">
        <article>
          <h3>Datos que podremos mostrar</h3>
          <ul>
            <li>Perfil, avatar, nombre y enlace público.</li>
            <li>Videos publicados con portada, fecha y duración.</li>
            <li>Visualizaciones, me gusta, comentarios y compartidos por video.</li>
            <li>Engagement calculado y evolución por día, semana o mes.</li>
            <li>Con Marketing API: gasto, impresiones, alcance, clics y campañas de Ads.</li>
          </ul>
        </article>
        <article>
          <h3>Variables que prepararemos en <code>.env</code></h3>
          <pre><code>TIKTOK_CLIENT_KEY=
TIKTOK_CLIENT_SECRET=
TIKTOK_REDIRECT_URI=https://sucasainmobiliaria.com.co/dashboard-marketing/tiktok/callback
TIKTOK_ADVERTISER_ID=</code></pre>
          <p class="social-development-note">El token no se pegará manualmente: se obtendrá al pulsar “Conectar con TikTok” y se guardará cifrado, igual que YouTube.</p>
        </article>
      </div>

      <div class="social-development-actions">
        <a class="button primary" href="https://developers.tiktok.com/" target="_blank" rel="noopener">Abrir TikTok for Developers</a>
        <a class="button ghost" href="https://business-api.tiktok.com/portal" target="_blank" rel="noopener">Abrir TikTok for Business</a>
      </div>
    <?php elseif ($isLinkedInTab) : ?>
      <div class="social-development-columns compact">
        <article>
          <h3>Qué necesitaremos</h3>
          <ul>
            <li>Aplicación en LinkedIn Developers vinculada a la empresa.</li>
            <li>Acceso aprobado a Marketing Developer Platform.</li>
            <li>Usuario administrador de la página y el ID de la organización.</li>
            <li>Permisos para leer páginas, publicaciones y estadísticas organizacionales.</li>
          </ul>
        </article>
        <article>
          <h3>Qué se mostrará</h3>
          <ul>
            <li>Seguidores, impresiones, clics e interacción.</li>
            <li>Publicaciones con sus KPI y comparativos.</li>
            <li>Audiencia de la página y campañas, si LinkedIn aprueba Ads.</li>
          </ul>
        </article>
      </div>
      <div class="social-development-actions"><a class="button primary" href="https://www.linkedin.com/developers/apps" target="_blank" rel="noopener">Abrir LinkedIn Developers</a></div>
    <?php else : ?>
      <div class="social-development-columns compact">
        <article>
          <h3>Estado actual</h3>
          <p>La solicitud de acceso a la API de Perfil de Negocio de Google ya fue enviada. Cuando Google la apruebe podremos terminar OAuth y conectar la ubicación.</p>
        </article>
        <article>
          <h3>Qué se mostrará</h3>
          <ul>
            <li>Búsquedas y visualizaciones del perfil.</li>
            <li>Llamadas, rutas, reservas y clics al sitio web.</li>
            <li>Publicaciones, reseñas y evolución por periodo cuando la API lo permita.</li>
          </ul>
        </article>
      </div>
      <div class="social-development-actions"><a class="button primary" href="https://developers.google.com/my-business" target="_blank" rel="noopener">Ver API de Perfil de Negocio</a></div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($isTikTokTab) : ?>
  <?php if (!$tiktokConnected) : ?>
    <section class="social-panel youtube-onboarding tiktok-onboarding" aria-labelledby="tiktok-onboarding-title">
      <div class="youtube-onboarding-mark tiktok-mark" aria-hidden="true">TT</div>
      <div>
        <span class="eyebrow">Conexión pendiente</span>
        <h2 id="tiktok-onboarding-title">Conecta la cuenta para importar sus videos</h2>
        <p>La autorización es de solo lectura. Los tokens se guardan cifrados y se renuevan automáticamente.</p>
        <ol>
          <li><strong>Conectar:</strong> inicia sesión con la cuenta propietaria de los videos.</li>
          <li><strong>Autorizar:</strong> acepta perfil básico y lista de videos.</li>
          <li><strong>Sincronizar:</strong> selecciona el periodo que deseas descargar.</li>
        </ol>
      </div>
    </section>
  <?php else : ?>
    <section class="youtube-channel-card tiktok-account-card" aria-label="Cuenta de TikTok conectada">
      <?php if (!empty($tiktokConnection['avatar_url'])) : ?>
        <img src="<?= e($tiktokConnection['avatar_url']) ?>" alt="Avatar de <?= e($tiktokConnection['display_name'] ?? '') ?>" width="76" height="76">
      <?php else : ?><span class="youtube-channel-placeholder tiktok-placeholder" aria-hidden="true">TT</span><?php endif; ?>
      <div>
        <span>Cuenta conectada</span>
        <h2><?= e($tiktokConnection['display_name'] ?? 'TikTok') ?></h2>
        <p><?= !empty($tiktokConnection['username']) ? '@' . e($tiktokConnection['username']) : 'Perfil autorizado' ?><?= !empty($tiktokConnection['last_sync']) ? ' · Última sincronización: ' . e($tiktokConnection['last_sync']) : ' · Pendiente de primera sincronización' ?></p>
      </div>
      <dl>
        <div><dt>Seguidores actuales</dt><dd><?= $tiktokHasStatsScope ? e($fmt($tiktokConnection['follower_count'] ?? 0)) : 'N/D' ?></dd></div>
        <div><dt>Me gusta del perfil</dt><dd><?= $tiktokHasStatsScope ? e($fmt($tiktokConnection['likes_count'] ?? 0)) : 'N/D' ?></dd></div>
        <div><dt>Videos sincronizados</dt><dd><?= e($fmt(count($tiktokVideos))) ?></dd></div>
      </dl>
    </section>

    <section class="social-kpis youtube-kpis tiktok-kpis" aria-label="Indicadores principales de TikTok">
      <?php foreach ([
        ['label' => 'Visualizaciones', 'key' => 'views'], ['label' => 'Me gusta', 'key' => 'likes'],
        ['label' => 'Comentarios', 'key' => 'comments'], ['label' => 'Compartidos', 'key' => 'shares'],
        ['label' => 'Interacciones', 'key' => 'interactions'], ['label' => 'Videos publicados', 'key' => 'videos'],
      ] as $card) : ?>
        <?php $change = $delta((float) ($tiktokSummary[$card['key']] ?? 0), (float) ($tiktokPrevious[$card['key']] ?? 0), (bool) ($tiktokPeriod['comparison_ready'] ?? false)); ?>
        <article><span><?= e($card['label']) ?></span><strong><?= e($fmt($tiktokSummary[$card['key']] ?? 0)) ?></strong><small class="<?= e($change['direction']) ?>"><?= e($change['label']) ?></small></article>
      <?php endforeach; ?>
    </section>
    <p class="social-period-note">Las cifras por video son acumulados actuales de TikTok y se agrupan según la fecha de publicación. Periodo: <strong><?= e($from) ?> a <?= e($to) ?></strong>.</p>

    <section class="social-panel social-trend tiktok-trend">
      <div class="social-panel-head"><div><h2>Evolución de publicaciones</h2><p>Visualizaciones acumuladas e interacciones agrupadas por fecha de publicación.</p></div><div class="social-trend-tools"><span class="social-chart-drag-hint">↔ Arrastra para explorar</span><div class="social-trend-nav" data-social-chart-nav><button type="button" data-chart-start>«</button><button type="button" data-chart-prev>‹</button><span data-chart-window><?= e(count($tiktokSeries)) ?> periodos</span><button type="button" data-chart-next>›</button><button type="button" data-chart-end>»</button></div></div></div>
      <div class="social-chart tiktok-chart" style="--periods: <?= e(max(1, count($tiktokSeries))) ?>" data-social-chart data-period-step="<?= e(max(48, (int) round($tiktokXStep))) ?>" data-chart-unit="periodo" data-chart-unit-plural="periodos">
        <?php if ($tiktokTrendPoints) : ?>
          <div class="social-chart-tooltip" data-social-tooltip role="status"></div>
          <svg viewBox="0 0 <?= e($tiktokTrendWidth) ?> 300" style="min-width: <?= e($tiktokTrendWidth) ?>px" role="img" aria-label="Visualizaciones e interacciones de TikTok">
            <g class="trend-grid"><?php for ($i = 0; $i <= 4; $i++) : $gridY = $tiktokPlotTop + (($tiktokPlotBottom - $tiktokPlotTop) * ($i / 4)); ?><line x1="<?= e($tiktokPlotLeft) ?>" y1="<?= e($gridY) ?>" x2="<?= e($tiktokPlotRight) ?>" y2="<?= e($gridY) ?>"></line><?php endfor; ?></g>
            <?php foreach ($tiktokTrendPoints as $point) : $barHeight = max(2, $tiktokPlotBottom - (float) $point['views_y']); ?><rect class="trend-bar tiktok-views" x="<?= e($point['x'] - ($tiktokBarWidth / 2)) ?>" y="<?= e($tiktokPlotBottom - $barHeight) ?>" width="<?= e($tiktokBarWidth) ?>" height="<?= e($barHeight) ?>" rx="6"></rect><?php endforeach; ?>
            <polyline class="trend-line tiktok-interactions" points="<?= e($tiktokInteractionsPolyline) ?>"></polyline>
            <?php foreach ($tiktokTrendPoints as $index => $point) : $row = $point['row']; $tooltip = '<strong>' . e((string) ($row['_chart_label'] ?? $row['metric_date'] ?? '')) . '</strong><span>Vistas: ' . e($fmt($row['views'] ?? 0)) . '</span><span>Interacciones: ' . e($fmt($row['interactions'] ?? 0)) . '</span><span>Videos: ' . e($fmt($row['videos_published'] ?? 0)) . '</span>'; ?>
              <g class="social-trend-point" tabindex="0" data-tooltip-html="<?= e($tooltip) ?>"><rect class="trend-hitbox" x="<?= e($point['x'] - max(16, $tiktokXStep / 2)) ?>" y="<?= e($tiktokPlotTop - 12) ?>" width="<?= e(max(32, $tiktokXStep)) ?>" height="<?= e($tiktokPlotBottom - $tiktokPlotTop + 30) ?>"></rect><circle class="tiktok-view-dot" cx="<?= e($point['x']) ?>" cy="<?= e($point['views_y']) ?>" r="4"></circle><circle class="tiktok-interaction-dot" cx="<?= e($point['x']) ?>" cy="<?= e($point['interactions_y']) ?>" r="4"></circle><?php if ($index % $tiktokLabelEvery === 0 || $index === count($tiktokTrendPoints) - 1) : ?><text x="<?= e($point['x']) ?>" y="276"><?= e($row['_chart_short'] ?? '') ?></text><?php endif; ?></g>
            <?php endforeach; ?>
          </svg>
        <?php else : ?><div class="empty-state">Pulsa <strong>Sincronizar TikTok</strong> para cargar la evolución.</div><?php endif; ?>
      </div>
      <div class="social-legend tiktok-legend"><span class="tiktok-views"></span> Visualizaciones <span class="tiktok-interactions"></span> Interacciones</div>
    </section>

    <section class="social-panel youtube-videos-panel tiktok-videos-panel">
      <div class="social-panel-head"><div><h2>Videos publicados</h2><p>Agrupados por mes con sus KPI acumulados actuales.</p></div><span><?= e(count($tiktokVideos)) ?> videos</span></div>
      <div class="social-post-months youtube-video-months">
        <?php foreach ($tiktokVideoMonths as $monthKey => $month) : ?>
          <details class="social-post-month" <?= $monthKey === array_key_first($tiktokVideoMonths) ? 'open' : '' ?>><summary><span><strong><?= e($month['label']) ?></strong><small><?= e(count($month['videos'])) ?> videos · <?= e($fmt($month['views'])) ?> vistas</small></span><span class="social-post-month-chevron" aria-hidden="true"></span></summary><div class="youtube-video-list">
          <?php foreach ($month['videos'] as $video) : ?><article class="youtube-video-row"><div class="youtube-video-thumb"><?php if (!empty($video['cover_url'])) : ?><img src="<?= e($video['cover_url']) ?>" alt="" loading="lazy" width="120" height="160"><?php endif; ?><span><?= e($duration($video['duration_seconds'] ?? 0)) ?></span></div><div class="youtube-video-copy"><time><?= e(substr((string) ($video['published_at'] ?? ''), 0, 16)) ?></time><strong><?= e($video['title'] ?: 'Video sin título') ?></strong><small><?= e($fmt($video['likes'] ?? 0)) ?> me gusta · <?= e($fmt($video['comments'] ?? 0)) ?> comentarios · <?= e($fmt($video['shares'] ?? 0)) ?> compartidos</small></div><div class="youtube-video-metric"><span>Vistas</span><strong><?= e($fmt($video['views'] ?? 0)) ?></strong></div><div class="social-post-row-actions"><button class="button ghost mini" type="button" data-social-post-detail data-detail="<?= e($tiktokVideoDetail($video)) ?>">Detalles</button><?php if (!empty($video['permalink'])) : ?><a class="button ghost mini" href="<?= e($video['permalink']) ?>" target="_blank" rel="noopener">Ver</a><?php endif; ?></div></article><?php endforeach; ?>
          </div></details>
        <?php endforeach; ?>
        <?php if (!$tiktokVideoMonths) : ?><div class="empty-state">No hay videos sincronizados en este periodo.</div><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php if ($isYouTubeTab) : ?>
  <?php if (!$youtubeConnected) : ?>
    <section class="social-panel youtube-onboarding" aria-labelledby="youtube-onboarding-title">
      <div class="youtube-onboarding-mark" aria-hidden="true">
        <svg viewBox="0 0 48 34" role="img"><rect width="48" height="34" rx="10"></rect><path d="M20 10l12 7-12 7z"></path></svg>
      </div>
      <div>
        <span class="eyebrow">Conexión pendiente</span>
        <h2 id="youtube-onboarding-title">Conecta el canal para ver sus estadísticas</h2>
        <p>La autorización es de solo lectura. El dashboard guardará los tokens cifrados y los renovará automáticamente.</p>
        <ol>
          <li><strong>Conectar:</strong> inicia sesión con la cuenta administradora.</li>
          <li><strong>Autorizar:</strong> acepta YouTube y YouTube Analytics.</li>
          <li><strong>Sincronizar:</strong> selecciona el periodo y descarga los indicadores.</li>
        </ol>
      </div>
    </section>
  <?php else : ?>
    <section class="youtube-channel-card" aria-label="Canal conectado">
      <?php if (!empty($youtubeConnection['thumbnail_url'])) : ?>
        <img src="<?= e($youtubeConnection['thumbnail_url']) ?>" alt="Imagen del canal <?= e($youtubeConnection['channel_title'] ?? '') ?>" width="76" height="76">
      <?php else : ?>
        <span class="youtube-channel-placeholder" aria-hidden="true">YT</span>
      <?php endif; ?>
      <div>
        <span>Canal conectado</span>
        <h2><?= e($youtubeConnection['channel_title'] ?? 'YouTube') ?></h2>
        <p><?= e($youtubeConnection['channel_handle'] ?? '') ?><?= !empty($youtubeConnection['last_sync']) ? ' · Última sincronización: ' . e($youtubeConnection['last_sync']) : ' · Pendiente de primera sincronización' ?></p>
      </div>
      <dl>
        <div><dt>Suscriptores actuales</dt><dd><?= e($fmt($youtubeConnection['subscriber_count'] ?? 0)) ?></dd></div>
        <div><dt>Vistas del canal</dt><dd><?= e($fmt($youtubeConnection['view_count'] ?? 0)) ?></dd></div>
        <div><dt>Videos totales</dt><dd><?= e($fmt($youtubeConnection['video_count'] ?? 0)) ?></dd></div>
      </dl>
    </section>

    <section class="social-kpis youtube-kpis" aria-label="Indicadores principales de YouTube">
      <?php foreach ($youtubeMetricCards as $card) : ?>
        <?php $change = $delta((float) $card['value'], (float) $card['prev'], (bool) ($youtubePeriod['comparison_ready'] ?? false)); ?>
        <article title="Periodo <?= e($from) ?> a <?= e($to) ?>">
          <span><?= e($card['label']) ?></span>
          <strong><?= e($card['format']($card['value'])) ?></strong>
          <small class="<?= e($change['direction']) ?>"><?= e($change['label']) ?></small>
        </article>
      <?php endforeach; ?>
    </section>
    <p class="social-period-note">Periodo actual: <strong><?= e($from) ?> a <?= e($to) ?></strong>. Datos guardados para <strong><?= e((int) ($youtubePeriod['covered_days'] ?? count($youtubeSeries))) ?></strong> de <?= e((int) ($youtubePeriod['requested_days'] ?? 0)) ?> días seleccionados.</p>

    <section class="social-grid youtube-overview-grid">
      <article class="social-panel social-trend youtube-trend">
        <div class="social-panel-head">
          <div>
            <h2>Evolución del canal</h2>
            <p>Visualizaciones y horas de reproducción <?= e($chartGranularityCopy['description']) ?>.</p>
          </div>
          <div class="social-trend-tools">
            <span class="social-chart-granularity">Agrupado por <?= e($chartGranularityCopy['label']) ?></span>
            <span class="social-chart-drag-hint">↔ Arrastra para explorar</span>
            <div class="social-trend-nav" data-social-chart-nav>
              <button type="button" data-chart-start aria-label="Ir al primer periodo">«</button>
              <button type="button" data-chart-prev aria-label="Ver periodos anteriores">‹</button>
              <span data-chart-window><?= e(count($youtubeSeries)) ?> <?= e($chartGranularityCopy['plural']) ?></span>
              <button type="button" data-chart-next aria-label="Ver periodos siguientes">›</button>
              <button type="button" data-chart-end aria-label="Ir al último periodo">»</button>
            </div>
          </div>
        </div>
        <div class="social-chart youtube-chart" style="--periods: <?= e(max(1, count($youtubeSeries))) ?>" data-social-chart data-period-step="<?= e(max(48, (int) round($youtubeXStep))) ?>" data-chart-unit="<?= e($chartGranularityCopy['label']) ?>" data-chart-unit-plural="<?= e($chartGranularityCopy['plural']) ?>" aria-label="Visualizaciones y tiempo de reproducción <?= e($chartGranularityCopy['description']) ?>. Arrastra horizontalmente para explorar.">
          <?php if ($youtubeTrendPoints) : ?>
            <div class="social-chart-tooltip" data-social-tooltip role="status" aria-live="polite"></div>
            <svg viewBox="0 0 <?= e($youtubeTrendWidth) ?> <?= e($youtubeTrendHeight) ?>" style="min-width: <?= e($youtubeTrendWidth) ?>px" role="img" aria-label="Tendencia de YouTube <?= e($chartGranularityCopy['description']) ?>">
              <g class="trend-grid">
                <?php for ($i = 0; $i <= 4; $i++) : $gridY = $youtubePlotTop + (($youtubePlotBottom - $youtubePlotTop) * ($i / 4)); ?>
                  <line x1="<?= e($youtubePlotLeft) ?>" y1="<?= e($gridY) ?>" x2="<?= e($youtubePlotRight) ?>" y2="<?= e($gridY) ?>"></line>
                <?php endfor; ?>
              </g>
              <text class="trend-axis-label" x="10" y="<?= e($youtubePlotTop + 8) ?>">Vistas</text>
              <?php foreach ($youtubeTrendPoints as $point) : ?>
                <?php $barHeight = max(2, $youtubePlotBottom - (float) $point['views_y']); ?>
                <rect class="trend-bar youtube-views" x="<?= e($point['x'] - ($youtubeBarWidth / 2)) ?>" y="<?= e($youtubePlotBottom - $barHeight) ?>" width="<?= e($youtubeBarWidth) ?>" height="<?= e($barHeight) ?>" rx="6"></rect>
              <?php endforeach; ?>
              <polyline class="trend-line youtube-watch" points="<?= e($youtubeWatchPolyline) ?>"></polyline>
              <?php foreach ($youtubeTrendPoints as $index => $point) : ?>
                <?php
                  $row = $point['row'];
                  $tooltip = '<strong>' . e((string) ($row['_chart_label'] ?? $row['metric_date'] ?? '')) . '</strong>'
                    . '<span>Visualizaciones: ' . e($fmt($row['views'] ?? 0)) . '</span>'
                    . '<span>Horas vistas: ' . e($watchHours($row['watch_minutes'] ?? 0)) . '</span>'
                    . '<span>Suscriptores netos: ' . e($fmt((int) ($row['subscribers_gained'] ?? 0) - (int) ($row['subscribers_lost'] ?? 0))) . '</span>';
                ?>
                <g class="social-trend-point" tabindex="0" role="button" aria-label="Datos de <?= e($row['_chart_label'] ?? $row['metric_date'] ?? '') ?>" data-tooltip-html="<?= e($tooltip) ?>">
                  <rect class="trend-hitbox" x="<?= e($point['x'] - max(16, $youtubeXStep / 2)) ?>" y="<?= e($youtubePlotTop - 12) ?>" width="<?= e(max(32, $youtubeXStep)) ?>" height="<?= e($youtubePlotBottom - $youtubePlotTop + 30) ?>"></rect>
                  <circle class="youtube-view-dot" cx="<?= e($point['x']) ?>" cy="<?= e($point['views_y']) ?>" r="4"></circle>
                  <circle class="youtube-watch-dot" cx="<?= e($point['x']) ?>" cy="<?= e($point['watch_y']) ?>" r="4"></circle>
                  <?php if ($index % $youtubeLabelEvery === 0 || $index === count($youtubeTrendPoints) - 1) : ?>
                    <text x="<?= e($point['x']) ?>" y="276"><?= e($row['_chart_short'] ?? substr((string) ($row['metric_date'] ?? ''), 5, 5)) ?></text>
                  <?php endif; ?>
                </g>
              <?php endforeach; ?>
            </svg>
          <?php else : ?>
            <div class="empty-state">Presiona <strong>Sincronizar YouTube</strong> para cargar la tendencia del periodo.</div>
          <?php endif; ?>
        </div>
        <div class="social-legend youtube-legend"><span class="youtube-views"></span> Visualizaciones <span class="youtube-watch"></span> Horas vistas</div>
      </article>

      <article class="social-panel youtube-content-types">
        <div class="social-panel-head">
          <div><h2>Rendimiento por formato</h2><p>Distribución informada por YouTube Analytics.</p></div>
        </div>
        <div class="youtube-format-list">
          <?php foreach (($youtubeBreakdowns['creator_content_type'] ?? []) as $row) : ?>
            <article>
              <span><?= e($youtubeLabel((string) $row['label'])) ?></span>
              <strong><?= e($fmt($row['value'] ?? 0)) ?></strong>
              <small><?= e($watchHours($row['watch_minutes'] ?? 0)) ?> horas vistas</small>
            </article>
          <?php endforeach; ?>
          <?php if (empty($youtubeBreakdowns['creator_content_type'])) : ?><div class="empty-state">Sin distribución por formato para este periodo.</div><?php endif; ?>
        </div>
      </article>
    </section>

    <section class="social-panel youtube-discovery-panel">
      <div class="social-panel-head">
        <div><h2>Descubrimiento y audiencia</h2><p>Cómo encuentran el canal y desde dónde consumen los videos.</p></div>
      </div>
      <div class="youtube-breakdown-grid">
        <?php foreach ([
          'traffic_source' => 'Fuentes de tráfico',
          'search_term' => 'Búsquedas en YouTube',
          'country' => 'Países principales',
          'device' => 'Dispositivos',
          'subscribed_status' => 'Suscripción',
        ] as $breakdownType => $breakdownTitle) : ?>
          <?php $rows = array_slice($youtubeBreakdowns[$breakdownType] ?? [], 0, 10); $maxValue = max(1, ...array_values(array_map(static fn (array $row): float => (float) ($row['value'] ?? 0), $rows ?: [['value' => 1]]))); ?>
          <div class="youtube-breakdown-list">
            <h3><?= e($breakdownTitle) ?></h3>
            <?php foreach ($rows as $row) : ?>
              <div>
                <span title="<?= e((string) $row['label']) ?>"><?= e($youtubeLabel((string) $row['label'])) ?></span>
                <b><?= e($fmt($row['value'] ?? 0)) ?></b>
                <i style="--bar: <?= e((int) round(((float) ($row['value'] ?? 0) / $maxValue) * 100)) ?>%"></i>
              </div>
            <?php endforeach; ?>
            <?php if (!$rows) : ?><div class="empty-state">Sin datos suficientes.</div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="social-panel youtube-videos-panel">
      <div class="social-panel-head">
        <div><h2>Videos publicados</h2><p>Publicaciones agrupadas por mes con sus KPI del periodo seleccionado.</p></div>
        <span><?= e(count($youtubeVideos)) ?> videos</span>
      </div>
      <div class="social-post-months youtube-video-months">
        <?php foreach ($youtubeVideoMonths as $monthKey => $month) : ?>
          <details class="social-post-month" <?= $monthKey === array_key_first($youtubeVideoMonths) ? 'open' : '' ?>>
            <summary>
              <span><strong><?= e($month['label']) ?></strong><small><?= e(count($month['videos'])) ?> videos · <?= e($fmt($month['views'])) ?> visualizaciones del periodo</small></span>
              <span class="social-post-month-chevron" aria-hidden="true"></span>
            </summary>
            <div class="youtube-video-list">
              <?php foreach ($month['videos'] as $video) : ?>
                <article class="youtube-video-row">
                  <div class="youtube-video-thumb">
                    <?php if (!empty($video['thumbnail_url'])) : ?><img src="<?= e($video['thumbnail_url']) ?>" alt="" loading="lazy" width="180" height="101"><?php endif; ?>
                    <span><?= e($duration($video['duration_seconds'] ?? 0)) ?></span>
                  </div>
                  <div class="youtube-video-copy">
                    <time><?= e(substr((string) ($video['published_at'] ?? ''), 0, 16)) ?></time>
                    <strong><?= e($video['title'] ?: 'Video sin título') ?></strong>
                    <small><?= ($video['content_type'] ?? '') === 'live' ? 'Transmisión' : 'Video' ?> · <?= e($fmt(($video['likes'] ?? 0) ?: ($video['public_likes'] ?? 0))) ?> me gusta · <?= e($fmt(($video['comments'] ?? 0) ?: ($video['public_comments'] ?? 0))) ?> comentarios</small>
                  </div>
                  <div class="youtube-video-metric"><span>Vistas</span><strong><?= e($fmt(($video['views'] ?? 0) ?: ($video['public_views'] ?? 0))) ?></strong></div>
                  <div class="youtube-video-metric"><span>Horas</span><strong><?= e($watchHours($video['watch_minutes'] ?? 0)) ?></strong></div>
                  <div class="youtube-video-metric"><span>Retención</span><strong><?= e(number_format((float) ($video['average_view_percentage'] ?? 0), 1, ',', '.')) ?>%</strong></div>
                  <div class="social-post-row-actions">
                    <button class="button ghost mini" type="button" data-social-post-detail data-detail="<?= e($youtubeVideoDetail($video)) ?>">Detalles</button>
                    <a class="button ghost mini" href="<?= e($video['permalink'] ?? '') ?>" target="_blank" rel="noopener">Ver</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endforeach; ?>
        <?php if (!$youtubeVideoMonths) : ?><div class="empty-state">No hay videos sincronizados en este periodo.</div><?php endif; ?>
      </div>
    </section>

    <section class="social-panel youtube-top-panel">
      <div class="social-panel-head"><div><h2>Videos destacados</h2><p>Ordenados por visualizaciones dentro del periodo.</p></div></div>
      <div class="youtube-top-grid">
        <?php foreach ($youtubeTopVideos as $position => $video) : ?>
          <article>
            <?php if (!empty($video['thumbnail_url'])) : ?><img src="<?= e($video['thumbnail_url']) ?>" alt="" loading="lazy" width="320" height="180"><?php endif; ?>
            <span>#<?= e($position + 1) ?></span>
            <div><strong><?= e($video['title'] ?: 'Video sin título') ?></strong><small><?= e($fmt(($video['views'] ?? 0) ?: ($video['public_views'] ?? 0))) ?> vistas · <?= e($watchHours($video['watch_minutes'] ?? 0)) ?> horas</small></div>
            <button class="button ghost mini" type="button" data-social-post-detail data-detail="<?= e($youtubeVideoDetail($video)) ?>">Más detalles</button>
          </article>
        <?php endforeach; ?>
        <?php if (!$youtubeTopVideos) : ?><div class="empty-state">Sin videos destacados todavía.</div><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php if ($isOrganicTab) : ?>
<section class="social-kpis" aria-label="Indicadores principales">
  <?php foreach ($metricCards as $card) : ?>
    <?php
      $available = !array_key_exists('available', $card) || (bool) $card['available'];
      $change = $available ? $delta((float) $card['value'], (float) $card['prev'], $comparisonReady) : ['label' => (string) ($card['unavailable'] ?? 'Dato no disponible.'), 'direction' => 'flat'];
    ?>
    <article title="Periodo actual: <?= $periodText ?>. Comparación: <?= $previousText ?>.">
      <span><?= e($card['label']) ?></span>
      <strong><?= !$available ? 'N/D' : (!empty($card['percent']) ? e($pct($card['value'])) : e($fmt($card['value']))) ?></strong>
      <small class="<?= e($change['direction']) ?>"><?= e($change['label']) ?></small>
    </article>
  <?php endforeach; ?>
</section>
<p class="social-period-note">Periodo actual: <strong><?= $periodText ?></strong>. <?= $comparisonReady ? 'Comparado contra:' : 'Aún no hay base completa para comparar contra:' ?> <strong><?= $previousText ?></strong>. <?= $coverageText ?></p>

<section class="social-grid">
  <article class="social-panel social-trend">
    <div class="social-panel-head">
      <div>
        <h2>Evolución de <?= e($tabLabels[$activeTab]) ?></h2>
        <p><?= e($exposureLabel) ?> e interacciones <?= e($chartGranularityCopy['description']) ?>.</p>
      </div>
      <div class="social-trend-tools">
        <span class="social-chart-granularity">Agrupado por <?= e($chartGranularityCopy['label']) ?></span>
        <span class="social-chart-drag-hint">↔ Arrastra para explorar</span>
        <div class="social-trend-nav" data-social-chart-nav>
          <button type="button" data-chart-start aria-label="Ir al primer periodo">«</button>
          <button type="button" data-chart-prev aria-label="Ver periodos anteriores">‹</button>
          <span data-chart-window><?= e(count($series)) ?> <?= e($chartGranularityCopy['plural']) ?></span>
          <button type="button" data-chart-next aria-label="Ver periodos siguientes">›</button>
          <button type="button" data-chart-end aria-label="Ir al último periodo">»</button>
        </div>
      </div>
    </div>
    <div class="social-chart" style="--periods: <?= e(max(1, count($series))) ?>" aria-label="Tendencia de <?= e($exposureLabel) ?> e interacciones <?= e($chartGranularityCopy['description']) ?>. Arrastra horizontalmente para explorar." data-social-chart data-period-step="<?= e(max(48, (int) round($xStep))) ?>" data-chart-unit="<?= e($chartGranularityCopy['label']) ?>" data-chart-unit-plural="<?= e($chartGranularityCopy['plural']) ?>">
      <?php if ($series) : ?>
        <div class="social-chart-tooltip" data-social-tooltip role="status" aria-live="polite"></div>
        <svg viewBox="0 0 <?= e($trendWidth) ?> <?= e($trendHeight) ?>" style="min-width: <?= e($trendWidth) ?>px" role="img" aria-label="<?= e($exposureLabel) ?> e interacciones <?= e($chartGranularityCopy['description']) ?>">
          <g class="trend-grid">
            <?php for ($i = 0; $i <= 3; $i++) : ?>
              <?php $reachYGrid = $reachTop + (($reachBottom - $reachTop) * ($i / 3)); ?>
              <?php $engagementYGrid = $engagementTop + (($engagementBottom - $engagementTop) * ($i / 3)); ?>
              <line x1="<?= e($plotLeft) ?>" y1="<?= e($reachYGrid) ?>" x2="<?= e($plotRight) ?>" y2="<?= e($reachYGrid) ?>"></line>
              <line x1="<?= e($plotLeft) ?>" y1="<?= e($engagementYGrid) ?>" x2="<?= e($plotRight) ?>" y2="<?= e($engagementYGrid) ?>"></line>
            <?php endfor; ?>
          </g>
          <text class="trend-axis-label" x="10" y="<?= e($reachTop + 8) ?>"><?= e($exposureLabel) ?></text>
          <text class="trend-axis-label" x="10" y="<?= e($engagementTop + 8) ?>">Interacciones</text>
          <?php foreach ($reachPoints as $index => $pointInfo) : ?>
            <?php
              $point = $pointInfo['point'];
              $reachMissing = (int) ($point['exposure'] ?? 0) === 0 && (int) ($point['engagement'] ?? 0) > 0;
              $barHeight = $reachMissing ? 2 : max(2, (float) $pointInfo['bar']);
            ?>
            <rect class="trend-bar reach <?= $reachMissing ? 'missing' : '' ?>" x="<?= e($pointInfo['x'] - ($barWidth / 2)) ?>" y="<?= e($reachBottom - $barHeight) ?>" width="<?= e($barWidth) ?>" height="<?= e($barHeight) ?>" rx="6"></rect>
          <?php endforeach; ?>
          <polyline class="trend-line engagement" points="<?= e($engagementPolyline) ?>"></polyline>
          <?php foreach ($reachPoints as $index => $pointInfo) : ?>
            <?php
              $point = $pointInfo['point'];
              $engagementInfo = $engagementPoints[$index] ?? $pointInfo;
              $reachMissing = (int) ($point['exposure'] ?? 0) === 0 && (int) ($point['engagement'] ?? 0) > 0;
              $reachLabel = $reachMissing ? 'No disponible' : $fmt($point['exposure']);
              $reachHelp = $reachMissing ? '<span class="muted">Meta no entregó esta métrica para el día.</span>' : '';
              $pointPeriod = (string) ($point['_chart_label'] ?? $point['date']);
              $tooltip = $pointPeriod . ' | ' . $exposureLabel . ': ' . $reachLabel . ' | Interacciones: ' . $fmt($point['engagement']) . ' | Seguidores: ' . $fmt($point['followers']);
              $htmlTooltip = '<strong>' . e($pointPeriod) . '</strong><span>' . e($exposureLabel) . ': ' . e($reachLabel) . '</span>' . $reachHelp . '<span>Interacciones: ' . e($fmt($point['engagement'])) . '</span><span>Seguidores al cierre: ' . e($fmt($point['followers'])) . '</span>';
            ?>
            <g class="social-trend-point" tabindex="0" role="button" aria-label="Datos de <?= e($pointPeriod) ?>" data-tooltip="<?= e($tooltip) ?>" data-tooltip-html="<?= e($htmlTooltip) ?>">
              <title><?= e($tooltip) ?></title>
              <rect class="trend-hitbox" x="<?= e($pointInfo['x'] - max(16, $xStep / 2)) ?>" y="<?= e($reachTop - 12) ?>" width="<?= e(max(32, $xStep)) ?>" height="<?= e($engagementBottom - $reachTop + 32) ?>"></rect>
              <circle class="reach-dot <?= $reachMissing ? 'missing' : '' ?>" cx="<?= e($pointInfo['x']) ?>" cy="<?= e($reachMissing ? $reachBottom : $pointInfo['y']) ?>" r="4"></circle>
              <circle class="engagement-dot" cx="<?= e($engagementInfo['x']) ?>" cy="<?= e($engagementInfo['y']) ?>" r="4"></circle>
              <?php if ($index % $labelEvery === 0 || $index === count($reachPoints) - 1) : ?>
                <text x="<?= e($pointInfo['x']) ?>" y="300"><?= e($point['_chart_short'] ?? substr((string) $point['date'], 5, 5)) ?></text>
              <?php endif; ?>
            </g>
          <?php endforeach; ?>
        </svg>
      <?php else : ?>
        <div class="empty-state">Sin datos diarios para el rango seleccionado.</div>
      <?php endif; ?>
    </div>
    <div class="social-legend"><span class="reach"></span> <?= e($exposureLabel) ?> <span class="engagement"></span> Interacciones</div>
  </article>

  <article class="social-panel">
    <div class="social-panel-head">
      <div>
        <h2>Resumen de <?= e($tabLabels[$activeTab]) ?></h2>
        <p>Rendimiento exclusivo de la red seleccionada.</p>
      </div>
    </div>
    <div class="social-platform-list">
      <?php foreach ($platforms as $key => $row) : ?>
        <?php
          $platformExposure = (int) ($row[$exposureKey] ?? 0);
          $platformReachAvailable = $platformExposure > 0;
          $platformRate = $platformReachAvailable ? ((int) ($row['engagement'] ?? 0) / $platformExposure) * 100 : 0;
          $bar = $platformReachAvailable ? (int) round(($platformExposure / $maxPlatformReach) * 100) : 0;
        ?>
        <div class="social-platform-row <?= e($platformTone[$key] ?? '') ?>">
          <div>
            <strong><?= e($platformLabels[$key] ?? ucfirst((string) $key)) ?></strong>
            <small><?= e($fmt($row['followers'] ?? 0)) ?> seguidores · <?= $platformReachAvailable ? e($pct($platformRate)) : 'N/D' ?> interacción</small>
          </div>
          <span title="<?= $platformReachAvailable ? e($exposureDescription) : 'Meta no entregó esta métrica para el periodo' ?>"><?= $platformReachAvailable ? e($fmt($platformExposure)) : 'N/D' ?></span>
          <i style="--bar: <?= e($bar) ?>%"></i>
        </div>
      <?php endforeach; ?>
      <?php if (!$platforms) : ?><div class="empty-state">No hay datos para el rango seleccionado.</div><?php endif; ?>
    </div>
  </article>
</section>
<?php endif; ?>

<?php if ($isAdsTab) : ?>
<section class="social-panel social-trend social-ads-trend">
  <div class="social-panel-head">
    <div>
      <h2>Evolución de Meta Ads</h2>
      <p>Impresiones y clics en enlace <?= e($chartGranularityCopy['description']) ?>.</p>
    </div>
    <div class="social-trend-tools">
      <span class="social-chart-granularity">Agrupado por <?= e($chartGranularityCopy['label']) ?></span>
      <span class="social-chart-drag-hint">↔ Arrastra para explorar</span>
      <div class="social-trend-nav" data-social-chart-nav>
        <button type="button" data-chart-start aria-label="Ir al primer periodo">«</button>
        <button type="button" data-chart-prev aria-label="Ver periodos anteriores">‹</button>
        <span data-chart-window><?= e(count($adsSeries)) ?> <?= e($chartGranularityCopy['plural']) ?></span>
        <button type="button" data-chart-next aria-label="Ver periodos siguientes">›</button>
        <button type="button" data-chart-end aria-label="Ir al último periodo">»</button>
      </div>
    </div>
  </div>
  <div class="social-chart ads-chart" style="--periods: <?= e(max(1, count($adsSeries))) ?>" data-social-chart data-period-step="<?= e(max(48, (int) round($adsXStep))) ?>" data-chart-unit="<?= e($chartGranularityCopy['label']) ?>" data-chart-unit-plural="<?= e($chartGranularityCopy['plural']) ?>" aria-label="Impresiones y clics de Meta Ads <?= e($chartGranularityCopy['description']) ?>. Arrastra horizontalmente para explorar.">
    <?php if ($adsTrendPoints) : ?>
      <div class="social-chart-tooltip" data-social-tooltip role="status" aria-live="polite"></div>
      <svg viewBox="0 0 <?= e($adsTrendWidth) ?> <?= e($adsTrendHeight) ?>" style="min-width: <?= e($adsTrendWidth) ?>px" role="img" aria-label="Rendimiento de Meta Ads <?= e($chartGranularityCopy['description']) ?>">
        <g class="trend-grid">
          <?php for ($i = 0; $i <= 4; $i++) : $gridY = $adsPlotTop + (($adsPlotBottom - $adsPlotTop) * ($i / 4)); ?>
            <line x1="<?= e($adsPlotLeft) ?>" y1="<?= e($gridY) ?>" x2="<?= e($adsPlotRight) ?>" y2="<?= e($gridY) ?>"></line>
          <?php endfor; ?>
        </g>
        <text class="trend-axis-label" x="10" y="<?= e($adsPlotTop + 8) ?>">Ads</text>
        <?php foreach ($adsTrendPoints as $point) : ?>
          <?php $barHeight = max(2, $adsPlotBottom - (float) $point['impressions_y']); ?>
          <rect class="trend-bar ads-impressions" x="<?= e($point['x'] - ($adsBarWidth / 2)) ?>" y="<?= e($adsPlotBottom - $barHeight) ?>" width="<?= e($adsBarWidth) ?>" height="<?= e($barHeight) ?>" rx="6"></rect>
        <?php endforeach; ?>
        <polyline class="trend-line ads-clicks" points="<?= e($adsClicksPolyline) ?>"></polyline>
        <?php foreach ($adsTrendPoints as $index => $point) : ?>
          <?php
            $row = $point['row'];
            $clickValue = (int) ($row['link_clicks'] ?? $row['clicks'] ?? 0);
            $tooltip = '<strong>' . e((string) ($row['_chart_label'] ?? $row['metric_date'] ?? '')) . '</strong>'
              . '<span>Impresiones: ' . e($fmt($row['impressions'] ?? 0)) . '</span>'
              . '<span>Alcance: ' . e($fmt($row['reach'] ?? 0)) . '</span>'
              . '<span>Clics en enlace: ' . e($fmt($clickValue)) . '</span>'
              . '<span>Inversión: ' . e($money($row['spend'] ?? 0)) . '</span>';
          ?>
          <g class="social-trend-point" tabindex="0" role="button" aria-label="Datos de <?= e($row['_chart_label'] ?? $row['metric_date'] ?? '') ?>" data-tooltip-html="<?= e($tooltip) ?>">
            <rect class="trend-hitbox" x="<?= e($point['x'] - max(16, $adsXStep / 2)) ?>" y="<?= e($adsPlotTop - 12) ?>" width="<?= e(max(32, $adsXStep)) ?>" height="<?= e($adsPlotBottom - $adsPlotTop + 30) ?>"></rect>
            <circle class="ads-impressions-dot" cx="<?= e($point['x']) ?>" cy="<?= e($point['impressions_y']) ?>" r="4"></circle>
            <circle class="ads-clicks-dot" cx="<?= e($point['x']) ?>" cy="<?= e($point['clicks_y']) ?>" r="4"></circle>
            <?php if ($index % $adsLabelEvery === 0 || $index === count($adsTrendPoints) - 1) : ?>
              <text x="<?= e($point['x']) ?>" y="276"><?= e($row['_chart_short'] ?? substr((string) ($row['metric_date'] ?? ''), 5, 5)) ?></text>
            <?php endif; ?>
          </g>
        <?php endforeach; ?>
      </svg>
    <?php else : ?>
      <div class="empty-state">Presiona <strong>Sincronizar Meta ahora</strong> para descargar el desglose diario y construir esta evolución.</div>
    <?php endif; ?>
  </div>
  <div class="social-legend ads-legend"><span class="ads-impressions"></span> Impresiones <span class="ads-clicks"></span> Clics en enlace</div>
</section>

<section class="social-panel social-ads-panel">
  <div class="social-panel-head">
    <div>
      <h2>Ads y campañas</h2>
      <p>Rendimiento del periodo guardado desde Meta Ads.</p>
    </div>
    <div class="social-panel-actions">
      <span><?= $adsPeriodLabel ?: e($ads['date'] ?? '') ?></span>
    </div>
  </div>
  <?php if ($adsPeriodLabel) : ?>
    <p class="social-panel-note"><?= $adsExact ? 'Periodo sincronizado:' : 'No hay datos exactos para el filtro actual. Mostrando último periodo guardado:' ?> <strong><?= $adsPeriodLabel ?></strong>.</p>
  <?php else : ?>
    <p class="social-panel-note">Elige fechas arriba y presiona <strong>Sincronizar Meta ahora</strong> para guardar este periodo de Ads.</p>
  <?php endif; ?>
  <div class="social-ads-kpis">
    <?php foreach ($adsKpiCards as $card) : ?>
      <article>
        <span>
          <?= e($card['label']) ?>
          <button class="metric-help" type="button" aria-label="<?= e($card['label'] . ': ' . $card['help']) ?>" data-metric-help="<?= e($card['help']) ?>">?</button>
        </span>
        <strong><?= e($card['value']) ?></strong>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="social-dual-list">
    <div>
      <h3>Campañas (<?= e(count($adsCampaigns)) ?>)</h3>
      <?php foreach ($adsCampaigns as $campaign) : ?>
        <article class="social-ad-row">
          <div><strong><?= e($campaign['name'] ?: 'Campaña sin nombre') ?></strong><small><?= e($money($campaign['spend'] ?? 0)) ?> · <?= e($fmt($campaign['impressions'] ?? 0)) ?> impresiones</small></div>
          <b><?= e($fmt($campaign['link_clicks'] ?? $campaign['clicks'] ?? 0)) ?></b>
          <button class="button ghost mini social-detail-button" type="button" data-social-ad-detail data-detail="<?= e($adDetail($campaign, 'campaign')) ?>">Detalle</button>
        </article>
      <?php endforeach; ?>
      <?php if (!$adsCampaigns) : ?><div class="empty-state">Sin campañas sincronizadas.</div><?php endif; ?>
    </div>
    <div>
      <h3>Anuncios (<?= e(count($adsRows)) ?>)</h3>
      <?php foreach ($adsRows as $ad) : ?>
        <article class="social-ad-row compact">
          <?php if (!empty($ad['thumbnail_url'])) : ?><img src="<?= e($ad['thumbnail_url']) ?>" alt=""><?php endif; ?>
          <div><strong><?= e($ad['name'] ?: 'Anuncio sin nombre') ?></strong><small><?= e($ad['status'] ?: 'estado n/d') ?> · <?= e($money($ad['spend'] ?? 0)) ?></small></div>
          <b><?= e($fmt($ad['reach'] ?? 0)) ?></b>
          <button class="button ghost mini social-detail-button" type="button" data-social-ad-detail data-detail="<?= e($adDetail($ad, 'ad')) ?>">Detalle</button>
        </article>
      <?php endforeach; ?>
      <?php if (!$adsRows) : ?><div class="empty-state">Sin anuncios sincronizados.</div><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="social-detail-modal" data-social-detail-modal aria-hidden="true">
  <div class="social-detail-backdrop" data-social-detail-close></div>
  <article class="social-detail-card" role="dialog" aria-modal="true" aria-labelledby="social-detail-title">
    <button class="social-detail-close" type="button" data-social-detail-close aria-label="Cerrar">×</button>
    <div class="social-detail-top">
      <img data-social-detail-image alt="">
      <div>
        <h2 id="social-detail-title" data-social-detail-title>Detalle</h2>
        <p data-social-detail-subtitle></p>
      </div>
    </div>
    <p class="social-detail-description" data-social-detail-description hidden></p>
    <div class="social-detail-notice" data-social-detail-notice hidden></div>
    <div class="social-detail-metrics" data-social-detail-metrics></div>
    <div class="social-detail-actions" data-social-detail-actions-section>
      <h3>Acciones registradas</h3>
      <div data-social-detail-actions></div>
    </div>
    <div class="social-detail-footer">
      <a class="button primary" data-social-detail-link href="#" target="_blank" rel="noopener" hidden>Ver publicación</a>
    </div>
  </article>
</div>

<?php if ($isInstagramTab) : ?>
<section class="social-panel social-audience-panel">
    <div class="social-panel-head">
      <div>
        <h2>Audiencia</h2>
        <p>Principales segmentos detectados por Instagram Insights.</p>
      </div>
      <span><?= e($audience['date'] ?? '') ?></span>
    </div>
    <div class="audience-columns">
      <?php foreach (['city' => 'Ciudades', 'country' => 'Países', 'gender' => 'Género', 'age' => 'Edades'] as $breakdown => $label) : ?>
        <div class="audience-list">
          <h3><?= e($label) ?></h3>
          <?php $rows = $audience[$breakdown] ?? []; $maxAudience = max(1, ...array_values(array_map(static fn ($r): int => (int) ($r['value'] ?? 0), $rows ?: [['value' => 1]]))); ?>
          <?php foreach ($rows as $row) : ?>
            <div class="audience-row">
              <span><?= e($row['label']) ?></span>
              <b><?= e($fmt($row['value'])) ?></b>
              <i style="--bar: <?= e((int) round(((int) $row['value'] / $maxAudience) * 100)) ?>%"></i>
            </div>
          <?php endforeach; ?>
          <?php if (!$rows) : ?><div class="empty-state">Sin datos.</div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($isOrganicTab) : ?>
<section class="social-panel social-posts-table-panel">
  <div class="social-panel-head">
    <div>
      <h2>Publicaciones realizadas</h2>
      <p>Contenido publicado en el rango seleccionado, con métricas disponibles desde Meta.</p>
    </div>
    <span><?= e(count($posts)) ?> posts</span>
  </div>
  <div class="social-post-months" data-social-post-months>
    <?php foreach ($postMonths as $monthIndex => $month) : ?>
      <?php $isFirstMonth = $monthIndex === array_key_first($postMonths); ?>
      <details class="social-post-month" <?= $isFirstMonth ? 'open' : '' ?>>
        <summary>
          <span>
            <strong><?= e($month['label']) ?></strong>
            <small><?= e(count($month['posts'])) ?> publicaciones · <?= e($fmt($month['engagement'])) ?> interacciones</small>
          </span>
          <span class="social-post-month-chevron" aria-hidden="true"></span>
        </summary>
        <div class="social-posts-table">
          <div class="social-posts-head">
            <span>Fecha</span>
            <span>Red</span>
            <span>Publicación</span>
            <span><?= e($exposureLabel) ?></span>
            <span>Interacciones</span>
            <span>Acciones</span>
          </div>
          <?php foreach ($month['posts'] as $post) : ?>
            <?php
              $engagement = (int) ($post['likes'] ?? 0) + (int) ($post['comments'] ?? 0) + (int) ($post['shares'] ?? 0) + (int) ($post['saves'] ?? 0);
              $postPlatform = (string) ($post['platform'] ?? '');
              $postExposure = (int) ($post[$exposureKey] ?? 0);
              $postReachAvailable = $postExposure > 0;
            ?>
            <article class="social-posts-row <?= e($platformTone[$postPlatform] ?? '') ?>">
              <time><?= e($post['post_date'] ?? '') ?><?= !empty($post['post_time']) ? ' · ' . e($post['post_time']) : '' ?></time>
              <span class="network-badge"><?= e($platformLabels[$postPlatform] ?? ucfirst($postPlatform)) ?></span>
              <div>
                <strong><?= e($post['title'] ?: 'Publicación sin título') ?></strong>
                <small><?= e($post['content_type'] ?? 'post') ?> · <?= e($fmt($post['likes'] ?? 0)) ?> me gusta · <?= e($fmt($post['comments'] ?? 0)) ?> comentarios</small>
              </div>
              <b title="<?= $postReachAvailable ? e($exposureDescription) : 'Meta no entregó esta métrica para la publicación' ?>"><?= $postReachAvailable ? e($fmt($postExposure)) : 'N/D' ?></b>
              <b><?= e($fmt($engagement)) ?></b>
              <div class="social-post-row-actions">
                <button class="button ghost mini" type="button" data-social-post-detail data-detail="<?= e($postDetail($post)) ?>">Detalles</button>
                <?php if (!empty($post['permalink'])) : ?>
                  <a class="button ghost mini" href="<?= e($post['permalink']) ?>" target="_blank" rel="noopener">Ver</a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
    <?php if (!$postMonths) : ?><div class="empty-state">No hay publicaciones registradas en este rango.</div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($isOrganicTab) : ?>
<section class="social-grid secondary">
  <article class="social-panel">
    <div class="social-panel-head">
      <div>
        <h2>Publicaciones destacadas</h2>
        <p>Ordenadas por interacciones del periodo.</p>
      </div>
    </div>
    <div class="social-post-list">
      <?php foreach ($topPosts as $post) : ?>
        <?php $engagement = (int) ($post['likes'] ?? 0) + (int) ($post['comments'] ?? 0) + (int) ($post['shares'] ?? 0) + (int) ($post['saves'] ?? 0); ?>
        <article class="social-post <?= e($platformTone[$post['platform']] ?? '') ?>">
          <span><?= e(strtoupper(substr((string) $post['platform'], 0, 2))) ?></span>
          <div>
            <strong><?= e($post['title'] ?: 'Publicación sin título') ?></strong>
            <?php $topExposure = (int) ($post[$exposureKey] ?? 0); ?>
            <small><?= e($post['post_date']) ?> · <?= e($post['content_type']) ?> · <?= $topExposure > 0 ? e($fmt($topExposure)) . ' ' . e(mb_strtolower($exposureLabel)) : e($exposureLabel) . ': N/D' ?></small>
          </div>
          <div class="social-post-engagement"><b><?= e($fmt($engagement)) ?></b><small>interacciones</small></div>
          <div class="social-post-actions">
            <button class="button ghost mini" type="button" data-social-post-detail data-detail="<?= e($postDetail($post)) ?>">Más detalles</button>
            <?php if (!empty($post['permalink'])) : ?>
              <a class="button ghost mini" href="<?= e($post['permalink']) ?>" target="_blank" rel="noopener">Ver</a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$topPosts) : ?><div class="empty-state">Aún no hay publicaciones medidas.</div><?php endif; ?>
    </div>
  </article>

  <article class="social-panel">
    <div class="social-panel-head">
      <div>
        <h2>Cuentas</h2>
        <p>Estado de conexión y sincronización.</p>
      </div>
    </div>
    <div class="social-account-list">
      <?php foreach ($accounts as $account) : ?>
        <div>
          <span class="<?= e($platformTone[$account['platform']] ?? '') ?>"><?= e(strtoupper(substr((string) $account['platform'], 0, 2))) ?></span>
          <div>
            <strong><?= e($account['account_name']) ?></strong>
            <small><?= e($account['username']) ?> · <?= (int) ($account['connected'] ?? 0) ? 'Conectada' : 'Pendiente' ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>
<?php endif; ?>
</div>
