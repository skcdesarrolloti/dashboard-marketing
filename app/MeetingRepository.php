<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class MeetingRepository
{
    private string $meetings;
    private string $items;
    private string $history;

    public function __construct()
    {
        MeetingSchema::ensure();
        $this->meetings = Database::table('marketing_meetings');
        $this->items = Database::table('marketing_meeting_items');
        $this->history = Database::table('marketing_meeting_action_history');
    }

    public function dashboard(array $filters = []): array
    {
        $where = ['m.status <> ?'];
        $types = 's';
        $params = ['archived'];
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'upcoming') {
            $where = ["m.status = 'draft'", 'm.meeting_date >= CURDATE()'];
            $types = '';
            $params = [];
        } elseif ($status === 'history') {
            $where = ["m.status IN ('completed','archived')"];
            $types = '';
            $params = [];
        } elseif (in_array($status, MeetingService::MEETING_STATUSES, true)) {
            $where = ['m.status = ?'];
            $types = 's';
            $params = [$status];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(m.title LIKE ? OR m.objective LIKE ? OR m.conclusions LIKE ?)';
            $types .= 'sss';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        $rows = Database::rows(
            "SELECT m.*,
                    COUNT(i.id) action_total,
                    SUM(i.status = 'completed') action_completed,
                    SUM(i.status = 'not_completed') action_not_completed,
                    SUM(i.status IN ('pending','in_progress')) action_open
               FROM {$this->meetings} m
               LEFT JOIN {$this->items} i ON i.meeting_id = m.id AND i.active = 1 AND i.section = 'action'
              WHERE " . implode(' AND ', $where) . "
              GROUP BY m.id
              ORDER BY (m.status = 'in_progress') DESC, (m.meeting_date >= CURDATE()) DESC, m.meeting_date DESC, m.id DESC
              LIMIT 250",
            $types,
            $params
        );
        foreach ($rows as &$row) {
            $row = $this->decorateMeeting($row);
        }
        unset($row);
        $summary = Database::one(
            "SELECT COUNT(*) total,
                    SUM(status = 'draft' AND meeting_date >= CURDATE()) upcoming,
                    SUM(status = 'in_progress') in_progress,
                    SUM(status = 'completed') completed
               FROM {$this->meetings}
              WHERE status <> 'archived'"
        );
        $actions = Database::one(
            "SELECT COUNT(*) reviewed, SUM(status = 'completed') completed
               FROM {$this->items}
              WHERE active = 1 AND section = 'action' AND status IN ('completed','not_completed')"
        );
        $reviewed = (int) ($actions['reviewed'] ?? 0);
        $summary['total'] = (int) ($summary['total'] ?? 0);
        $summary['upcoming'] = (int) ($summary['upcoming'] ?? 0);
        $summary['in_progress'] = (int) ($summary['in_progress'] ?? 0);
        $summary['completed'] = (int) ($summary['completed'] ?? 0);
        $summary['compliance'] = $reviewed > 0 ? (int) round(((int) ($actions['completed'] ?? 0) / $reviewed) * 100) : 0;
        return ['meetings' => $rows, 'summary' => $summary];
    }

    public function find(int $id): array
    {
        if ($id <= 0) return [];
        $meeting = Database::one("SELECT * FROM {$this->meetings} WHERE id = ? LIMIT 1", 'i', [$id]);
        if (!$meeting) return [];
        $meeting = $this->decorateMeeting($meeting);
        $meeting['items'] = ['achievement' => [], 'improvement' => [], 'action' => []];
        foreach (Database::rows(
            "SELECT i.*, source.title carried_from_title, source.meeting_id source_meeting_id
               FROM {$this->items} i
               LEFT JOIN {$this->items} source ON source.id = i.carried_from_item_id
              WHERE i.meeting_id = ? AND i.active = 1
              ORDER BY i.section ASC, i.sort_order ASC, i.id ASC",
            'i',
            [$id]
        ) as $item) {
            $section = (string) ($item['section'] ?? '');
            if (isset($meeting['items'][$section])) $meeting['items'][$section][] = $item;
        }
        $meeting['history'] = Database::rows(
            "SELECT h.*, i.title action_title
               FROM {$this->history} h
               LEFT JOIN {$this->items} i ON i.id = h.action_item_id
              WHERE h.meeting_id = ?
              ORDER BY h.changed_at DESC, h.id DESC",
            'i',
            [$id]
        );
        $meeting['progress'] = $this->progress($id);
        return $meeting;
    }

    public function insertMeeting(array $data): int
    {
        if (empty($data['carried_from_meeting_id'])) unset($data['carried_from_meeting_id']);
        return $this->insert($this->meetings, $data);
    }
    public function updateMeeting(int $id, array $data): bool
    {
        if (!$this->nullableUpdate($this->meetings, $id, $data)) throw new RuntimeException('No fue posible actualizar la reunión.');
        return true;
    }
    public function insertItem(array $data): int
    {
        foreach (['related_improvement_id', 'responsible_employee_id', 'due_date', 'carried_from_item_id', 'completed_by', 'completed_at'] as $nullable) {
            if (!isset($data[$nullable]) || $data[$nullable] === '' || $data[$nullable] === null || $data[$nullable] === 0) unset($data[$nullable]);
        }
        return $this->insert($this->items, $data);
    }
    public function updateItem(int $id, array $data): bool
    {
        if (!$this->nullableUpdate($this->items, $id, $data)) throw new RuntimeException('No fue posible actualizar el punto de reunión.');
        return true;
    }

    public function item(int $id): array
    {
        return $id > 0 ? Database::one("SELECT * FROM {$this->items} WHERE id = ? LIMIT 1", 'i', [$id]) : [];
    }

    public function previousCompleted(string $beforeDate, int $excludeId = 0): array
    {
        return Database::one(
            "SELECT * FROM {$this->meetings}
              WHERE status = 'completed' AND meeting_date <= ? AND id <> ?
              ORDER BY meeting_date DESC, id DESC LIMIT 1",
            'si',
            [$beforeDate, $excludeId]
        );
    }

    public function hasCarryTarget(int $sourceMeetingId): bool
    {
        if ($sourceMeetingId <= 0) return false;
        return (int) Database::value(
            "SELECT COUNT(*) FROM {$this->meetings} WHERE carried_from_meeting_id = ?",
            'i',
            [$sourceMeetingId]
        ) > 0;
    }

    public function unresolvedActions(int $meetingId): array
    {
        return Database::rows(
            "SELECT * FROM {$this->items}
              WHERE meeting_id = ? AND active = 1 AND section = 'action' AND status IN ('pending','in_progress','not_completed')
              ORDER BY sort_order ASC, id ASC",
            'i',
            [$meetingId]
        );
    }

    public function maxSort(int $meetingId, string $section): int
    {
        return (int) Database::value("SELECT COALESCE(MAX(sort_order),0) FROM {$this->items} WHERE meeting_id = ? AND section = ?", 'is', [$meetingId, $section]);
    }

    public function insertHistory(array $data): int { return $this->insert($this->history, $data); }

    public function deleteMeetingData(int $meetingId): void
    {
        if ($meetingId <= 0) throw new RuntimeException('La reunión seleccionada no es válida.');
        $queries = [
            ["DELETE h FROM {$this->history} h LEFT JOIN {$this->items} i ON i.id = h.action_item_id WHERE h.meeting_id = ? OR i.meeting_id = ?", 'ii', [$meetingId, $meetingId]],
            ["UPDATE {$this->items} child INNER JOIN {$this->items} source ON source.id = child.carried_from_item_id SET child.carried_from_item_id = NULL WHERE source.meeting_id = ? AND child.meeting_id <> ?", 'ii', [$meetingId, $meetingId]],
            ["UPDATE {$this->items} child INNER JOIN {$this->items} source ON source.id = child.related_improvement_id SET child.related_improvement_id = NULL WHERE source.meeting_id = ? AND child.meeting_id <> ?", 'ii', [$meetingId, $meetingId]],
            ["UPDATE {$this->meetings} SET carried_from_meeting_id = NULL WHERE carried_from_meeting_id = ?", 'i', [$meetingId]],
            ["DELETE FROM {$this->items} WHERE meeting_id = ?", 'i', [$meetingId]],
            ["DELETE FROM {$this->meetings} WHERE id = ? LIMIT 1", 'i', [$meetingId]],
        ];
        foreach ($queries as [$sql, $types, $params]) {
            if (!Database::execute($sql, $types, $params)) {
                throw new RuntimeException('No fue posible eliminar completamente la reunión.');
            }
        }
    }

    public function progress(int $meetingId): array
    {
        $row = Database::one(
            "SELECT COUNT(*) total,
                    SUM(status = 'completed') completed,
                    SUM(status = 'not_completed') not_completed,
                    SUM(status IN ('completed','not_completed')) reviewed
               FROM {$this->items}
              WHERE meeting_id = ? AND active = 1 AND section = 'action'",
            'i',
            [$meetingId]
        );
        $total = (int) ($row['total'] ?? 0);
        $reviewed = (int) ($row['reviewed'] ?? 0);
        return [
            'total' => $total,
            'completed' => (int) ($row['completed'] ?? 0),
            'not_completed' => (int) ($row['not_completed'] ?? 0),
            'reviewed' => $reviewed,
            'review_percent' => $total > 0 ? (int) round(($reviewed / $total) * 100) : 0,
            'compliance_percent' => $reviewed > 0 ? (int) round(((int) ($row['completed'] ?? 0) / $reviewed) * 100) : 0,
        ];
    }

    public function employees(): array
    {
        $table = Database::table('jet_cct_funcionarios');
        if (!Database::tableExists($table)) return [];
        return Database::rows(
            "SELECT id_empleado id, nombre, correo
               FROM {$table}
              WHERE COALESCE(NULLIF(LOWER(TRIM(activo)), ''), 'si') <> 'no'
                AND nombre IS NOT NULL AND TRIM(nombre) <> ''
              ORDER BY nombre ASC"
        );
    }

    public function employee(int $id): array
    {
        if ($id <= 0) return [];
        $table = Database::table('jet_cct_funcionarios');
        if (!Database::tableExists($table)) return [];
        return Database::one("SELECT id_empleado id, nombre, correo FROM {$table} WHERE id_empleado = ? LIMIT 1", 'i', [$id]);
    }

    private function decorateMeeting(array $meeting): array
    {
        $participants = json_decode((string) ($meeting['participants_json'] ?? '[]'), true);
        $meeting['participants'] = is_array($participants) ? $participants : [];
        foreach (['action_total', 'action_completed', 'action_not_completed', 'action_open'] as $key) {
            if (array_key_exists($key, $meeting)) $meeting[$key] = (int) $meeting[$key];
        }
        $reviewed = (int) ($meeting['action_completed'] ?? 0) + (int) ($meeting['action_not_completed'] ?? 0);
        $meeting['compliance'] = $reviewed > 0 ? (int) round(((int) ($meeting['action_completed'] ?? 0) / $reviewed) * 100) : 0;
        return $meeting;
    }

    private function nullableUpdate(string $table, int $id, array $data): bool
    {
        if ($id <= 0 || $data === []) return false;
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            if ($value === null) {
                $sets[] = "`{$column}` = NULL";
            } else {
                $sets[] = "`{$column}` = ?";
                $params[] = (string) $value;
            }
        }
        $params[] = $id;
        return Database::execute(
            "UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = ? LIMIT 1',
            str_repeat('s', count($params) - 1) . 'i',
            $params
        );
    }

    private function insert(string $table, array $data): int
    {
        return Database::insert($table, $data);
    }
}
