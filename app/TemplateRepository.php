<?php

declare(strict_types=1);

namespace App;

final class TemplateRepository
{
    private string $table;
    private const WHATSAPP_DEFAULT_LANGUAGE = 'es_CO';

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
        if ($type === 'whatsapp') {
            if (trim((string) ($input['whatsapp_template_name'] ?? '')) === '') {
                $existingConfig = self::whatsappConfig(array_merge($input, ['tipo' => 'whatsapp']));
                if (trim((string) ($existingConfig['official_template'] ?? '')) !== '') {
                    $input['whatsapp_template_name'] = (string) $existingConfig['official_template'];
                    $input['whatsapp_language'] = (string) ($existingConfig['language'] ?? self::WHATSAPP_DEFAULT_LANGUAGE);
                    $input['whatsapp_category'] = (string) ($existingConfig['category'] ?? 'MARKETING');
                    $input['whatsapp_header_type'] = (string) ($existingConfig['header_type'] ?? 'none');
                    $input['whatsapp_header_url'] = (string) ($existingConfig['header_url'] ?? '');
                    $input['whatsapp_header_filename'] = (string) ($existingConfig['header_filename'] ?? '');
                    $input['whatsapp_button_type'] = (string) ($existingConfig['button_type'] ?? 'none');
                    $input['whatsapp_button_url_parameter'] = (string) ($existingConfig['button_url_parameter'] ?? '');
                    $input['contenido'] = (string) ($existingConfig['body_text'] ?? '');
                }
            }
            $input['contenido'] = json_encode(
                self::normalizeWhatsAppInput($input),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            if (trim((string) ($input['asunto'] ?? '')) === '') {
                $input['asunto'] = trim((string) ($input['whatsapp_template_name'] ?? ''));
            }
        }

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

    public static function whatsappConfig(array $template): array
    {
        if (strtolower(trim((string) ($template['tipo'] ?? ''))) !== 'whatsapp') {
            return [];
        }

        $content = trim((string) ($template['contenido'] ?? ''));
        if ($content === '') {
            return self::defaultWhatsAppConfig();
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && isset($decoded['official_template'])) {
                return array_replace_recursive(self::defaultWhatsAppConfig(), $decoded);
            }
        } catch (\JsonException) {
        }

        $config = self::defaultWhatsAppConfig();
        $config['body_text'] = $content;
        return $config;
    }

    public static function whatsappBody(array $template): string
    {
        $config = self::whatsappConfig($template);
        return trim((string) ($config['body_text'] ?? ($template['contenido'] ?? '')));
    }

    public static function whatsappListSummary(array $template): string
    {
        if (strtolower(trim((string) ($template['tipo'] ?? ''))) !== 'whatsapp') {
            return mb_strimwidth(strip_tags((string) ($template['contenido'] ?? '')), 0, 110, '…');
        }

        $config = self::whatsappConfig($template);
        $parts = array_filter([
            strtoupper((string) ($config['category'] ?? '')),
            (string) ($config['official_template'] ?? ''),
            (string) ($config['header_type'] ?? '') !== 'none' ? 'header ' . (string) $config['header_type'] : '',
        ]);
        $summary = implode(' · ', $parts);
        return $summary !== '' ? $summary : mb_strimwidth((string) ($config['body_text'] ?? ''), 0, 110, '…');
    }

    public static function normalizeWhatsAppInput(array $input): array
    {
        $headerType = strtolower(trim((string) ($input['whatsapp_header_type'] ?? 'none')));
        if (!in_array($headerType, ['none', 'image', 'video', 'document'], true)) {
            $headerType = 'none';
        }

        $category = strtoupper(trim((string) ($input['whatsapp_category'] ?? 'MARKETING')));
        if (!in_array($category, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)) {
            $category = 'MARKETING';
        }

        $buttonType = strtolower(trim((string) ($input['whatsapp_button_type'] ?? 'none')));
        if (!in_array($buttonType, ['none', 'url_dynamic'], true)) {
            $buttonType = 'none';
        }

        return [
            'official_template' => strtolower(preg_replace('/[^a-z0-9_]+/', '_', trim((string) ($input['whatsapp_template_name'] ?? ''))) ?? ''),
            'language' => trim((string) ($input['whatsapp_language'] ?? self::WHATSAPP_DEFAULT_LANGUAGE)) ?: self::WHATSAPP_DEFAULT_LANGUAGE,
            'category' => $category,
            'header_type' => $headerType,
            'header_url' => trim((string) ($input['whatsapp_header_url'] ?? '')),
            'header_filename' => trim((string) ($input['whatsapp_header_filename'] ?? '')),
            'body_text' => trim((string) ($input['contenido'] ?? $input['whatsapp_body_text'] ?? '')),
            'footer_text' => trim((string) ($input['whatsapp_footer_text'] ?? 'SKC SuCasa Inmobiliaria')),
            'button_type' => $buttonType,
            'button_url_parameter' => trim((string) ($input['whatsapp_button_url_parameter'] ?? '')),
        ];
    }

    private static function defaultWhatsAppConfig(): array
    {
        return [
            'official_template' => '',
            'language' => self::WHATSAPP_DEFAULT_LANGUAGE,
            'category' => 'MARKETING',
            'header_type' => 'none',
            'header_url' => '',
            'header_filename' => '',
            'body_text' => '',
            'footer_text' => 'SKC SuCasa Inmobiliaria',
            'button_type' => 'none',
            'button_url_parameter' => '',
        ];
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
