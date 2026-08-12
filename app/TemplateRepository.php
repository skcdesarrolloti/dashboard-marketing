<?php

declare(strict_types=1);

namespace App;

final class TemplateRepository
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::table('jet_cct_plantillas');
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        if (!Database::tableExists($this->table)) {
            return ['items' => [], 'total' => 0, 'pages' => 1];
        }

        $columns = Database::columns($this->table);
        $where = '1=1';
        $params = [];
        $types = '';

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $parts = [];
            foreach (['nombre', 'tipo', 'asunto', 'contenido'] as $column) {
                if (!isset($columns[$column])) {
                    continue;
                }
                $parts[] = "`{$column}` LIKE ?";
                $params[] = '%' . $q . '%';
                $types .= 's';
            }
            if ($parts) {
                $where .= ' AND (' . implode(' OR ', $parts) . ')';
            }
        }

        $tipo = trim((string) ($filters['tipo'] ?? ''));
        if ($tipo !== '' && isset($columns['tipo'])) {
            $where .= ' AND `tipo` = ?';
            $params[] = $tipo;
            $types .= 's';
        }

        $total = (int) Database::value("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", $types, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $select = array_values(array_filter(['_ID', 'nombre', 'tipo', 'asunto', 'contenido', 'cct_modified'], static fn ($column): bool => isset($columns[$column]) || $column === '_ID'));
        $items = Database::rows(
            'SELECT `' . implode('`,`', $select) . "` FROM {$this->table} WHERE {$where} ORDER BY _ID DESC LIMIT ? OFFSET ?",
            $types . 'ii',
            array_merge($params, [$perPage, $offset])
        );

        return ['items' => $items, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function options(): array
    {
        return $this->paginate([], 1, 500)['items'];
    }

    public function find(int $id): array
    {
        if (!Database::tableExists($this->table)) {
            return [];
        }

        return Database::one("SELECT * FROM {$this->table} WHERE _ID = ? LIMIT 1", 'i', [$id]);
    }

    public function save(array $input): int
    {
        if (!Database::tableExists($this->table)) {
            return 0;
        }

        $columns = Database::columns($this->table);
        $type = strtolower(trim((string) ($input['tipo'] ?? 'email')));
        if (!in_array($type, ['email','sms','whatsapp'], true)) throw new \RuntimeException('Tipo de plantilla inválido.');
        if ($type === 'sms' && mb_strlen(CampaignEngine::SMS_PROVIDER_PREFIX . trim((string) ($input['contenido'] ?? ''))) > CampaignEngine::SMS_MAX) throw new \RuntimeException('La plantilla SMS supera ' . CampaignEngine::SMS_MAX . ' caracteres.');
        $input['tipo'] = $type;
        $data = [];
        foreach (['nombre', 'tipo', 'asunto', 'contenido'] as $field) {
            if (isset($columns[$field])) {
                $data[$field] = trim((string) ($input[$field] ?? ''));
            }
        }

        foreach (['cct_status' => 'publish', 'cct_modified' => date('Y-m-d H:i:s')] as $field => $value) {
            if (isset($columns[$field])) {
                $data[$field] = $value;
            }
        }

        $id = (int) ($input['_ID'] ?? 0);
        if ($id > 0) {
            Database::update($this->table, $data, $id);
            return $id;
        }

        if (isset($columns['cct_created'])) {
            $data['cct_created'] = date('Y-m-d H:i:s');
        }

        return Database::insert($this->table, $data);
    }

    public function duplicate(int $id): int
    {
        $template = $this->find($id);
        if (!$template) {
            return 0;
        }

        $template['_ID'] = 0;
        $template['nombre'] = trim((string) ($template['nombre'] ?? 'Plantilla')) . ' copia';
        return $this->save($template);
    }

    public function delete(int $id): bool
    {
        if (!Database::tableExists($this->table)) {
            return false;
        }

        return Database::execute("DELETE FROM {$this->table} WHERE _ID = ? LIMIT 1", 'i', [$id]);
    }
}
