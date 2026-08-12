<?php

declare(strict_types=1);

namespace App;

final class DeliveryRepository
{
    public function myQueue(): array
    {
        $id = (int) (Auth::user()['id'] ?? 0);
        $result = ['email'=>0,'sms'=>0,'paused'=>false];
        if (Database::tableExists('skc_notification_queue')) {
            $rows = Database::rows("SELECT channel,COUNT(*) total FROM skc_notification_queue WHERE project_code='gestor-actores' AND status='pending' AND gda_id_funcionario=? GROUP BY channel", 'i', [$id]);
            foreach ($rows as $row) $result[(string)$row['channel']] = (int)$row['total'];
        }
        $result['paused'] = in_array($id, array_map('intval',(array)(new AdminRepository())->getOption('gda_paused_users',[])), true);
        return $result;
    }

    public function recentForUser(int $limit = 12): array
    {
        $id = (int) (Auth::user()['id'] ?? 0);
        $items = [];
        if (Database::tableExists('skc_notification_queue')) {
            $columns = Database::columns('skc_notification_queue');
            $select = array_values(array_filter(['id','channel','status','destination_name','destination','subject','created_at','sent_at'], static fn($c)=>isset($columns[$c])));
            if ($select !== []) {
                $items = Database::rows("SELECT `".implode('`,`',$select)."` FROM skc_notification_queue WHERE project_code='gestor-actores' AND gda_id_funcionario=? ORDER BY id DESC LIMIT ?", 'ii', [$id,$limit]);
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['sent_at'] ?? $b['created_at'] ?? ''), (string) ($a['sent_at'] ?? $a['created_at'] ?? '')));
        return array_slice($items, 0, $limit);
    }
    public function summary(array $filters = []): array
    {
        $summary = [
            'queue_pending' => 0,
            'queue_failed' => 0,
            'queue_sent' => 0,
            'queue_total' => 0,
            'channels' => [],
        ];

        if (Database::tableExists('skc_notification_queue')) {
            $columns = Database::columns('skc_notification_queue');
            $where = "project_code = 'gestor-actores'";
            $params = [];
            $types = '';
            $this->applyFilters($where, $params, $types, $columns, $filters);

            $rows = Database::rows(
                "SELECT channel, status, COUNT(*) AS total
                   FROM skc_notification_queue
                  WHERE {$where}
                  GROUP BY channel, status",
                $types,
                $params
            );
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                $total = (int) ($row['total'] ?? 0);
                $summary['queue_total'] += $total;
                if ($status === 'pending') {
                    $summary['queue_pending'] += $total;
                } elseif ($status === 'failed') {
                    $summary['queue_failed'] += $total;
                } elseif ($status === 'sent') {
                    $summary['queue_sent'] += $total;
                }
                $channel = (string) ($row['channel'] ?? 'sin canal');
                $summary['channels'][$channel] = ($summary['channels'][$channel] ?? 0) + $total;
            }
        }

        arsort($summary['channels']);
        return $summary;
    }

    public function queue(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (!Database::tableExists('skc_notification_queue')) {
            return ['items' => [], 'total' => 0, 'pages' => 1];
        }

        $columns = Database::columns('skc_notification_queue');
        $where = "project_code = 'gestor-actores'";
        $params = [];
        $types = '';
        $this->applyFilters($where, $params, $types, $columns, $filters);

        $total = (int) Database::value("SELECT COUNT(*) FROM skc_notification_queue WHERE {$where}", $types, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $select = array_values(array_filter(['id', 'channel', 'status', 'destination_name', 'destination', 'subject', 'attempts', 'scheduled_at', 'created_at', 'sent_at', 'gda_tipo_actor'], static fn ($column): bool => isset($columns[$column])));
        $items = Database::rows(
            'SELECT `' . implode('`,`', $select) . "` FROM skc_notification_queue WHERE {$where} ORDER BY id DESC LIMIT ? OFFSET ?",
            $types . 'ii',
            array_merge($params, [$perPage, $offset])
        );

        return ['items' => $items, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function removeFromQueue(int $id): string
    {
        if ($id < 1 || !Database::tableExists('skc_notification_queue')) {
            return 'not_found';
        }

        $columns = Database::columns('skc_notification_queue');
        if (!isset($columns['id'], $columns['status'])) {
            return 'not_found';
        }

        $row = Database::one(
            "SELECT status FROM skc_notification_queue WHERE project_code = 'gestor-actores' AND id = ? LIMIT 1",
            'i',
            [$id]
        );
        if ($row === []) {
            return 'not_found';
        }

        $status = strtolower((string) ($row['status'] ?? ''));
        if ($status === 'sent') {
            return 'sent';
        }
        if ($status === 'processing') {
            return 'processing';
        }

        $removable = ['pending', 'paused', 'failed'];
        if (!in_array($status, $removable, true)) {
            return 'blocked';
        }

        return Database::execute(
            "DELETE FROM skc_notification_queue WHERE project_code = 'gestor-actores' AND id = ? AND status IN ('pending', 'paused', 'failed') LIMIT 1",
            'i',
            [$id]
        ) ? 'removed' : 'blocked';
    }

    public function filterOptions(): array
    {
        if (!Database::tableExists('skc_notification_queue')) {
            return ['channels' => [], 'statuses' => [], 'actors' => []];
        }

        $options = ['channels' => [], 'statuses' => [], 'actors' => []];
        foreach (Database::rows(
            "SELECT DISTINCT channel, status, gda_tipo_actor
               FROM skc_notification_queue
              WHERE project_code = 'gestor-actores'"
        ) as $row) {
            foreach (['channels' => 'channel', 'statuses' => 'status', 'actors' => 'gda_tipo_actor'] as $bucket => $column) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value !== '') {
                    $options[$bucket][$value] = $value;
                }
            }
        }

        foreach ($options as &$values) {
            natcasesort($values);
            $values = array_values($values);
        }

        return $options;
    }

    private function applyFilters(string &$where, array &$params, string &$types, array $columns, array $filters): void
    {
        foreach (['channel', 'status', 'gda_tipo_actor'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));
            if ($value === '' || !isset($columns[$column])) continue;
            $where .= " AND `{$column}` = ?";
            $params[] = $value;
            $types .= 's';
        }

        foreach (['destination_name', 'destination'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));
            if ($value === '' || !isset($columns[$column])) continue;
            $where .= " AND `{$column}` LIKE ?";
            $params[] = '%' . $value . '%';
            $types .= 's';
        }
    }
}
