<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class AdminRepository
{
    private const OPTION_DEFAULTS = [
        'gda_queue_paused' => 0, 'gda_force_active' => 0,
        'gda_email_max_daily' => 200, 'gda_sms_max_daily' => 50,
        'gda_onurix_client' => '', 'gda_onurix_key' => '',
        'gda_paused_users' => [], 'gda_role_permissions' => [],
    ];

    public function dashboard(): array
    {
        $options = [];
        foreach (self::OPTION_DEFAULTS as $key => $default) $options[$key] = $this->option($key, $default);
        return [
            'options' => $options,
            'doc_types' => $this->documentTypes(),
            'config_rows' => $this->configRows(),
            'promotion' => $this->promotionConfig(),
        ];
    }

    public function getOption(string $name, mixed $default = null): mixed { return $this->option($name, $default); }

    public function saveLimits(int $email, int $sms): void
    {
        if ($email < 1 || $sms < 1) throw new RuntimeException('Los límites deben ser mayores que cero.');
        $this->setOption('gda_email_max_daily', $email);
        $this->setOption('gda_sms_max_daily', $sms);
    }

    public function saveOnurix(string $client, string $key): void
    {
        if ($client === '' || $key === '') throw new RuntimeException('Client ID y API Key son obligatorios.');
        $this->upsertConfigValue('gda_onurix_client', $client);
        $this->upsertConfigValue('gda_onurix_key', $key);
        $this->setOption('gda_onurix_client', $client);
        $this->setOption('gda_onurix_key', $key);
    }

    public function toggleOption(string $name): int
    {
        if (!in_array($name, ['gda_queue_paused', 'gda_force_active'], true)) throw new RuntimeException('Opción no permitida.');
        $value = (int) !$this->option($name, 0);
        $this->setOption($name, $value);
        return $value;
    }

    public function toggleUser(int $userId): void
    {
        $paused = array_map('intval', (array) $this->option('gda_paused_users', []));
        $paused = in_array($userId, $paused, true) ? array_values(array_diff($paused, [$userId])) : array_values(array_unique([...$paused, $userId]));
        $this->setOption('gda_paused_users', $paused);
    }

    public function queueAction(int $userId, string $channel, string $operation): int
    {
        if (!in_array($channel, ['email', 'sms'], true) || !Database::tableExists('skc_notification_queue')) return 0;
        $where = "project_code = 'gestor-actores' AND status = 'pending' AND channel = ? AND gda_id_funcionario = ?";
        if ($operation === 'priority') {
            Database::execute("UPDATE skc_notification_queue SET priority = 10, updated_at = UTC_TIMESTAMP() WHERE {$where}", 'si', [$channel, $userId]);
        } elseif ($operation === 'delete') {
            Database::execute("DELETE FROM skc_notification_queue WHERE {$where}", 'si', [$channel, $userId]);
        } else throw new RuntimeException('Operación de cola no permitida.');
        return Database::connection()->affected_rows;
    }

    public function clearLogs(bool $todayOnly = false): int
    {
        $table = Database::table('jet_cct_envios_log');
        if (!Database::tableExists($table)) return 0;
        $sql = $todayOnly
            ? "DELETE FROM {$table} WHERE estado = 'Enviado' AND DATE(fecha_envio) = CURDATE()"
            : "DELETE FROM {$table}";
        Database::execute($sql);
        return Database::connection()->affected_rows;
    }

    public function savePermissions(array $permissions): void { $this->setOption('gda_role_permissions', $permissions); }

    public function addDocumentType(string $type): void
    {
        $table = Database::table('jet_cct_tipos_documentos');
        if ($type === '' || !Database::tableExists($table)) throw new RuntimeException('Tipo de documento inválido.');
        if ((int) Database::value("SELECT COUNT(*) FROM {$table} WHERE tipo = ?", 's', [$type]) > 0) throw new RuntimeException('Ese tipo ya existe.');
        Database::insert($table, ['tipo' => $type, 'cct_status' => 'publish']);
    }

    public function deleteDocumentType(int $id): void
    {
        $table = Database::table('jet_cct_tipos_documentos');
        if (Database::tableExists($table)) Database::execute("DELETE FROM {$table} WHERE _ID = ?", 'i', [$id]);
    }

    public function saveConfigRow(array $input): void
    {
        $table = Database::table('jet_cct_confi_sistema');
        if (!Database::tableExists($table)) throw new RuntimeException('Tabla de configuración no disponible.');

        $id = (int) ($input['id'] ?? 0);
        $funcion = trim((string) ($input['funcion'] ?? ''));
        if ($funcion === '') throw new RuntimeException('La función es obligatoria.');

        $columns = Database::columns($table);
        $data = [
            'funcion' => $funcion,
            'valor' => trim((string) ($input['valor'] ?? '')),
        ];
        if (array_key_exists('imagen', $input)) {
            $data['imagen'] = trim((string) ($input['imagen'] ?? ''));
        }
        if (isset($columns['cct_modified'])) $data['cct_modified'] = date('Y-m-d H:i:s');
        if (isset($columns['cct_status']) && $id <= 0) $data['cct_status'] = 'publish';
        if (isset($columns['cct_author_id']) && $id <= 0) $data['cct_author_id'] = (int) (Auth::user()['id'] ?? 0);
        $data = array_intersect_key($data, $columns);

        if ($id > 0) {
            Database::update($table, $data, $id);
            return;
        }

        if (isset($columns['cct_created'])) $data['cct_created'] = date('Y-m-d H:i:s');
        Database::insert($table, $data);
    }

    public function savePromotionConfig(array $input, array $files): void
    {
        $bannerUrl = $this->storeBannerUpload($files['banner_image'] ?? []);
        if ($bannerUrl !== '') {
            $this->upsertConfigValue('banner', $bannerUrl, $bannerUrl);
        }

        $manualBanner = trim((string) ($input['banner_url'] ?? ''));
        if ($bannerUrl === '' && $manualBanner !== '') {
            $this->upsertConfigValue('banner', $manualBanner, $manualBanner);
        }

        $linkRevista = trim((string) ($input['link_revista'] ?? ''));
        $this->upsertConfigValue('link_revista', $linkRevista, '');
    }

    public function deleteConfigRow(int $id): void
    {
        $table = Database::table('jet_cct_confi_sistema');
        if ($id > 0 && Database::tableExists($table)) {
            Database::execute("DELETE FROM {$table} WHERE _ID = ?", 'i', [$id]);
        }
    }

    private function queueUsers(): array
    {
        if (!Database::tableExists('skc_notification_queue')) return [];
        $rows = Database::rows(
            "SELECT gda_id_funcionario user_id,
                    MAX(JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.gda.nombre_funcionario'))) user_name,
                    SUM(status='pending' AND channel='email') pending_emails,
                    SUM(status='pending' AND channel='sms') pending_sms,
                    SUM(status='sent' AND channel='email' AND DATE(COALESCE(sent_at, updated_at))=CURDATE()) sent_email_today,
                    SUM(status='sent' AND channel='sms' AND DATE(COALESCE(sent_at, updated_at))=CURDATE()) sent_sms_today
               FROM skc_notification_queue
              WHERE project_code='gestor-actores'
                AND (status='pending' OR (status='sent' AND DATE(COALESCE(sent_at, updated_at))=CURDATE()))
              GROUP BY user_id
              ORDER BY user_name"
        );
        $paused = array_map('intval', (array) $this->option('gda_paused_users', []));
        foreach ($rows as &$row) $row['paused'] = in_array((int) $row['user_id'], $paused, true);
        return $rows;
    }

    private function roles(): array
    {
        $table = Database::table('jet_cct_funcionarios');
        return Database::tableExists($table) ? Database::rows("SELECT DISTINCT rol FROM {$table} WHERE rol IS NOT NULL AND rol <> '' ORDER BY rol") : [];
    }

    private function modules(): array
    {
        return ['dashboard'=>'Dashboard','campanas'=>'Campañas','contactos'=>'Contactos','suscriptores'=>'Suscriptores','clientes'=>'Clientes','club_pph'=>'Club PPH','propietarios'=>'Propietarios','arrendatarios'=>'Arrendatarios','codeudores'=>'Codeudores','copropiedades'=>'Copropiedades','proveedores'=>'Proveedores','funcionarios'=>'Funcionarios','plantillas'=>'Plantillas','inventario'=>'Inventario de souvenirs','redes'=>'Redes sociales','planificador'=>'Planificador','reuniones'=>'Reuniones','confi_sistema'=>'Configuración'];
    }

    private function documentTypes(): array
    {
        $table = Database::table('jet_cct_tipos_documentos');
        return Database::tableExists($table) ? Database::rows("SELECT _ID, tipo FROM {$table} WHERE cct_status='publish' ORDER BY tipo") : [];
    }

    private function configRows(): array
    {
        $table = Database::table('jet_cct_confi_sistema');
        if (!Database::tableExists($table)) return [];
        return Database::rows("SELECT _ID, funcion, valor, imagen FROM {$table} ORDER BY funcion ASC, _ID DESC");
    }

    private function promotionConfig(): array
    {
        return [
            'banner' => $this->configValue('banner'),
            'link_revista' => $this->configValue('link_revista'),
        ];
    }

    private function configValue(string $function): string
    {
        $table = Database::table('jet_cct_confi_sistema');
        if (!Database::tableExists($table)) return '';
        return trim((string) Database::value(
            "SELECT COALESCE(NULLIF(imagen, ''), NULLIF(valor, '')) FROM {$table} WHERE funcion = ? ORDER BY _ID DESC LIMIT 1",
            's',
            [$function]
        ));
    }

    private function upsertConfigValue(string $function, string $value, string $image = ''): void
    {
        $table = Database::table('jet_cct_confi_sistema');
        if (!Database::tableExists($table)) throw new RuntimeException('Tabla de configuración no disponible.');

        $existing = Database::one("SELECT _ID FROM {$table} WHERE funcion = ? LIMIT 1", 's', [$function]);
        $columns = Database::columns($table);
        $data = ['funcion' => $function, 'valor' => $value, 'imagen' => $image];
        if (isset($columns['cct_modified'])) $data['cct_modified'] = date('Y-m-d H:i:s');
        if (!$existing && isset($columns['cct_status'])) $data['cct_status'] = 'publish';
        if (!$existing && isset($columns['cct_author_id'])) $data['cct_author_id'] = (int) (Auth::user()['id'] ?? 0);
        $data = array_intersect_key($data, $columns);

        if ($existing) {
            Database::update($table, $data, (int) $existing['_ID']);
            return;
        }

        if (isset($columns['cct_created'])) $data['cct_created'] = date('Y-m-d H:i:s');
        Database::insert($table, $data);
    }

    private function storeBannerUpload(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('No fue posible subir el banner.');
        }

        $tmp = (string) $file['tmp_name'];
        $info = @getimagesize($tmp);
        if (!$info || !in_array((int) $info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('El banner debe ser JPG, PNG o WEBP.');
        }

        $uploadDir = dirname(__DIR__) . '/public/uploads/config';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('No fue posible crear la carpeta de uploads.');
        }

        $canCompress = function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
        $sourceExt = match ((int) $info[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
        $filename = 'banner-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . ($canCompress ? 'jpg' : $sourceExt);
        $dest = $uploadDir . '/' . $filename;
        $compressed = $canCompress && $this->compressImage($tmp, $dest, (int) $info[2], 1600, 78);
        if (!$compressed && !move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('No fue posible guardar el banner.');
        }

        return $this->publicUploadUrl('/uploads/config/' . $filename);
    }

    private function compressImage(string $source, string $dest, int $type, int $maxWidth, int $quality): bool
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) return false;

        $image = match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($source) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if (!$image) return false;

        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round(($height / max(1, $width)) * $targetWidth);
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $ok = imagejpeg($canvas, $dest, $quality);
        imagedestroy($image);
        imagedestroy($canvas);
        return $ok;
    }

    private function publicUploadUrl(string $path): string
    {
        $public = rtrim((string) app_config('app.public_url', ''), '/');
        if ($public !== '') return $public . $path;

        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080'));
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host . clean_url_base() . $path;
    }

    private function option(string $name, mixed $default = null): mixed
    {
        $table = Database::table('options');
        if (!Database::tableExists($table)) return $default;
        $raw = Database::value("SELECT option_value FROM {$table} WHERE option_name = ? LIMIT 1", 's', [$name]);
        if ($raw === null) return $default;
        $decoded = @unserialize((string) $raw, ['allowed_classes' => false]);
        return $decoded !== false || $raw === 'b:0;' ? $decoded : $raw;
    }

    private function setOption(string $name, mixed $value): void
    {
        $table = Database::table('options');
        $encoded = is_array($value) ? serialize($value) : (string) $value;
        if ((int) Database::value("SELECT COUNT(*) FROM {$table} WHERE option_name=?", 's', [$name])) {
            Database::execute("UPDATE {$table} SET option_value=? WHERE option_name=?", 'ss', [$encoded, $name]);
        } else Database::insert($table, ['option_name'=>$name, 'option_value'=>$encoded, 'autoload'=>'yes']);
    }
}
