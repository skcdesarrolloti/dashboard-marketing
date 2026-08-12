<?php

declare(strict_types=1);

namespace App;

use finfo;
use RuntimeException;

final class BrandAssetService
{
    private const CATEGORIES = [
        'social' => 'Redes sociales',
        'branding' => 'Branding',
        'commercial' => 'Comercial',
        'institutional' => 'Institucional',
        'web' => 'Web y digital',
        'physical' => 'Pieza física',
        'campaign' => 'Campaña',
    ];

    private const STATUSES = [
        'designed' => 'Diseñada',
        'in_review' => 'En revisión',
        'approved' => 'Aprobada',
        'published' => 'Publicada',
        'implemented' => 'Implementada',
        'adjustment' => 'Por ajustar',
    ];

    private const CHANNELS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'web' => 'Sitio web',
        'email' => 'Correo',
        'print' => 'Impreso',
        'internal' => 'Comunicación interna',
    ];

    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf',
        'mp4', 'webm', 'mov', 'ppt', 'pptx', 'psd', 'ai', 'eps',
    ];

    private const MAX_FILE_SIZE = 52428800;

    private string $table;
    private string $linksTable;
    private string $socialPostsTable;

    public function __construct()
    {
        BrandAssetSchema::ensure();
        $this->table = Database::table('marketing_brand_assets');
        $this->linksTable = Database::table('marketing_brand_asset_posts');
        $this->socialPostsTable = Database::table('marketing_social_posts');
    }

    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public static function channels(): array
    {
        return self::CHANNELS;
    }

    public function dashboardData(array $filters): array
    {
        [$where, $types, $params] = $this->where($filters);
        $assets = Database::rows(
            "SELECT *,
                    (views_count + reach_count + (clicks_count * 5) + (leads_count * 50)
                     + (referrals_count * 75) + (acquisitions_count * 100)) AS impact_score
               FROM {$this->table}
              {$where}
              ORDER BY active DESC, creation_date DESC, id DESC
              LIMIT 250",
            $types,
            $params
        );

        foreach ($assets as &$asset) {
            $asset['channels'] = $this->decodeChannels((string) ($asset['channels_json'] ?? ''));
        }
        unset($asset);
        $assets = $this->attachLinkedPosts($assets);

        $summary = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'approved') AS approved,
                    SUM(status = 'implemented') AS implemented,
                    SUM(status IN ('designed', 'in_review', 'adjustment')) AS pending,
                    ROUND(AVG(NULLIF(brand_compliance, 0)), 0) AS visual_consistency
               FROM {$this->table}
              WHERE active = 1"
        );
        $total = (int) ($summary['total'] ?? 0);
        $implemented = (int) ($summary['implemented'] ?? 0);
        $summary['total'] = $total;
        $summary['approved'] = (int) ($summary['approved'] ?? 0);
        $summary['implemented'] = $implemented;
        $summary['pending'] = (int) ($summary['pending'] ?? 0);
        $summary['visual_consistency'] = (int) ($summary['visual_consistency'] ?? 0);
        $summary['branding_progress'] = $total > 0 ? (int) round(($implemented / $total) * 100) : 0;

        $channelCounts = array_fill_keys(array_keys(self::CHANNELS), 0);
        foreach (Database::rows("SELECT channels_json FROM {$this->table} WHERE active = 1") as $row) {
            foreach ($this->decodeChannels((string) ($row['channels_json'] ?? '')) as $channel) {
                $channelCounts[$channel] = ($channelCounts[$channel] ?? 0) + 1;
            }
        }
        arsort($channelCounts);

        $categoryCounts = [];
        foreach (Database::rows(
            "SELECT category, COUNT(*) AS total FROM {$this->table} WHERE active = 1 GROUP BY category ORDER BY total DESC"
        ) as $row) {
            $categoryCounts[(string) $row['category']] = (int) $row['total'];
        }

        $topAssets = Database::rows(
            "SELECT id, title, category, reach_count, clicks_count, leads_count, acquisitions_count,
                    (views_count + reach_count + (clicks_count * 5) + (leads_count * 50)
                     + (referrals_count * 75) + (acquisitions_count * 100)) AS impact_score
               FROM {$this->table}
              WHERE active = 1
              ORDER BY impact_score DESC, implementation_date DESC, id DESC
              LIMIT 5"
        );

        return [
            'assets' => $assets,
            'summary' => $summary,
            'channelCounts' => $channelCounts,
            'categoryCounts' => $categoryCounts,
            'topAssets' => $topAssets,
        ];
    }

    public function socialPosts(): array
    {
        $rows = Database::rows(
            "SELECT p._ID, p.post_date, p.post_time, p.platform, p.external_id, p.content_type,
                    p.title, p.permalink, p.thumbnail_url, p.reach, p.impressions, p.likes,
                    p.comments, p.shares, p.saves, p.metrics_json, p.updated_at,
                    l.asset_id AS linked_asset_id, a.title AS linked_asset_title
               FROM {$this->socialPostsTable} p
               LEFT JOIN {$this->linksTable} l ON l.social_post_id = p._ID
               LEFT JOIN {$this->table} a ON a.id = l.asset_id
              WHERE p.source <> 'demo' AND p.external_id <> ''
              ORDER BY p.post_date DESC, p.post_time DESC, p._ID DESC
              LIMIT 1000"
        );

        foreach ($rows as &$row) {
            $row = $this->decorateSocialPost($row);
        }
        unset($row);

        return $rows;
    }

    public function replacePostLinks(int $assetId, array $postIds, int $userId): int
    {
        if ($assetId <= 0 || $this->find($assetId) === []) {
            throw new RuntimeException('La pieza seleccionada no existe.');
        }

        $postIds = array_values(array_unique(array_filter(
            array_map('intval', $postIds),
            static fn (int $id): bool => $id > 0
        )));
        if (count($postIds) > 1000) {
            throw new RuntimeException('Selecciona máximo 1.000 publicaciones por pieza.');
        }

        if ($postIds !== []) {
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $validIds = array_map(
                'intval',
                array_column(
                    Database::rows(
                        "SELECT _ID FROM {$this->socialPostsTable}
                          WHERE _ID IN ({$placeholders}) AND source <> 'demo' AND external_id <> ''",
                        str_repeat('i', count($postIds)),
                        $postIds
                    ),
                    '_ID'
                )
            );
            sort($validIds);
            $expectedIds = $postIds;
            sort($expectedIds);
            if ($validIds !== $expectedIds) {
                throw new RuntimeException('Una de las publicaciones seleccionadas ya no está disponible.');
            }

            $conflict = Database::one(
                "SELECT a.title
                   FROM {$this->linksTable} l
                   JOIN {$this->table} a ON a.id = l.asset_id
                  WHERE l.social_post_id IN ({$placeholders}) AND l.asset_id <> ?
                  LIMIT 1",
                str_repeat('i', count($postIds)) . 'i',
                [...$postIds, $assetId]
            );
            if ($conflict !== []) {
                throw new RuntimeException('Una publicación seleccionada ya está vinculada a “' . (string) $conflict['title'] . '”.');
            }
        }

        Database::beginTransaction();
        try {
            if (!Database::execute("DELETE FROM {$this->linksTable} WHERE asset_id = ?", 'i', [$assetId])) {
                throw new RuntimeException('No fue posible actualizar los vínculos de la pieza.');
            }
            $now = date('Y-m-d H:i:s');
            foreach ($postIds as $postId) {
                if (!Database::execute(
                    "INSERT INTO {$this->linksTable} (asset_id, social_post_id, created_by, created_at)
                     VALUES (?, ?, ?, ?)",
                    'iiis',
                    [$assetId, $postId, $userId ?: null, $now]
                )) {
                    throw new RuntimeException('No fue posible guardar uno de los vínculos.');
                }
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        return count($postIds);
    }

    public function find(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $asset = Database::one("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1", 'i', [$id]);
        if ($asset !== []) {
            $asset['channels'] = $this->decodeChannels((string) ($asset['channels_json'] ?? ''));
        }
        return $asset;
    }

    public function save(array $input, array $files, int $userId): int
    {
        $id = max(0, (int) ($input['id'] ?? 0));
        $existing = $id > 0 ? $this->find($id) : [];
        if ($id > 0 && $existing === []) {
            throw new RuntimeException('La pieza que intentas editar ya no existe.');
        }

        $title = $this->requiredText($input, 'title', 'Escribe el nombre de la pieza.', 255);
        $category = $this->allowedValue((string) ($input['category'] ?? ''), self::CATEGORIES, 'Selecciona una categoría válida.');
        $status = $this->allowedValue((string) ($input['status'] ?? ''), self::STATUSES, 'Selecciona un estado válido.');
        $channels = array_values(array_intersect(array_keys(self::CHANNELS), array_map('strval', (array) ($input['channels'] ?? []))));
        if ($channels === []) {
            throw new RuntimeException('Selecciona al menos un canal de uso.');
        }

        $creationDate = $this->requiredDate((string) ($input['creation_date'] ?? ''), 'La fecha de creación no es válida.');
        $approvalDate = $this->optionalDate((string) ($input['approval_date'] ?? ''), 'La fecha de aprobación no es válida.');
        $publicationDate = $this->optionalDate((string) ($input['publication_date'] ?? ''), 'La fecha de publicación no es válida.');
        $implementationDate = $this->optionalDate((string) ($input['implementation_date'] ?? ''), 'La fecha de implementación no es válida.');

        $assetCode = $this->nullableText($input, 'asset_code', 80);
        if ($assetCode !== null) {
            $duplicate = (int) Database::value(
                "SELECT COUNT(*) FROM {$this->table} WHERE asset_code = ? AND id <> ?",
                'si',
                [$assetCode, $id]
            );
            if ($duplicate > 0) {
                throw new RuntimeException('Ya existe otra pieza con ese código.');
            }
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'title' => $title,
            'asset_code' => $assetCode,
            'description' => $this->nullableText($input, 'description', 3000),
            'category' => $category,
            'status' => $status,
            'channels_json' => json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'campaign_name' => $this->nullableText($input, 'campaign_name', 191),
            'designer_name' => $this->nullableText($input, 'designer_name', 191),
            'approver_name' => $this->nullableText($input, 'approver_name', 191),
            'implementer_name' => $this->nullableText($input, 'implementer_name', 191),
            'creation_date' => $creationDate,
            'approval_date' => $approvalDate,
            'publication_date' => $publicationDate,
            'implementation_date' => $implementationDate,
            'brand_compliance' => $this->boundedInt($input, 'brand_compliance', 0, 100),
            'views_count' => $this->boundedInt($input, 'views_count', 0),
            'reach_count' => $this->boundedInt($input, 'reach_count', 0),
            'clicks_count' => $this->boundedInt($input, 'clicks_count', 0),
            'leads_count' => $this->boundedInt($input, 'leads_count', 0),
            'referrals_count' => $this->boundedInt($input, 'referrals_count', 0),
            'acquisitions_count' => $this->boundedInt($input, 'acquisitions_count', 0),
            'observations' => $this->nullableText($input, 'observations', 5000),
            'updated_by' => $userId ?: null,
            'updated_at' => $now,
        ];

        $upload = $this->storeUpload((array) ($files['asset_file'] ?? []));
        if ($id === 0 && $upload === null) {
            throw new RuntimeException('Adjunta el arte final de la pieza.');
        }
        if ($upload !== null) {
            $data = array_merge($data, $upload);
        }

        if ($id === 0) {
            $data['active'] = 1;
            $data['created_by'] = $userId ?: null;
            $data['created_at'] = $now;
            $columns = array_keys($data);
            $id = Database::execute(
                "INSERT INTO {$this->table} (`" . implode('`,`', $columns) . '`) VALUES ('
                    . implode(',', array_fill(0, count($columns), '?')) . ')',
                str_repeat('s', count($columns)),
                array_values($data)
            ) ? (int) Database::connection()->insert_id : 0;
            if ($id <= 0) {
                $this->removeUpload($upload);
                throw new RuntimeException('No fue posible guardar la pieza.');
            }
            return $id;
        }

        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = "`{$column}` = ?";
            $params[] = $value;
        }
        $params[] = $id;
        $ok = Database::execute(
            "UPDATE {$this->table} SET " . implode(', ', $sets) . ' WHERE id = ? LIMIT 1',
            str_repeat('s', count($data)) . 'i',
            $params
        );
        if (!$ok) {
            $this->removeUpload($upload);
            throw new RuntimeException('No fue posible actualizar la pieza.');
        }
        if ($upload !== null && !empty($existing['storage_path'])) {
            $this->removeStoredPath((string) $existing['storage_path']);
        }
        return $id;
    }

    public function setActive(int $id, bool $active, int $userId): void
    {
        if ($id <= 0 || $this->find($id) === []) {
            throw new RuntimeException('La pieza seleccionada no existe.');
        }
        if (!Database::execute(
            "UPDATE {$this->table} SET active = ?, updated_by = ?, updated_at = ? WHERE id = ? LIMIT 1",
            'iisi',
            [$active ? 1 : 0, $userId, date('Y-m-d H:i:s'), $id]
        )) {
            throw new RuntimeException('No fue posible actualizar la pieza.');
        }
    }

    public function streamFile(int $id, bool $download = false): never
    {
        $asset = $this->find($id);
        $stored = (string) ($asset['storage_path'] ?? '');
        $root = realpath(dirname(__DIR__) . '/storage/branding-assets');
        $path = $stored !== '' ? realpath(dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $stored), '/')) : false;
        if ($asset === [] || $root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }

        $name = str_replace(["\r", "\n", '"'], '', (string) ($asset['original_name'] ?? 'pieza'));
        header('Content-Type: ' . ((string) ($asset['mime_type'] ?? '') ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        readfile($path);
        exit;
    }

    private function attachLinkedPosts(array $assets): array
    {
        if ($assets === []) {
            return [];
        }

        $assetIndexes = [];
        $assetIds = [];
        foreach ($assets as $index => &$asset) {
            $assetId = (int) ($asset['id'] ?? 0);
            $assetIndexes[$assetId] = $index;
            $assetIds[] = $assetId;
            $asset['linked_posts'] = [];
            $asset['social_kpis'] = [
                'posts' => 0,
                'reach' => 0,
                'views' => 0,
                'interactions' => 0,
                'clicks' => 0,
                'last_sync_at' => '',
            ];
        }
        unset($asset);

        $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
        $rows = Database::rows(
            "SELECT l.asset_id, p._ID, p.post_date, p.post_time, p.platform, p.external_id,
                    p.content_type, p.title, p.permalink, p.thumbnail_url, p.reach, p.impressions,
                    p.likes, p.comments, p.shares, p.saves, p.metrics_json, p.updated_at
               FROM {$this->linksTable} l
               JOIN {$this->socialPostsTable} p ON p._ID = l.social_post_id
              WHERE l.asset_id IN ({$placeholders})
              ORDER BY p.post_date DESC, p.post_time DESC, p._ID DESC",
            str_repeat('i', count($assetIds)),
            $assetIds
        );

        foreach ($rows as $row) {
            $assetId = (int) ($row['asset_id'] ?? 0);
            if (!isset($assetIndexes[$assetId])) {
                continue;
            }
            $index = $assetIndexes[$assetId];
            $post = $this->decorateSocialPost($row);
            $assets[$index]['linked_posts'][] = $post;
            $assets[$index]['social_kpis']['posts']++;
            foreach (['reach', 'views', 'interactions', 'clicks'] as $metric) {
                $assets[$index]['social_kpis'][$metric] += (int) ($post[$metric] ?? 0);
            }
            $lastSync = (string) ($post['updated_at'] ?? '');
            if ($lastSync > (string) $assets[$index]['social_kpis']['last_sync_at']) {
                $assets[$index]['social_kpis']['last_sync_at'] = $lastSync;
            }
        }

        return $assets;
    }

    private function decorateSocialPost(array $row): array
    {
        $metrics = json_decode((string) ($row['metrics_json'] ?? ''), true);
        $metrics = is_array($metrics) ? $metrics : [];
        $baseInteractions = (int) ($row['likes'] ?? 0)
            + (int) ($row['comments'] ?? 0)
            + (int) ($row['shares'] ?? 0)
            + (int) ($row['saves'] ?? 0);

        $row['views'] = max(
            (int) ($row['impressions'] ?? 0),
            $this->metricInt($metrics, ['views', 'post_media_view', 'post_video_views'])
        );
        $row['interactions'] = max(
            $baseInteractions,
            $this->metricInt($metrics, ['total_interactions'])
        );
        $row['clicks'] = $this->metricInt($metrics, ['post_clicks', 'clicks', 'website_clicks']);
        unset($row['metrics_json']);

        return $row;
    }

    private function metricInt(array $metrics, array $keys): int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $metrics)) {
                continue;
            }
            $value = $metrics[$key];
            if (is_array($value)) {
                $value = $value['value'] ?? $value['total_value']['value'] ?? 0;
            }
            return max(0, (int) $value);
        }
        return 0;
    }

    private function where(array $filters): array
    {
        $clauses = [];
        $types = '';
        $params = [];
        $active = (string) ($filters['active'] ?? '1');
        if ($active !== 'all') {
            $clauses[] = 'active = ?';
            $types .= 'i';
            $params[] = $active === '0' ? 0 : 1;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $clauses[] = '(title LIKE ? OR asset_code LIKE ? OR campaign_name LIKE ? OR designer_name LIKE ?)';
            $types .= 'ssss';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        foreach (['category' => self::CATEGORIES, 'status' => self::STATUSES] as $key => $allowed) {
            $value = trim((string) ($filters[$key] ?? ''));
            if (isset($allowed[$value])) {
                $clauses[] = "{$key} = ?";
                $types .= 's';
                $params[] = $value;
            }
        }
        $channel = trim((string) ($filters['channel'] ?? ''));
        if (isset(self::CHANNELS[$channel])) {
            $clauses[] = 'channels_json LIKE ?';
            $types .= 's';
            $params[] = '%"' . $channel . '"%';
        }
        foreach (['from' => 'creation_date >= ?', 'to' => 'creation_date <= ?'] as $key => $expression) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '' && $this->validDate($value)) {
                $clauses[] = $expression;
                $types .= 's';
                $params[] = $value;
            }
        }
        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $types, $params];
    }

    private function storeUpload(array $file): ?array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('La carga del archivo no se completó. Intenta nuevamente.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('El archivo debe pesar máximo 50 MB.');
        }
        $original = basename((string) ($file['name'] ?? 'pieza'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Formato no permitido. Usa imagen, PDF, video o archivo de diseño compatible.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
        $directory = dirname(__DIR__) . '/storage/branding-assets/' . date('Y/m');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar la carpeta de archivos.');
        }
        $filename = bin2hex(random_bytes(18)) . '.' . $extension;
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            throw new RuntimeException('No fue posible guardar el archivo.');
        }
        return [
            'storage_path' => str_replace('\\', '/', substr($destination, strlen(dirname(__DIR__)) + 1)),
            'original_name' => substr($original, 0, 255),
            'mime_type' => substr($mime, 0, 120),
            'file_size' => $size,
        ];
    }

    private function decodeChannels(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_values(array_intersect(array_keys(self::CHANNELS), array_map('strval', $decoded))) : [];
    }

    private function requiredText(array $input, string $key, string $message, int $max): string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException($message);
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function nullableText(array $input, string $key, int $max): ?string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function allowedValue(string $value, array $allowed, string $message): string
    {
        if (!isset($allowed[$value])) {
            throw new RuntimeException($message);
        }
        return $value;
    }

    private function requiredDate(string $value, string $message): string
    {
        if (!$this->validDate($value)) {
            throw new RuntimeException($message);
        }
        return $value;
    }

    private function optionalDate(string $value, string $message): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return $this->requiredDate($value, $message);
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function boundedInt(array $input, string $key, int $minimum, int $maximum = PHP_INT_MAX): int
    {
        $raw = trim((string) ($input[$key] ?? '0'));
        if ($raw === '') {
            return 0;
        }
        if (!preg_match('/^\d+$/', $raw)) {
            throw new RuntimeException('Los indicadores deben ser números enteros positivos.');
        }
        return min($maximum, max($minimum, (int) $raw));
    }

    private function removeUpload(?array $upload): void
    {
        if ($upload !== null && !empty($upload['storage_path'])) {
            $this->removeStoredPath((string) $upload['storage_path']);
        }
    }

    private function removeStoredPath(string $stored): void
    {
        $root = realpath(dirname(__DIR__) . '/storage/branding-assets');
        $path = realpath(dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $stored), '/'));
        if ($root !== false && $path !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
        }
    }
}
