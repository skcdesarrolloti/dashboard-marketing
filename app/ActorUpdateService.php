<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class ActorUpdateService
{
    private const TOKEN_TTL = 900;

    public function preview(string $type, array $input): array
    {
        $prepared = $this->prepare($type, $input);
        $propagation = new ActorPropagation();
        $impact = $propagation->impact($type, $prepared['id'], $prepared['before'], $prepared['changes']);
        $payload = [
            'type' => $type,
            'id' => $prepared['id'],
            'user' => Auth::authorId(),
            'version' => $prepared['version'],
            'changes_hash' => $this->changesHash($prepared['changes']),
            'expires' => time() + self::TOKEN_TTL,
        ];

        return [
            'ok' => true,
            'changes' => $this->presentChanges($prepared['config'], $prepared['changes']),
            'impact' => $impact,
            'totals' => [
                'active' => array_sum(array_column($impact, 'active')),
                'excluded' => array_sum(array_column($impact, 'excluded')),
            ],
            'warnings' => $this->warnings($prepared, $impact),
            'preview_token' => $this->sign($payload),
            'original_version' => $prepared['version'],
        ];
    }

    public function commit(string $type, array $input): int
    {
        $token = trim((string) ($input['preview_token'] ?? ''));
        $payload = $this->verifyToken($token);
        if (($payload['type'] ?? '') !== $type
            || (int) ($payload['id'] ?? 0) !== (int) ($input['_ID'] ?? 0)
            || (int) ($payload['user'] ?? 0) !== Auth::authorId()) {
            throw new RuntimeException('La previsualización no corresponde a este actor o usuario.');
        }

        $prepared = $this->prepare($type, $input);
        if (!hash_equals((string) ($payload['changes_hash'] ?? ''), $this->changesHash($prepared['changes']))) {
            throw new RuntimeException('Los campos cambiaron después de la previsualización. Revisa los cambios nuevamente.');
        }
        if (!hash_equals((string) ($payload['version'] ?? ''), $prepared['version'])) {
            throw new RuntimeException('Otro usuario actualizó este actor. Recarga el editor antes de continuar.');
        }
        if ($prepared['changes'] === []) {
            throw new RuntimeException('No hay cambios para guardar.');
        }

        $config = $prepared['config'];
        $table = (string) $config['table'];
        $id = $prepared['id'];
        $propagation = new ActorPropagation();

        Database::beginTransaction();
        try {
            $locked = Database::one("SELECT * FROM {$table} WHERE _ID = ? FOR UPDATE", 'i', [$id]);
            $lockedVersion = (string) ($locked['cct_modified'] ?? '');
            if (!$locked || !hash_equals($prepared['version'], $lockedVersion)) {
                throw new RuntimeException('Otro usuario actualizó este actor. Recarga el editor antes de continuar.');
            }

            $data = [];
            foreach ($prepared['changes'] as $field => $change) {
                $data[$field] = $change['after'];
            }
            $data['cct_modified'] = gmdate('Y-m-d H:i:s');
            $sets = array_map(static fn (string $column): string => "`{$column}` = ?", array_keys($data));
            $params = array_merge(array_values($data), [$id, $prepared['version']]);
            $ok = Database::execute(
                "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE _ID = ? AND COALESCE(cct_modified, '') = ? LIMIT 1",
                str_repeat('s', count($data)) . 'is',
                $params
            );
            if (!$ok || Database::affectedRows() !== 1) {
                throw new RuntimeException('El registro cambió durante la confirmación; no se aplicó ninguna actualización.');
            }

            $appliedImpact = $propagation->apply($type, $id, $prepared['before'], $prepared['changes']);
            $this->audit($type, $id, $prepared, $appliedImpact, $input);
            Database::commit();
            return $id;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private function prepare(string $type, array $input): array
    {
        $config = ActorCatalog::get($type);
        $id = (int) ($input['_ID'] ?? 0);
        if (!$config || $id <= 0 || !Database::tableExists((string) $config['table'])) {
            throw new RuntimeException('Tipo de actor o registro inválido.');
        }
        $before = (new ActorRepository())->find($type, $id);
        if (!$before) {
            throw new RuntimeException('El actor no existe o no está disponible para tu usuario.');
        }
        $authorId = (int) ($before['cct_author_id'] ?? 0);
        if (!PermissionService::canFullEdit($type, $id, $authorId)) {
            throw new RuntimeException('No tienes permisos para editar completamente este actor.');
        }

        $columns = Database::columns((string) $config['table']);
        $columnTypes = [];
        foreach (Database::rows('SHOW COLUMNS FROM ' . (string) $config['table']) as $column) {
            $columnTypes[(string) ($column['Field'] ?? '')] = strtolower((string) ($column['Type'] ?? ''));
        }
        $data = [];
        foreach (($config['campos'] ?? []) as $field) {
            $fieldId = trim((string) ($field['id'] ?? ''));
            $fieldType = (string) ($field['type'] ?? 'text');
            if ($fieldId === '' || !isset($columns[$fieldId])
                || in_array($fieldType, ['header', 'subheader'], true)
                || !empty($field['readonly']) || !empty($field['sensitive'])) {
                continue;
            }
            $raw = $input[$fieldId] ?? null;
            if ($fieldType === 'checkbox') {
                $data[$fieldId] = in_array($raw, ['1', 1, true, 'on'], true) ? '1' : '0';
                continue;
            }
            if ($raw === null) {
                continue;
            }
            if ($fieldType === 'multiselect') {
                $values = is_array($raw) ? $raw : preg_split('/[,\s;|]+/', (string) $raw);
                $values = array_values(array_unique(array_filter(array_map(
                    static fn ($value): string => trim((string) $value),
                    (array) $values
                ))));
                $data[$fieldId] = implode(',', $values);
                continue;
            }
            $value = trim((string) $raw);
            if ($fieldType === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('El correo de “' . ($field['label'] ?? $fieldId) . '” no es válido.');
            }
            if ($fieldType === 'number' && $value !== '' && (!is_numeric($value) || (float) $value < 0)) {
                throw new RuntimeException('“' . ($field['label'] ?? $fieldId) . '” debe ser un número igual o mayor que cero.');
            }
            if ($fieldType === 'date' && $value !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    throw new RuntimeException('La fecha de “' . ($field['label'] ?? $fieldId) . '” no es válida.');
                }
                if (str_contains((string) ($columnTypes[$fieldId] ?? ''), 'int')) {
                    $value = (string) $date->getTimestamp();
                }
            }
            $data[$fieldId] = $value;
        }

        $countryId = (int) ($input['country_id'] ?? 0);
        if ($countryId > 0) {
            $country = CountryCatalog::find($countryId);
            if (!$country) {
                throw new RuntimeException('El país seleccionado no existe en el catálogo.');
            }
            if (isset($columns['pais'])) {
                $data['pais'] = (string) $country['name'];
            }
            if (isset($columns['indicativo'])) {
                $data['indicativo'] = (string) $country['calling_code'];
            }
        }

        $phoneField = (string) ($config['phone'] ?? '');
        if ($phoneField !== '' && isset($data[$phoneField])) {
            $callingCode = (string) ($data['indicativo'] ?? $before['indicativo'] ?? '');
            $data[$phoneField] = ActorPhone::national((string) $data[$phoneField], $callingCode);
        }
        if (isset($data['indicativo'])) {
            $data['indicativo'] = CountryCatalog::normalizeCallingCode((string) $data['indicativo']);
        }

        if ($type === 'club_pph' && isset($columns['total_familia'])) {
            $ranges = ['infancia_familia','ninez_familia','pubertad_familia','adolescencia_familia','juventud_familia','adulto_familia','adulto_mayor_familia','ancianidad_familia'];
            $total = 0;
            foreach ($ranges as $range) {
                $total += (int) ($data[$range] ?? $before[$range] ?? 0);
            }
            $data['total_familia'] = (string) $total;
        }

        if ($type === 'club_pph' && trim((string) ($input['club_pph_edit_reason'] ?? '')) === '') {
            throw new RuntimeException('Debes seleccionar el motivo de edición.');
        }
        if ($type === 'contactos') {
            $this->assertUniqueContact($id, $data, $before, (string) $config['table']);
        }

        $changes = [];
        foreach ($data as $field => $after) {
            $old = (string) ($before[$field] ?? '');
            $new = (string) $after;
            if ($old !== $new) {
                $changes[$field] = ['before' => $old, 'after' => $new];
            }
        }
        ksort($changes);
        return [
            'id' => $id,
            'config' => $config,
            'before' => $before,
            'changes' => $changes,
            'version' => (string) ($before['cct_modified'] ?? ''),
        ];
    }

    private function assertUniqueContact(int $id, array $data, array $before, string $table): void
    {
        $phone = (string) ($data['celular'] ?? $before['celular'] ?? '');
        $email = (string) ($data['correo'] ?? $before['correo'] ?? '');
        $parts = [];
        $params = [$id, Auth::authorId()];
        $types = 'ii';
        if ($phone !== '') {
            $parts[] = 'celular = ?';
            $params[] = $phone;
            $types .= 's';
        }
        if ($email !== '') {
            $parts[] = 'correo = ?';
            $params[] = $email;
            $types .= 's';
        }
        if ($parts !== [] && (int) Database::value(
            "SELECT COUNT(*) FROM {$table} WHERE _ID != ? AND cct_author_id = ? AND (" . implode(' OR ', $parts) . ')',
            $types,
            $params
        ) > 0) {
            throw new RuntimeException('Ya existe otro contacto con ese celular o correo.');
        }
    }

    private function presentChanges(array $config, array $changes): array
    {
        $labels = [];
        foreach (($config['campos'] ?? []) as $field) {
            if (!empty($field['id'])) {
                $labels[(string) $field['id']] = (string) ($field['label'] ?? $field['id']);
            }
        }
        $labels['pais'] = 'País';
        $labels['indicativo'] = 'Indicativo';
        $out = [];
        foreach ($changes as $field => $change) {
            $out[] = [
                'field' => $field,
                'label' => $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)),
                'before' => $change['before'],
                'after' => $change['after'],
            ];
        }
        return $out;
    }

    private function warnings(array $prepared, array $impact): array
    {
        $warnings = [];
        if ($prepared['changes'] === []) {
            $warnings[] = 'No se detectaron cambios.';
        }
        $excluded = array_sum(array_column($impact, 'excluded'));
        if ($excluded > 0) {
            $warnings[] = $excluded . ' registro(s) histórico(s) o cerrado(s) conservarán sus datos originales.';
        }
        return $warnings;
    }

    private function audit(string $type, int $id, array $prepared, array $impact, array $input): void
    {
        $table = Database::table('marketing_actor_change_log');
        if (!Database::tableExists($table)) {
            throw new RuntimeException('Falta ejecutar la migración del editor de actores.');
        }
        $before = [];
        $after = [];
        foreach ($prepared['changes'] as $field => $change) {
            $before[$field] = $change['before'];
            $after[$field] = $change['after'];
        }
        $data = [
            'actor_type' => $type,
            'actor_id' => $id,
            'changed_by' => Auth::authorId(),
            'changed_by_name' => (string) (Auth::user()['nombre'] ?? ''),
            'reason' => trim((string) ($input['club_pph_edit_reason'] ?? 'Actualización de datos')),
            'detail' => trim((string) ($input['club_pph_edit_detail'] ?? '')),
            'version_before' => $prepared['version'],
            'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'impact_json' => json_encode($impact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        if (Database::insert($table, $data) <= 0) {
            throw new RuntimeException('No fue posible guardar la auditoría de la edición.');
        }
    }

    private function changesHash(array $changes): string
    {
        return hash('sha256', json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encoded = rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
        return $encoded . '.' . hash_hmac('sha256', $encoded, $this->signingKey());
    }

    private function verifyToken(string $token): array
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, '');
        $expected = hash_hmac('sha256', $encoded, $this->signingKey());
        if ($encoded === '' || $signature === '' || !hash_equals($expected, $signature)) {
            throw new RuntimeException('La previsualización expiró o no es válida.');
        }
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $payload = json_decode((string) base64_decode(strtr($encoded, '-_', '+/'), true), true);
        if (!is_array($payload) || (int) ($payload['expires'] ?? 0) < time()) {
            throw new RuntimeException('La previsualización expiró. Revisa los cambios nuevamente.');
        }
        return $payload;
    }

    private function signingKey(): string
    {
        return hash('sha256', csrf_token() . '|' . session_id() . '|' . (string) app_config('auth.magic_login_token', ''));
    }
}
