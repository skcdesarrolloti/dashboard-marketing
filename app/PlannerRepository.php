<?php

declare(strict_types=1);

namespace App;

final class PlannerRepository
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::table('marketing_planner_posts');
        $this->ensureTable();
    }

    public function all(array $filters = []): array
    {
        [$where, $types, $params] = $this->where($filters);
        return Database::rows(
            "SELECT _ID AS id, post_date AS date, post_time AS time, status, channel, content_type AS type,
                    campaign, title, owner, post_copy AS copy, asset_url, post_url, notes,
                    reach, views, interactions, created_at, updated_at
               FROM {$this->table}
              WHERE {$where}
              ORDER BY post_date ASC, post_time ASC, _ID ASC",
            $types,
            $params
        );
    }

    public function forRange(string $from, string $to, array $filters = []): array
    {
        $filters['from'] = $from;
        $filters['to'] = $to;
        return $this->all($filters);
    }

    public function find(int $id): array
    {
        return Database::one(
            "SELECT _ID AS id, post_date AS date, post_time AS time, status, channel, content_type AS type,
                    campaign, title, owner, post_copy AS copy, asset_url, post_url, notes,
                    reach, views, interactions, created_at, updated_at
               FROM {$this->table}
              WHERE _ID = ?
              LIMIT 1",
            'i',
            [$id]
        );
    }

    public function save(array $input): int
    {
        $id = max(0, (int) ($input['id'] ?? 0));
        $data = [
            'post_date' => $this->date($input['date'] ?? '', date('Y-m-d')),
            'post_time' => $this->time($input['time'] ?? ''),
            'status' => $this->choice($input['status'] ?? 'planned', ['idea', 'planned', 'scheduled', 'published', 'cancelled'], 'planned'),
            'channel' => $this->choice($input['channel'] ?? 'instagram', ['instagram', 'facebook', 'whatsapp', 'email', 'web', 'tiktok', 'linkedin', 'google'], 'instagram'),
            'content_type' => $this->choice($input['type'] ?? 'post', ['post', 'reel', 'story', 'ad', 'email', 'blog', 'landing'], 'post'),
            'campaign' => $this->text($input['campaign'] ?? '', 120),
            'title' => $this->text($input['title'] ?? '', 160),
            'owner' => $this->text($input['owner'] ?? '', 100),
            'post_copy' => $this->textArea($input['copy'] ?? '', 3000),
            'asset_url' => $this->text($input['asset_url'] ?? '', 800),
            'post_url' => $this->text($input['post_url'] ?? '', 800),
            'notes' => $this->textArea($input['notes'] ?? '', 2000),
            'reach' => max(0, (int) ($input['reach'] ?? 0)),
            'views' => max(0, (int) ($input['views'] ?? 0)),
            'interactions' => max(0, (int) ($input['interactions'] ?? 0)),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0 && $this->find($id) !== []) {
            $sets = [];
            foreach (array_keys($data) as $column) {
                $sets[] = "`{$column}` = ?";
            }
            $params = array_values($data);
            $params[] = $id;
            Database::execute(
                "UPDATE {$this->table} SET " . implode(', ', $sets) . ' WHERE _ID = ? LIMIT 1',
                str_repeat('s', count($data)) . 'i',
                $params
            );
            return $id;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert($this->table, $data);
    }

    public function delete(int $id): bool
    {
        return Database::execute("DELETE FROM {$this->table} WHERE _ID = ? LIMIT 1", 'i', [$id]);
    }

    public function summary(array $items): array
    {
        $summary = ['total' => count($items), 'idea' => 0, 'planned' => 0, 'scheduled' => 0, 'published' => 0, 'cancelled' => 0, 'views' => 0, 'interactions' => 0];
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            $summary['views'] += (int) ($item['views'] ?? 0);
            $summary['interactions'] += (int) ($item['interactions'] ?? 0);
        }

        return $summary;
    }

    private function ensureTable(): void
    {
        if (Database::tableExists($this->table)) {
            return;
        }

        Database::execute(
            "CREATE TABLE {$this->table} (
                _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                post_date DATE NOT NULL,
                post_time CHAR(5) NOT NULL DEFAULT '',
                status VARCHAR(24) NOT NULL DEFAULT 'planned',
                channel VARCHAR(32) NOT NULL DEFAULT 'instagram',
                content_type VARCHAR(32) NOT NULL DEFAULT 'post',
                campaign VARCHAR(120) NOT NULL DEFAULT '',
                title VARCHAR(160) NOT NULL DEFAULT '',
                owner VARCHAR(100) NOT NULL DEFAULT '',
                post_copy TEXT NULL,
                asset_url VARCHAR(800) NOT NULL DEFAULT '',
                post_url VARCHAR(800) NOT NULL DEFAULT '',
                notes TEXT NULL,
                reach INT UNSIGNED NOT NULL DEFAULT 0,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                interactions INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (_ID),
                KEY post_date (post_date),
                KEY status (status),
                KEY channel (channel),
                KEY campaign (campaign)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function where(array $filters): array
    {
        $where = '1=1';
        $types = '';
        $params = [];

        $from = $this->date($filters['from'] ?? '', '');
        $to = $this->date($filters['to'] ?? '', '');
        if ($from !== '') {
            $where .= ' AND post_date >= ?';
            $types .= 's';
            $params[] = $from;
        }
        if ($to !== '') {
            $where .= ' AND post_date <= ?';
            $types .= 's';
            $params[] = $to;
        }

        foreach (['status' => 'status', 'channel' => 'channel', 'type' => 'content_type'] as $filter => $column) {
            $value = trim((string) ($filters[$filter] ?? ''));
            if ($value !== '') {
                $where .= " AND {$column} = ?";
                $types .= 's';
                $params[] = $value;
            }
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (title LIKE ? OR campaign LIKE ? OR owner LIKE ? OR post_copy LIKE ? OR notes LIKE ?)';
            $types .= 'sssss';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [$where, $types, $params];
    }

    private function date(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return $fallback;
        }
        $date = \DateTime::createFromFormat('Y-m-d', $text);
        return $date && $date->format('Y-m-d') === $text ? $text : $fallback;
    }

    private function time(mixed $value): string
    {
        $text = trim((string) $value);
        return preg_match('/^\d{2}:\d{2}$/', $text) ? $text : '';
    }

    private function choice(mixed $value, array $allowed, string $fallback): string
    {
        $text = trim((string) $value);
        return in_array($text, $allowed, true) ? $text : $fallback;
    }

    private function text(mixed $value, int $max): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
        return mb_substr($text, 0, $max);
    }

    private function textArea(mixed $value, int $max): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        return mb_substr($text, 0, $max);
    }
}
