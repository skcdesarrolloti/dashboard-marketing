<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class TikTokRepository
{
    private string $connectionsTable;
    private string $videosTable;

    public function __construct()
    {
        $this->connectionsTable = Database::table('marketing_tiktok_connections');
        $this->videosTable = Database::table('marketing_tiktok_videos');
        $this->ensureTables();
    }

    public function connection(): array
    {
        return Database::one("SELECT * FROM {$this->connectionsTable} ORDER BY connected DESC, updated_at DESC LIMIT 1");
    }

    public function saveConnection(array $data): array
    {
        $openId = $this->text($data['open_id'] ?? '', 120);
        if ($openId === '') {
            throw new RuntimeException('TikTok no devolvió un identificador de cuenta válido.');
        }

        $now = date('Y-m-d H:i:s');
        Database::execute("UPDATE {$this->connectionsTable} SET connected = 0, updated_at = ?", 's', [$now]);
        $payload = $this->connectionPayload($data, $now);
        $payload['open_id'] = $openId;
        $payload['connected'] = 1;
        $payload['last_error'] = '';
        $existing = Database::one("SELECT _ID, refresh_token_enc FROM {$this->connectionsTable} WHERE open_id = ? LIMIT 1", 's', [$openId]);
        if ($existing) {
            if ($payload['refresh_token_enc'] === '') {
                $payload['refresh_token_enc'] = (string) ($existing['refresh_token_enc'] ?? '');
            }
            Database::update($this->connectionsTable, $payload, (int) $existing['_ID']);
        } else {
            $payload['created_at'] = $now;
            Database::insert($this->connectionsTable, $payload);
        }
        return $this->connection();
    }

    public function updateTokens(string $openId, string $accessTokenEnc, string $refreshTokenEnc, string $expiresAt, string $refreshExpiresAt, string $scopes): void
    {
        $row = Database::one("SELECT _ID, refresh_token_enc FROM {$this->connectionsTable} WHERE open_id = ? LIMIT 1", 's', [$openId]);
        if (!$row) {
            throw new RuntimeException('La conexión de TikTok ya no existe. Vuelve a conectar la cuenta.');
        }
        Database::update($this->connectionsTable, [
            'access_token_enc' => $accessTokenEnc,
            'refresh_token_enc' => $refreshTokenEnc !== '' ? $refreshTokenEnc : (string) ($row['refresh_token_enc'] ?? ''),
            'token_expires_at' => $expiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
            'scopes' => $this->textArea($scopes),
            'connected' => 1,
            'last_error' => '',
            'updated_at' => date('Y-m-d H:i:s'),
        ], (int) $row['_ID']);
    }

    public function updateProfile(array $profile): void
    {
        $openId = $this->text($profile['open_id'] ?? '', 120);
        $row = Database::one("SELECT _ID FROM {$this->connectionsTable} WHERE open_id = ? LIMIT 1", 's', [$openId]);
        if (!$row) {
            return;
        }
        Database::update($this->connectionsTable, [
            'union_id' => $this->text($profile['union_id'] ?? '', 120),
            'display_name' => $this->text($profile['display_name'] ?? '', 220),
            'username' => $this->text($profile['username'] ?? '', 180),
            'avatar_url' => $this->text($profile['avatar_url'] ?? '', 1000),
            'profile_url' => $this->text($profile['profile_url'] ?? '', 1000),
            'bio_description' => $this->textArea($profile['bio_description'] ?? '', 4000),
            'follower_count' => max(0, (int) ($profile['follower_count'] ?? 0)),
            'following_count' => max(0, (int) ($profile['following_count'] ?? 0)),
            'likes_count' => max(0, (int) ($profile['likes_count'] ?? 0)),
            'video_count' => max(0, (int) ($profile['video_count'] ?? 0)),
            'connected' => 1,
            'last_error' => '',
            'updated_at' => date('Y-m-d H:i:s'),
        ], (int) $row['_ID']);
    }

    public function upsertVideos(string $openId, array $videos): int
    {
        $now = date('Y-m-d H:i:s');
        $count = 0;
        foreach ($videos as $video) {
            $videoId = $this->text($video['video_id'] ?? '', 80);
            if ($videoId === '') {
                continue;
            }
            $payload = [
                'video_id' => $videoId,
                'open_id' => $this->text($openId, 120),
                'title' => $this->text($video['title'] ?? '', 500),
                'description' => $this->textArea($video['description'] ?? '', 12000),
                'published_at' => $this->dateTime($video['published_at'] ?? ''),
                'cover_url' => $this->text($video['cover_url'] ?? '', 1200),
                'permalink' => $this->text($video['permalink'] ?? '', 1000),
                'duration_seconds' => max(0, (int) ($video['duration_seconds'] ?? 0)),
                'views' => max(0, (int) ($video['views'] ?? 0)),
                'likes' => max(0, (int) ($video['likes'] ?? 0)),
                'comments' => max(0, (int) ($video['comments'] ?? 0)),
                'shares' => max(0, (int) ($video['shares'] ?? 0)),
                'metrics_json' => json_encode($video['metrics'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'updated_at' => $now,
            ];
            $existing = Database::one("SELECT _ID FROM {$this->videosTable} WHERE video_id = ? LIMIT 1", 's', [$videoId]);
            if ($existing) {
                Database::update($this->videosTable, $payload, (int) $existing['_ID']);
            } else {
                $payload['created_at'] = $now;
                Database::insert($this->videosTable, $payload);
            }
            $count++;
        }
        return $count;
    }

    public function markSynced(): void
    {
        Database::execute(
            "UPDATE {$this->connectionsTable} SET last_sync = ?, last_error = '', updated_at = ? WHERE connected = 1",
            'ss',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
    }

    public function disconnect(): void
    {
        Database::execute(
            "UPDATE {$this->connectionsTable} SET connected = 0, access_token_enc = '', refresh_token_enc = '', token_expires_at = NULL, refresh_expires_at = NULL, last_error = '', updated_at = ?",
            's',
            [date('Y-m-d H:i:s')]
        );
    }

    public function dashboard(string $from, string $to): array
    {
        $connection = $this->connection();
        $openId = (string) ($connection['open_id'] ?? '');
        $days = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
        $previousTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $previousFrom = date('Y-m-d', strtotime($previousTo . ' -' . ($days - 1) . ' days'));
        $videos = $openId === '' ? [] : Database::rows(
            "SELECT * FROM {$this->videosTable} WHERE open_id = ? AND DATE(published_at) BETWEEN ? AND ? ORDER BY published_at DESC, _ID DESC",
            'sss',
            [$openId, $from, $to]
        );
        $series = $openId === '' ? [] : Database::rows(
            "SELECT DATE(published_at) metric_date, COUNT(*) videos_published, COALESCE(SUM(views),0) views, COALESCE(SUM(likes),0) likes, COALESCE(SUM(comments),0) comments, COALESCE(SUM(shares),0) shares, COALESCE(SUM(likes + comments + shares),0) interactions FROM {$this->videosTable} WHERE open_id = ? AND DATE(published_at) BETWEEN ? AND ? GROUP BY DATE(published_at) ORDER BY metric_date ASC",
            'sss',
            [$openId, $from, $to]
        );
        $topVideos = $videos;
        usort($topVideos, static fn (array $a, array $b): int => (int) $b['views'] <=> (int) $a['views']);
        return [
            'connection' => $connection,
            'summary' => $this->summary($openId, $from, $to),
            'previous' => $this->summary($openId, $previousFrom, $previousTo),
            'series' => $series,
            'videos' => $videos,
            'topVideos' => array_slice($topVideos, 0, 8),
            'period' => [
                'current' => ['from' => $from, 'to' => $to],
                'previous' => ['from' => $previousFrom, 'to' => $previousTo],
                'comparison_ready' => $openId !== '' && $this->videoCount($openId, $previousFrom, $previousTo) > 0,
                'requested_days' => $days,
                'covered_days' => count($series),
                'available_from' => $series ? (string) $series[0]['metric_date'] : '',
                'available_to' => $series ? (string) $series[count($series) - 1]['metric_date'] : '',
            ],
            'hasLiveData' => $openId !== '' && ((int) ($connection['connected'] ?? 0) === 1 || $videos !== []),
        ];
    }

    private function summary(string $openId, string $from, string $to): array
    {
        if ($openId === '') {
            return $this->emptySummary();
        }
        return array_merge($this->emptySummary(), Database::one(
            "SELECT COUNT(*) videos, COALESCE(SUM(views),0) views, COALESCE(SUM(likes),0) likes, COALESCE(SUM(comments),0) comments, COALESCE(SUM(shares),0) shares, COALESCE(SUM(likes + comments + shares),0) interactions FROM {$this->videosTable} WHERE open_id = ? AND DATE(published_at) BETWEEN ? AND ?",
            'sss',
            [$openId, $from, $to]
        ));
    }

    private function videoCount(string $openId, string $from, string $to): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM {$this->videosTable} WHERE open_id = ? AND DATE(published_at) BETWEEN ? AND ?", 'sss', [$openId, $from, $to]);
    }

    private function emptySummary(): array
    {
        return ['videos' => 0, 'views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'interactions' => 0];
    }

    private function connectionPayload(array $data, string $now): array
    {
        return [
            'union_id' => $this->text($data['union_id'] ?? '', 120),
            'display_name' => $this->text($data['display_name'] ?? '', 220),
            'username' => $this->text($data['username'] ?? '', 180),
            'avatar_url' => $this->text($data['avatar_url'] ?? '', 1000),
            'profile_url' => $this->text($data['profile_url'] ?? '', 1000),
            'bio_description' => $this->textArea($data['bio_description'] ?? '', 4000),
            'follower_count' => max(0, (int) ($data['follower_count'] ?? 0)),
            'following_count' => max(0, (int) ($data['following_count'] ?? 0)),
            'likes_count' => max(0, (int) ($data['likes_count'] ?? 0)),
            'video_count' => max(0, (int) ($data['video_count'] ?? 0)),
            'access_token_enc' => $this->textArea($data['access_token_enc'] ?? ''),
            'refresh_token_enc' => $this->textArea($data['refresh_token_enc'] ?? ''),
            'token_expires_at' => $this->dateTime($data['token_expires_at'] ?? ''),
            'refresh_expires_at' => $this->dateTime($data['refresh_expires_at'] ?? ''),
            'scopes' => $this->textArea($data['scopes'] ?? ''),
            'updated_at' => $now,
        ];
    }

    private function ensureTables(): void
    {
        if (!Database::tableExists($this->connectionsTable)) {
            Database::execute("CREATE TABLE {$this->connectionsTable} (
                _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, open_id VARCHAR(120) NOT NULL, union_id VARCHAR(120) NOT NULL DEFAULT '', display_name VARCHAR(220) NOT NULL DEFAULT '', username VARCHAR(180) NOT NULL DEFAULT '', avatar_url VARCHAR(1000) NOT NULL DEFAULT '', profile_url VARCHAR(1000) NOT NULL DEFAULT '', bio_description TEXT NULL, follower_count BIGINT UNSIGNED NOT NULL DEFAULT 0, following_count BIGINT UNSIGNED NOT NULL DEFAULT 0, likes_count BIGINT UNSIGNED NOT NULL DEFAULT 0, video_count INT UNSIGNED NOT NULL DEFAULT 0, access_token_enc MEDIUMTEXT NULL, refresh_token_enc MEDIUMTEXT NULL, token_expires_at DATETIME NULL, refresh_expires_at DATETIME NULL, scopes TEXT NULL, connected TINYINT(1) NOT NULL DEFAULT 0, last_sync DATETIME NULL, last_error TEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (_ID), UNIQUE KEY open_id_unique (open_id), KEY connected (connected)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        if (!Database::tableExists($this->videosTable)) {
            Database::execute("CREATE TABLE {$this->videosTable} (
                _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, video_id VARCHAR(80) NOT NULL, open_id VARCHAR(120) NOT NULL, title VARCHAR(500) NOT NULL DEFAULT '', description MEDIUMTEXT NULL, published_at DATETIME NULL, cover_url VARCHAR(1200) NOT NULL DEFAULT '', permalink VARCHAR(1000) NOT NULL DEFAULT '', duration_seconds INT UNSIGNED NOT NULL DEFAULT 0, views BIGINT UNSIGNED NOT NULL DEFAULT 0, likes BIGINT UNSIGNED NOT NULL DEFAULT 0, comments BIGINT UNSIGNED NOT NULL DEFAULT 0, shares BIGINT UNSIGNED NOT NULL DEFAULT 0, metrics_json MEDIUMTEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (_ID), UNIQUE KEY video_unique (video_id), KEY account_published (open_id, published_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    private function text(mixed $value, int $length): string { return mb_substr(trim((string) $value), 0, $length); }
    private function textArea(mixed $value, int $length = 65535): string { return mb_substr(trim((string) $value), 0, $length); }
    private function dateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $time = strtotime($value);
        return $time === false ? null : date('Y-m-d H:i:s', $time);
    }
}
