<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class ActorImporter
{
    public function importFromUpload(string $type, array $file, int $authorId = 0): array
    {
        $config = ActorCatalog::get($type);
        if (!$config) {
            throw new RuntimeException('Tipo de actor no soportado.');
        }
        if (empty($config['permitir_import'])) {
            throw new RuntimeException('La importación no está permitida para este actor.');
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new RuntimeException('Archivo no válido.');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new RuntimeException('No se pudo abrir el archivo.');
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, ',');
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('No se encontraron encabezados.');
        }
        $headers = array_map(static fn ($h) => strtolower(trim((string) $h)), $headers);

        $fieldMap = ActorCatalog::fieldMap($type);
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];
        $lineNo = 1;

        $table = $config['table'];
        if (!Database::tableExists($table)) {
            fclose($handle);
            throw new RuntimeException('Tabla no existe: ' . $table);
        }
        $columns = Database::columns($table);

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $lineNo++;
            $data = [];
            foreach ($headers as $i => $header) {
                $value = $row[$i] ?? '';
                $data[$header] = is_string($value) ? trim($value) : $value;
            }
            $mapped = $this->mapRow($data, $fieldMap);
            if ($mapped === null) {
                $skipped++;
                $errors[] = "Línea {$lineNo}: sin nombre";
                continue;
            }

            if ($type === 'contactos') {
                $mapped['cct_author_id'] = $authorId;
                $mapped['id_dueno'] = $authorId;
                if ($this->existsDuplicado('contactos', $mapped, $authorId)) {
                    $duplicates++;
                    continue;
                }
            }

            if (!empty($config['force_tipo_actor']) && isset($columns['tipo_actor'])) {
                $mapped['tipo_actor'] = (string) $config['force_tipo_actor'];
            }
            foreach (ActorPreferences::CATEGORIAS as $categoria) {
                foreach (ActorPreferences::CANALES as $canal) {
                    $field = 'permite_' . $categoria . '_' . $canal;
                    if (isset($columns[$field]) && !array_key_exists($field, $mapped)) {
                        $mapped[$field] = 1;
                    }
                }
            }
            if (array_key_exists('pais', $mapped) || array_key_exists('indicativo', $mapped)) {
                $countryId = CountryCatalog::selectedId(
                    (string) ($mapped['pais'] ?? ''),
                    (string) ($mapped['indicativo'] ?? '')
                );
                $country = $countryId > 0 ? CountryCatalog::find($countryId) : [];
                if ($country !== []) {
                    $mapped['pais'] = (string) $country['name'];
                    $mapped['indicativo'] = (string) $country['calling_code'];
                } elseif (isset($mapped['indicativo'])) {
                    $mapped['indicativo'] = CountryCatalog::normalizeCallingCode((string) $mapped['indicativo']);
                }
                if (isset($mapped['celular'])) {
                    $mapped['celular'] = ActorPhone::national((string) $mapped['celular'], (string) ($mapped['indicativo'] ?? ''));
                }
            }

            $mapped['cct_status'] = 'publish';
            $mapped['cct_created'] = gmdate('Y-m-d H:i:s');
            $mapped['cct_modified'] = gmdate('Y-m-d H:i:s');

            Database::insert($table, array_intersect_key($mapped, $columns));
            $inserted++;
        }
        fclose($handle);

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }

    private function mapRow(array $row, array $fieldMap): ?array
    {
        $mapped = [];
        foreach ($row as $key => $value) {
            $cleanKey = strtolower(trim((string) $key));
            if (isset($fieldMap[$cleanKey])) {
                $mapped[$cleanKey] = $cleanKey === 'celular' ? ActorPhone::clean((string) $value) : (string) $value;
            }
        }
        if (empty($mapped['nombre']) && empty($mapped['proveedor']) && empty($mapped['copropiedad'])) {
            return null;
        }
        return $mapped;
    }

    private function existsDuplicado(string $type, array $mapped, int $authorId): bool
    {
        $table = Database::table('jet_cct_contactos');
        $contacts = [];
        $params = [$authorId];
        $types = 'i';

        if (!empty($mapped['celular'])) {
            $contacts[] = 'celular = ?';
            $params[] = $mapped['celular'];
            $types .= 's';
        }
        if (!empty($mapped['correo'])) {
            $contacts[] = 'correo = ?';
            $params[] = $mapped['correo'];
            $types .= 's';
        }
        if ($contacts === []) return false;
        $where = 'cct_author_id = ? AND (' . implode(' OR ', $contacts) . ')';
        $sql = "SELECT _ID FROM {$table} WHERE {$where} LIMIT 1";
        $row = Database::one($sql, $types, $params);
        return (bool) $row;
    }
}
