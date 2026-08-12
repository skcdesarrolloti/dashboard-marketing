<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use Throwable;

final class MeetingService
{
    public const MEETING_STATUSES = ['draft', 'in_progress', 'completed', 'archived'];
    public const ITEM_SECTIONS = ['achievement', 'improvement', 'action'];
    public const ACTION_STATUSES = ['pending', 'in_progress', 'completed', 'not_completed'];

    private MeetingRepository $repo;

    public function __construct(?MeetingRepository $repo = null)
    {
        $this->repo = $repo ?? new MeetingRepository();
    }

    public function pageData(array $filters = [], int $meetingId = 0): array
    {
        $dashboard = $this->repo->dashboard($filters);
        return $dashboard + [
            'selected' => $meetingId > 0 ? $this->repo->find($meetingId) : [],
            'employees' => $this->repo->employees(),
        ];
    }

    public function saveMeeting(array $input): int
    {
        $id = max(0, (int) ($input['id'] ?? 0));
        $existing = $id > 0 ? $this->requireMeeting($id) : [];
        if ($existing && in_array((string) $existing['status'], ['completed', 'archived'], true)) {
            throw new RuntimeException('La reunión está cerrada. Un administrador debe reabrirla antes de modificarla.');
        }
        $title = $this->text($input['title'] ?? '', 220);
        if ($title === '') throw new RuntimeException('Escribe un título para la reunión.');
        $meetingDate = $this->date($input['meeting_date'] ?? '');
        if ($meetingDate === '') throw new RuntimeException('Selecciona una fecha válida para la reunión.');
        $participants = $this->participants((array) ($input['participant_ids'] ?? []), (array) ($existing['participants'] ?? []));
        $user = $this->userSnapshot();
        $now = date('Y-m-d H:i:s');
        $data = [
            'title' => $title,
            'meeting_date' => $meetingDate,
            'objective' => $this->textArea($input['objective'] ?? '', 8000),
            'participants_json' => json_encode($participants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            'conclusions' => $this->textArea($input['conclusions'] ?? '', 12000),
            'updated_by' => $user['id'],
            'updated_by_name' => $user['name'],
            'updated_at' => $now,
        ];
        if ($id > 0) {
            $this->repo->updateMeeting($id, $data);
            return $id;
        }

        $previous = $this->repo->previousCompleted($meetingDate);
        if ($previous && $this->repo->hasCarryTarget((int) $previous['id'])) {
            $previous = [];
        }
        $data += [
            'status' => 'draft',
            'carried_from_meeting_id' => !empty($previous['id']) ? (int) $previous['id'] : null,
            'created_by' => $user['id'],
            'created_by_name' => $user['name'],
            'created_at' => $now,
        ];
        Database::beginTransaction();
        try {
            $id = $this->repo->insertMeeting($data);
            if ($id <= 0) throw new RuntimeException('No fue posible crear la reunión.');
            if ($previous) $this->carryActions((int) $previous['id'], $id, $user, $now);
            Database::commit();
        } catch (Throwable $error) {
            Database::rollBack();
            throw $error;
        }
        return $id;
    }

    public function saveItem(array $input): int
    {
        $id = max(0, (int) ($input['id'] ?? 0));
        $meetingId = max(0, (int) ($input['meeting_id'] ?? 0));
        $meeting = $this->requireEditableMeeting($meetingId);
        $existing = $id > 0 ? $this->repo->item($id) : [];
        if ($id > 0 && (!$existing || (int) $existing['meeting_id'] !== $meetingId)) {
            throw new RuntimeException('El elemento seleccionado no pertenece a esta reunión.');
        }
        $section = $existing ? (string) $existing['section'] : trim((string) ($input['section'] ?? ''));
        if (!in_array($section, self::ITEM_SECTIONS, true)) throw new RuntimeException('Selecciona una sección válida.');
        $title = $this->text($input['title'] ?? '', 300);
        if ($title === '') throw new RuntimeException($section === 'action' ? 'Describe la acción que debe realizarse.' : 'Escribe un título para el punto.');
        $user = $this->userSnapshot();
        $now = date('Y-m-d H:i:s');
        $data = [
            'title' => $title,
            'content' => $this->textArea($input['content'] ?? '', 12000),
            'observation' => $this->textArea($input['observation'] ?? '', 12000),
            'updated_by' => $user['id'],
            'updated_by_name' => $user['name'],
            'updated_at' => $now,
        ];
        if ($section === 'action') {
            $responsibleId = max(0, (int) ($input['responsible_employee_id'] ?? 0));
            $employee = $responsibleId > 0 ? $this->repo->employee($responsibleId) : [];
            if (!$employee && $existing && $responsibleId === (int) ($existing['responsible_employee_id'] ?? 0)) {
                $employee = ['nombre' => (string) ($existing['responsible_name'] ?? '')];
            }
            if ($responsibleId <= 0 || !$employee) {
                throw new RuntimeException('Selecciona un responsable para la acción.');
            }
            $dueDate = $this->date($input['due_date'] ?? '');
            if ($dueDate === '') {
                throw new RuntimeException('Selecciona una fecha límite válida para la acción.');
            }
            $relatedId = max(0, (int) ($input['related_improvement_id'] ?? 0));
            if ($relatedId > 0) {
                $related = $this->repo->item($relatedId);
                if (!$related || (int) $related['meeting_id'] !== $meetingId || (string) $related['section'] !== 'improvement') {
                    throw new RuntimeException('El punto de mejora relacionado no es válido.');
                }
            }
            $data += [
                'related_improvement_id' => $relatedId ?: null,
                'responsible_employee_id' => $responsibleId,
                'responsible_name' => $this->text($employee['nombre'] ?? '', 191),
                'due_date' => $dueDate,
            ];
        }
        if ($existing) {
            $this->repo->updateItem($id, $data);
            return $id;
        }
        $data += [
            'meeting_id' => $meetingId,
            'section' => $section,
            'status' => $section === 'action' ? 'pending' : 'recorded',
            'sort_order' => $this->repo->maxSort($meetingId, $section) + 10,
            'active' => 1,
            'created_by' => $user['id'],
            'created_by_name' => $user['name'],
            'created_at' => $now,
        ];
        $id = $this->repo->insertItem($data);
        if ($id <= 0) throw new RuntimeException('No fue posible guardar el punto de reunión.');
        return $id;
    }

    public function updateActionStatus(int $itemId, string $status, string $reason = ''): array
    {
        $item = $this->repo->item($itemId);
        if (!$item || (string) ($item['section'] ?? '') !== 'action' || (int) ($item['active'] ?? 0) !== 1) {
            throw new RuntimeException('La acción seleccionada ya no está disponible.');
        }
        $meetingId = (int) $item['meeting_id'];
        $this->requireEditableMeeting($meetingId);
        if (!in_array($status, self::ACTION_STATUSES, true)) throw new RuntimeException('Selecciona un estado válido.');
        $reason = $this->textArea($reason, 12000);
        if ($status === 'not_completed' && $reason === '') {
            throw new RuntimeException('Explica por qué la acción no se realizó.');
        }
        $from = (string) ($item['status'] ?? 'pending');
        if ($from === $status && ($status !== 'not_completed' || $reason === (string) ($item['failure_reason'] ?? ''))) {
            return ['item' => $item, 'progress' => $this->repo->progress($meetingId)];
        }
        $user = $this->userSnapshot();
        $now = date('Y-m-d H:i:s');
        Database::beginTransaction();
        try {
            $this->repo->updateItem($itemId, [
                'status' => $status,
                'failure_reason' => $status === 'not_completed' ? $reason : '',
                'completed_by' => $status === 'completed' ? $user['id'] : null,
                'completed_by_name' => $status === 'completed' ? $user['name'] : '',
                'completed_at' => $status === 'completed' ? $now : null,
                'updated_by' => $user['id'],
                'updated_by_name' => $user['name'],
                'updated_at' => $now,
            ]);
            if ($this->repo->insertHistory([
                'meeting_id' => $meetingId,
                'action_item_id' => $itemId,
                'from_status' => $from,
                'to_status' => $status,
                'reason' => $reason,
                'changed_by' => $user['id'],
                'changed_by_name' => $user['name'],
                'changed_at' => $now,
            ]) <= 0) {
                throw new RuntimeException('No fue posible registrar el historial de la acción.');
            }
            Database::commit();
        } catch (Throwable $error) {
            Database::rollBack();
            throw $error;
        }
        return ['item' => $this->repo->item($itemId), 'progress' => $this->repo->progress($meetingId)];
    }

    public function moveItem(int $itemId, string $direction): void
    {
        $item = $this->repo->item($itemId);
        if (!$item || (int) ($item['active'] ?? 0) !== 1) throw new RuntimeException('El elemento ya no está disponible.');
        $this->requireEditableMeeting((int) $item['meeting_id']);
        if (!in_array($direction, ['up', 'down'], true)) throw new RuntimeException('Movimiento inválido.');
        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $table = Database::table('marketing_meeting_items');
        $neighbor = Database::one(
            "SELECT * FROM {$table} WHERE meeting_id = ? AND section = ? AND active = 1 AND sort_order {$operator} ? ORDER BY sort_order {$order}, id {$order} LIMIT 1",
            'isi',
            [(int) $item['meeting_id'], (string) $item['section'], (int) $item['sort_order']]
        );
        if (!$neighbor) return;
        $this->repo->updateItem((int) $item['id'], ['sort_order' => (int) $neighbor['sort_order']]);
        $this->repo->updateItem((int) $neighbor['id'], ['sort_order' => (int) $item['sort_order']]);
    }

    public function archiveItem(int $itemId): int
    {
        $item = $this->repo->item($itemId);
        if (!$item) throw new RuntimeException('El elemento ya no está disponible.');
        $meetingId = (int) $item['meeting_id'];
        $this->requireEditableMeeting($meetingId);
        $user = $this->userSnapshot();
        $this->repo->updateItem($itemId, ['active' => 0, 'updated_by' => $user['id'], 'updated_by_name' => $user['name'], 'updated_at' => date('Y-m-d H:i:s')]);
        return $meetingId;
    }

    public function start(int $meetingId): void
    {
        $meeting = $this->requireMeeting($meetingId);
        if ((string) $meeting['status'] !== 'draft') throw new RuntimeException('Solo las reuniones en borrador pueden iniciarse.');
        $this->transitionMeeting($meetingId, ['status' => 'in_progress', 'started_at' => date('Y-m-d H:i:s')]);
    }

    public function complete(int $meetingId, string $conclusions = ''): void
    {
        $meeting = $this->requireMeeting($meetingId);
        if (!in_array((string) $meeting['status'], ['draft', 'in_progress'], true)) throw new RuntimeException('Esta reunión ya está cerrada.');
        $this->transitionMeeting($meetingId, [
            'status' => 'completed',
            'conclusions' => $this->textArea($conclusions, 12000),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function reopen(int $meetingId): void
    {
        $meeting = $this->requireMeeting($meetingId);
        if ((string) $meeting['status'] !== 'completed') throw new RuntimeException('Solo una reunión finalizada puede reabrirse.');
        $this->transitionMeeting($meetingId, ['status' => 'in_progress', 'completed_at' => null]);
    }

    public function archive(int $meetingId): void
    {
        $this->requireMeeting($meetingId);
        $this->transitionMeeting($meetingId, ['status' => 'archived', 'archived_at' => date('Y-m-d H:i:s')]);
    }

    public function delete(int $meetingId): void
    {
        $this->requireMeeting($meetingId);
        Database::beginTransaction();
        try {
            $this->repo->deleteMeetingData($meetingId);
            if ($this->repo->find($meetingId)) {
                throw new RuntimeException('La reunión no pudo eliminarse completamente.');
            }
            Database::commit();
        } catch (Throwable $error) {
            Database::rollBack();
            throw $error;
        }
    }

    private function transitionMeeting(int $meetingId, array $changes): void
    {
        $user = $this->userSnapshot();
        $changes += ['updated_by' => $user['id'], 'updated_by_name' => $user['name'], 'updated_at' => date('Y-m-d H:i:s')];
        $this->repo->updateMeeting($meetingId, $changes);
    }

    private function carryActions(int $sourceMeetingId, int $targetMeetingId, array $user, string $now): void
    {
        $sort = 0;
        foreach ($this->repo->unresolvedActions($sourceMeetingId) as $source) {
            $sort += 10;
            $copyId = $this->repo->insertItem([
                'meeting_id' => $targetMeetingId,
                'section' => 'action',
                'title' => (string) $source['title'],
                'content' => (string) ($source['content'] ?? ''),
                'observation' => (string) ($source['observation'] ?? ''),
                'responsible_employee_id' => (int) ($source['responsible_employee_id'] ?? 0) ?: null,
                'responsible_name' => (string) ($source['responsible_name'] ?? ''),
                'due_date' => !empty($source['due_date']) ? (string) $source['due_date'] : null,
                'status' => 'pending',
                'failure_reason' => '',
                'carried_from_item_id' => (int) $source['id'],
                'sort_order' => $sort,
                'active' => 1,
                'created_by' => $user['id'],
                'created_by_name' => $user['name'],
                'updated_by' => $user['id'],
                'updated_by_name' => $user['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($copyId <= 0) throw new RuntimeException('No fue posible copiar una acción pendiente de la reunión anterior.');
        }
    }

    private function participants(array $ids, array $historical = []): array
    {
        $historicalNames = [];
        foreach ($historical as $participant) {
            $historicalNames[(int) ($participant['id'] ?? 0)] = (string) ($participant['name'] ?? '');
        }
        $out = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $ids)))) as $id) {
            $employee = $this->repo->employee($id);
            $name = $employee ? (string) ($employee['nombre'] ?? '') : (string) ($historicalNames[$id] ?? '');
            if ($name !== '') $out[] = ['id' => $id, 'name' => $this->text($name, 191)];
        }
        return $out;
    }

    private function requireMeeting(int $id): array
    {
        $meeting = $this->repo->find($id);
        if (!$meeting) throw new RuntimeException('La reunión seleccionada no existe.');
        return $meeting;
    }

    private function requireEditableMeeting(int $id): array
    {
        $meeting = $this->requireMeeting($id);
        if (in_array((string) $meeting['status'], ['completed', 'archived'], true)) {
            throw new RuntimeException('La reunión está cerrada y no admite cambios.');
        }
        return $meeting;
    }

    private function userSnapshot(): array
    {
        $user = Auth::user();
        return ['id' => (int) ($user['id'] ?? 0), 'name' => $this->text($user['nombre'] ?? 'Funcionario', 191)];
    }

    private function date(mixed $value): string
    {
        $value = trim((string) $value);
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function text(mixed $value, int $max): string
    {
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        return mb_substr($value, 0, $max);
    }

    private function textArea(mixed $value, int $max): string
    {
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        return mb_substr($value, 0, $max);
    }
}
