<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class TrackerRepository
{
    public function register(array $payload): int
    {
        $table = Database::table('jet_cct_tracker_logs');
        if (!Database::tableExists($table)) {
            throw new RuntimeException('La tabla de tracking no existe.');
        }

        $slug = $this->text($payload['slug'] ?? $payload['camp_slug'] ?? '', 120);
        $utmCampaign = $this->text($payload['utm_campaign'] ?? '', 120);
        if ($slug === '' || $slug === 'general') {
            $slug = $utmCampaign !== '' ? $utmCampaign : ($slug === '' ? 'general' : $slug);
        }

        $rawSource = $this->text($payload['utm_source'] ?? '', 120);
        $rawMedium = $this->text($payload['utm_medium'] ?? '', 120);
        if ($rawSource === '') {
            $utmSource = $slug === 'inmueble_view' ? 'Inmuebles' : 'Directo';
            $utmMedium = $rawMedium !== '' ? $rawMedium : ($slug === 'inmueble_view' ? 'Pagina Web' : 'Navegador');
        } else {
            $utmSource = $rawSource;
            $utmMedium = $rawMedium;
        }

        $ip = $this->clientIp();
        $geo = $this->geo($ip);
        $data = [
            'camp_slug' => $slug,
            'user_id' => max(0, (int) ($payload['user_id'] ?? 0)),
            'ip_address' => $ip,
            'user_agent' => $this->text($_SERVER['HTTP_USER_AGENT'] ?? '', 250),
            'page_url' => $this->url($payload['url'] ?? $payload['page_url'] ?? '', 800),
            'referrer' => $this->url($payload['referrer'] ?? '', 800),
            'country' => $geo['country'] ?? '',
            'city' => $geo['city'] ?? '',
            'event_type' => $this->text($payload['event_type'] ?? 'view', 40) ?: 'view',
            'object_id' => max(0, (int) ($payload['object_id'] ?? 0)),
            'object_ref' => $this->text($payload['object_ref'] ?? '', 160),
            'event_label' => $this->text($payload['event_label'] ?? '', 160),
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'fecha_visita' => date('Y-m-d H:i:s'),
        ];

        $columns = Database::columns($table);
        $insert = array_intersect_key($data, $columns);
        $id = Database::insert($table, $insert);
        $this->ensureCampaign($slug);

        return $id;
    }

    private function ensureCampaign(string $slug): void
    {
        if (in_array($slug, ['general', 'inmueble_view', 'event_click'], true)) {
            return;
        }

        $table = Database::table('jet_cct_tracker_camps');
        if (!Database::tableExists($table)) {
            return;
        }

        $exists = Database::value("SELECT _ID FROM {$table} WHERE slug = ? LIMIT 1", 's', [$slug]);
        if ($exists) {
            return;
        }

        $columns = Database::columns($table);
        $data = array_intersect_key([
            'slug' => $slug,
            'nombre' => ucfirst(str_replace(['-', '_'], ' ', $slug)),
        ], $columns);

        if ($data !== []) {
            Database::insert($table, $data);
        }
    }

    private function clientIp(): string
    {
        $candidates = [];
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            foreach (explode(',', (string) ($_SERVER[$key] ?? '')) as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $candidates[] = $value;
                }
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '';
    }

    private function geo(string $ip): array
    {
        if (!app_config('tracking.geo_lookup', true)
            || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['country' => '', 'city' => ''];
        }

        $cache = $this->geoCache();
        $key = sha1($ip);
        if (isset($cache[$key]) && (int) ($cache[$key]['expires'] ?? 0) > time()) {
            return [
                'country' => (string) ($cache[$key]['country'] ?? ''),
                'city' => (string) ($cache[$key]['city'] ?? ''),
            ];
        }

        $result = ['country' => '', 'city' => ''];
        $context = stream_context_create(['http' => ['timeout' => 0.8, 'ignore_errors' => true]]);
        $json = @file_get_contents('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,city', false, $context);
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && ($decoded['status'] ?? '') === 'success') {
                $result = [
                    'country' => $this->text($decoded['country'] ?? '', 80),
                    'city' => $this->text($decoded['city'] ?? '', 80),
                ];
            }
        }

        $cache[$key] = $result + ['expires' => time() + ($result['country'] !== '' ? 604800 : 3600)];
        $this->saveGeoCache($cache);

        return $result;
    }

    private function geoCache(): array
    {
        $file = dirname(__DIR__) . '/storage/cache/gda_geo.json';
        if (!is_readable($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveGeoCache(array $cache): void
    {
        $dir = dirname(__DIR__) . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($dir . '/gda_geo.json', json_encode($cache, JSON_UNESCAPED_SLASHES));
    }

    private function text(mixed $value, int $max): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
        return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
    }

    private function url(mixed $value, int $max): string
    {
        $url = $this->text($value, $max);
        return preg_match('#^https?://#i', $url) ? $url : '';
    }
}
