<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class CampaignEngine
{
    public const CANALES_VALIDOS = ['email', 'sms', 'whatsapp'];
    public const SMS_MAX = 160;
    public const SMS_PROVIDER_PREFIX = 'SKC SuCasa Inmobiliaria ';
    private array $scheduledBase = [];

    public function send(
        string $type,
        array $ids,
        string $channel,
        string $tag,
        string $subject,
        string $message,
        string $categoria = 'info',
        array $template = [],
        ?string $attachmentPath = null,
        bool $force = false
    ): array {
        $channel = strtolower($channel);
        $categoria = ActorPreferences::normalizeCategoria($categoria);
        $tag = $this->normalizeTag($tag);
        if (!in_array($channel, self::CANALES_VALIDOS, true)) {
            throw new RuntimeException('Canal no soportado.');
        }
        if (trim($tag) === '') {
            throw new RuntimeException('La campaña requiere tag.');
        }
        if ($ids === []) {
            throw new RuntimeException('Selecciona al menos un destinatario.');
        }
        if (trim($message) === '') {
            throw new RuntimeException('El mensaje no puede estar vacío.');
        }
        if ($channel === 'email' && trim($subject) === '') {
            throw new RuntimeException('El asunto es obligatorio para correo electrónico.');
        }
        if ($channel === 'sms' && mb_strlen(self::SMS_PROVIDER_PREFIX . $message) > self::SMS_MAX) {
            throw new RuntimeException('SMS excede ' . self::SMS_MAX . ' caracteres.');
        }
        if ($channel === 'whatsapp' && trim((string) ($template['official_template'] ?? '')) === '') {
            throw new RuntimeException('WhatsApp oficial requiere una plantilla aprobada de Meta.');
        }

        $repo = new ActorRepository();
        $recipients = $repo->recipients($type, $ids, $channel);
        $user = Auth::user();
        $queuedActorIds = [];
        $campaignPaused = $this->isCampaignPaused($tag);

        $queued = 0;
        $failed = 0;
        $filtered = 0;
        $duplicates = 0;
        $excluded = 0;

        if ($tag !== '') {
            $configTable = Database::table('jet_cct_campaign_config');
            $exists = Database::tableExists($configTable)
                ? (int) Database::value("SELECT COUNT(*) FROM {$configTable} WHERE campaign_tag = ?", 's', [$tag])
                : 0;
            if ($exists === 0) {
                (new CampaignRepository())->create($tag);
            }
        }

        foreach ($recipients as $recipient) {
            $actorId = (int) $recipient['_ID'];
            if ($tag !== '' && $this->isDuplicateForCampaign($actorId, $tag, $channel, $type)) {
                $duplicates++;
                continue;
            }

            if ($tag !== '' && $this->isExcludedFromCampaign($actorId, $tag, $type)) {
                $excluded++;
                continue;
            }

            if (!ActorPreferences::permite($recipient, $categoria, $channel, $type) && !$force) {
                $filtered++;
                continue;
            }

            $contact = $this->resolveDestination($recipient, $channel);
            if ($contact === '') {
                $failed++;
                continue;
            }

            $resolved = $this->resolveVariables($message, $recipient, $type);
            $resolvedSubject = $this->resolveVariables($subject, $recipient, $type);
            $whatsAppPayload = [];
            if ($channel === 'whatsapp') {
                $whatsAppPayload = $this->buildWhatsAppTemplatePayload($template, $recipient, $type);
            }
            $trackingToken = bin2hex(random_bytes(16));
            if ($channel === 'email') {
                $origin = rtrim((string) app_config('app.public_url', ''), '/');
                if ($origin === '') {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $origin = $scheme . '://' . preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                }
                $pixelUrl = $origin . url(['action' => 'track-email', 'token' => $trackingToken]);
                $resolved .= '<img src="' . htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0" />';
            }

            $ok = $this->enqueue($channel, [
                'destination' => $contact,
                'destination_name' => (string) ($recipient['nombre'] ?? ''),
                'subject' => $channel === 'email' ? $resolvedSubject : '',
                'message' => $resolved,
                'id_actor' => $actorId,
                'tipo_actor' => $type,
                'campaign_tag' => $tag,
                'categoria' => $categoria,
                'template_id' => (int) ($template['id'] ?? 0),
                'template_name' => (string) ($template['name'] ?? ''),
                'official_template' => (string) ($template['official_template'] ?? ''),
                'template_language' => (string) ($template['language'] ?? 'es_CO'),
                'whatsapp_payload' => $whatsAppPayload,
                'tracking_token' => $trackingToken,
                'attachment_path' => $attachmentPath,
                'id_funcionario' => (int) ($user['id'] ?? 0),
                'nombre_funcionario' => (string) ($user['nombre'] ?? 'Sistema'),
                'status' => $campaignPaused ? 'paused' : 'pending',
                'scheduled_at' => $this->scheduledAt($tag, $channel, $queued, $force),
            ]);

            if ($ok) {
                $queued++;
                $queuedActorIds[] = $actorId;
            } else {
                $failed++;
            }
        }

        if ($tag !== '' && $queuedActorIds !== []) {
            $this->recordBatch($tag, $channel, $queuedActorIds, (int) ($user['id'] ?? 0));
        }

        return [
            'queued' => $queued,
            'failed' => $failed,
            'filtered' => $filtered,
            'duplicates' => $duplicates,
            'excluded' => $excluded,
            'total' => count($recipients),
            'selected' => count(array_unique(array_map('intval', $ids))),
            'not_found' => max(0, count(array_unique(array_map('intval', $ids))) - count($recipients)),
            'tag' => $tag,
            'channel' => $channel,
            'categoria' => $categoria,
        ];
    }

    public function resolveVariables(string $template, array $actor, string $type): string
    {
        $rolPersona = (string) ($actor['rol_persona'] ?? ActorCatalog::get($type)['rol_persona'] ?? 'Contacto');
        $map = [
            '{{nombre}}' => (string) ($actor['nombre'] ?? $actor['proveedor'] ?? $actor['copropiedad'] ?? ''),
            '{{rol_persona}}' => $rolPersona,
            '{{correo}}' => (string) ($actor['correo'] ?? $actor['correo_proveedor'] ?? ''),
            '{{celular}}' => $this->resolveDestination($actor, 'sms'),
            '{{documento}}' => (string) ($actor['documento'] ?? ''),
            '{{ciudad}}' => (string) ($actor['ciudad'] ?? ''),
            '{{indicativo}}' => (string) ($actor['indicativo'] ?? ''),
            '{{tipo_documento}}' => (string) ($actor['tipo_documento'] ?? ''),
            '{{link}}' => (string) ($actor['link'] ?? $actor['url'] ?? ''),
            '{{custom_message}}' => '',
        ];
        foreach ($actor as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $map['{{' . $key . '}}'] = trim((string) ($value ?? ''));
            }
        }
        return strtr($template, $map);
    }

    private function normalizeTag(string $tag): string
    {
        $tag = preg_replace('/\s+/', ' ', trim($tag)) ?? '';
        return mb_substr($tag, 0, 120);
    }

    private function resolveDestination(array $recipient, string $channel): string
    {
        if ($channel === 'email') {
            $email = trim((string) ($recipient['contacto'] ?? $recipient['correo'] ?? $recipient['correo_proveedor'] ?? ''));
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        }

        $phone = (string) ($recipient['contacto'] ?? $recipient['celular'] ?? $recipient['celular_proveedor'] ?? $recipient['contacto'] ?? $recipient['telefono'] ?? '');
        return ActorPhone::clean($phone);
    }

    private function buildWhatsAppTemplatePayload(array $template, array $recipient, string $type): array
    {
        $templateName = trim((string) ($template['official_template'] ?? ''));
        if ($templateName === '') {
            throw new RuntimeException('La plantilla WhatsApp no tiene nombre oficial de Meta.');
        }

        $components = [];
        $headerType = strtolower(trim((string) ($template['header_type'] ?? 'none')));
        if (in_array($headerType, ['image', 'video', 'document'], true)) {
            $headerUrl = trim($this->resolveVariables((string) ($template['header_url'] ?? ''), $recipient, $type));
            if ($headerUrl === '') {
                throw new RuntimeException('La plantilla WhatsApp requiere URL pública para el header ' . $headerType . '.');
            }
            if (!preg_match('/^https:\/\//i', $headerUrl)) {
                throw new RuntimeException('La URL del header WhatsApp debe ser HTTPS pública.');
            }

            $media = ['link' => $headerUrl];
            if ($headerType === 'document') {
                $filename = trim($this->resolveVariables((string) ($template['header_filename'] ?? ''), $recipient, $type));
                if ($filename !== '') {
                    $media['filename'] = $filename;
                }
            }

            $components[] = [
                'type' => 'header',
                'parameters' => [[
                    'type' => $headerType,
                    $headerType => $media,
                ]],
            ];
        }

        $bodyTemplate = (string) ($template['body_text'] ?? '');
        $bodyParameters = [];
        foreach ($this->extractTemplateTokens($bodyTemplate) as $token) {
            $value = trim($this->resolveVariables('{{' . $token . '}}', $recipient, $type));
            $bodyParameters[] = ['type' => 'text', 'text' => $value !== '' ? $value : '-'];
        }
        if ($bodyParameters !== []) {
            $components[] = ['type' => 'body', 'parameters' => $bodyParameters];
        }

        if (($template['button_type'] ?? 'none') === 'url_dynamic') {
            $buttonValue = trim($this->resolveVariables((string) ($template['button_url_parameter'] ?? ''), $recipient, $type));
            if ($buttonValue !== '') {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [['type' => 'text', 'text' => $buttonValue]],
                ];
            }
        }

        return [
            'type' => 'template',
            'template_name' => $templateName,
            'template_language' => trim((string) ($template['language'] ?? 'es_CO')) ?: 'es_CO',
            'components' => $components,
        ];
    }

    private function extractTemplateTokens(string $template): array
    {
        preg_match_all('/{{\s*([A-Za-z_][A-Za-z0-9_]*)\s*}}/', $template, $matches);
        return $matches[1] ?? [];
    }

    private function isDuplicateForCampaign(int $actorId, string $tag, string $channel, string $type): bool
    {
        if ($actorId <= 0 || $tag === '') {
            return false;
        }

        $queued = Database::tableExists('skc_notification_queue')
            ? (int) Database::value(
                "SELECT COUNT(*) FROM skc_notification_queue
                 WHERE project_code = 'gestor-actores'
                   AND channel = ?
                   AND gda_campaign_tag = ?
                   AND gda_tipo_actor = ?
                   AND gda_id_actor = ?",
                'sssi',
                [$channel, $tag, $type, $actorId]
            )
            : 0;

        if ($queued > 0) {
            return true;
        }

        return false;
    }

    private function isExcludedFromCampaign(int $actorId, string $tag, string $type): bool
    {
        $table = Database::table('jet_cct_campaign_exclusions');
        if ($actorId <= 0 || $tag === '' || !Database::tableExists($table)) {
            return false;
        }

        return (int) Database::value(
            "SELECT COUNT(*) FROM {$table} WHERE campaign_tag = ? AND id_actor = ? AND tipo_actor = ?",
            'sis',
            [$tag, $actorId, $type]
        ) > 0;
    }

    private function recordBatch(string $tag, string $channel, array $actorIds, int $userId): void
    {
        $table = Database::table('jet_cct_campaign_batches');
        if (!Database::tableExists($table)) {
            return;
        }

        $nextBatch = (int) Database::value(
            "SELECT COALESCE(MAX(batch_number), 0) + 1 FROM {$table} WHERE campaign_tag = ? AND channel = ?",
            'ss',
            [$tag, $channel]
        );

        Database::insert($table, [
            'campaign_tag' => $tag,
            'batch_number' => max(1, $nextBatch),
            'actor_ids' => json_encode(array_values(array_unique(array_map('intval', $actorIds))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'batch_size' => count($actorIds),
            'total_actors' => count($actorIds),
            'sent_count' => 0,
            'failed_count' => 0,
            'channel' => $channel,
            'id_funcionario' => $userId,
            'cct_created' => gmdate('Y-m-d H:i:s'),
            'cct_modified' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function enqueue(string $channel, array $payload): bool
    {
        if (!Database::tableExists('skc_notification_queue')) {
            return false;
        }

        $meta = [
            'gda' => [
                'id_funcionario' => $payload['id_funcionario'],
                'id_actor' => $payload['id_actor'],
                'tipo_actor' => $payload['tipo_actor'],
                'campaign_tag' => $payload['campaign_tag'],
                'categoria' => $payload['categoria'] ?? 'info',
                'categoria_mensaje' => $payload['categoria'] ?? 'info',
                'template_id' => (int) ($payload['template_id'] ?? 0),
                'template_name' => (string) ($payload['template_name'] ?? ''),
                'official_template' => (string) ($payload['official_template'] ?? ''),
                'template_language' => (string) ($payload['template_language'] ?? ''),
                'nombre_funcionario' => $payload['nombre_funcionario'],
                'tracking_token' => $payload['tracking_token'] ?? '',
                'attachment_path' => $payload['attachment_path'] ?? null,
                'source_module' => 'dashboard-marketing',
            ],
        ];

        $attachmentPath = trim((string) ($payload['attachment_path'] ?? ''));
        $payloadJson = $attachmentPath !== '' ? [
            'attachments' => [[
                'path' => $attachmentPath,
                'name' => basename($attachmentPath),
            ]],
        ] : [];
        if ($channel === 'whatsapp') {
            $payloadJson = (array) ($payload['whatsapp_payload'] ?? []);
        }

        $queueData = [
            'project_code' => 'gestor-actores',
            'source_module' => 'dashboard-marketing',
            'channel' => $channel,
            'provider' => match ($channel) {
                'sms' => 'sms_onurix',
                'whatsapp' => 'whatsapp_official',
                default => 'email_smtp',
            },
            'destination' => $payload['destination'],
            'destination_name' => $payload['destination_name'],
            'subject' => $payload['subject'],
            'message_html' => $channel === 'email' ? $payload['message'] : '',
            'message_text' => strip_tags($payload['message']),
            'template_name' => $channel === 'whatsapp'
                ? (string) ($payload['official_template'] ?? '')
                : (string) ($payload['template_name'] ?? ''),
            'template_language' => $channel === 'whatsapp' ? (string) ($payload['template_language'] ?? 'es_CO') : '',
            'payload_json' => json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'priority' => '100',
            'created_by' => 'dashboard-marketing',
            'scheduled_at' => (string) ($payload['scheduled_at'] ?? gmdate('Y-m-d H:i:s')),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'dedupe_key' => implode(':', [
                'gestor-actores', 'campaign', md5((string) $payload['campaign_tag']), $channel,
                (string) $payload['tipo_actor'], (int) $payload['id_actor'],
            ]),
        ];

        $queueData['status'] = in_array((string) ($payload['status'] ?? 'pending'), ['pending', 'paused'], true)
            ? (string) ($payload['status'] ?? 'pending')
            : 'pending';

        $columns = Database::columns('skc_notification_queue');
        $filtered = array_intersect_key($queueData, $columns);
        $inserted = Database::insert('skc_notification_queue', $filtered);
        $tracking = Database::table('jet_cct_email_tracking');
        if ($inserted > 0 && $channel === 'email' && Database::tableExists($tracking)) {
            Database::insert($tracking, [
                'tracking_token'=>(string) ($payload['tracking_token'] ?? ''), 'queue_id'=>$inserted,
                'id_actor'=>(int) $payload['id_actor'], 'id_funcionario'=>(int) $payload['id_funcionario'],
                'tipo_actor'=>(string) $payload['tipo_actor'], 'campaign_tag'=>(string) $payload['campaign_tag'],
                'destination'=>(string) $payload['destination'], 'subject'=>(string) $payload['subject'],
                'open_count'=>0, 'created_at'=>gmdate('Y-m-d H:i:s'),
            ]);
        }
        return $inserted > 0;
    }

    private function isCampaignPaused(string $tag): bool
    {
        $table = Database::table('jet_cct_campaign_config');
        if ($tag === '' || !Database::tableExists($table) || !Database::columnExists($table, 'status')) {
            return false;
        }
        return strtolower((string) Database::value("SELECT status FROM {$table} WHERE campaign_tag=? LIMIT 1", 's', [$tag])) === 'paused';
    }

    private function scheduledAt(string $tag, string $channel, int $newPosition, bool $force): string
    {
        if ($force) return gmdate('Y-m-d H:i:s');

        $configTable = Database::table('jet_cct_campaign_config');
        $config = Database::tableExists($configTable)
            ? Database::one("SELECT batch_size,batch_interval_minutes,max_per_day FROM {$configTable} WHERE campaign_tag=? LIMIT 1", 's', [$tag])
            : [];
        $batchSize = max(1, (int) ($config['batch_size'] ?? 200));
        $interval = max(1, (int) ($config['batch_interval_minutes'] ?? 15));
        $maxPerDay = max(1, (int) ($config['max_per_day'] ?? 1000));

        $cacheKey = $channel . '|' . $tag;
        if (!isset($this->scheduledBase[$cacheKey])) {
            $this->scheduledBase[$cacheKey] = (int) Database::value(
                "SELECT COUNT(*) FROM skc_notification_queue WHERE project_code='gestor-actores' AND channel=? AND status IN ('pending','processing','paused') AND gda_campaign_tag=?",
                'ss',
                [$channel, $tag]
            );
        }
        $alreadyQueued = $this->scheduledBase[$cacheKey];
        $position = $alreadyQueued + $newPosition;
        $dayOffset = intdiv($position, $maxPerDay);
        $withinDay = $position % $maxPerDay;
        $batchOffset = intdiv($withinDay, $batchSize) * $interval;

        $local = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
        if ((int) $local->format('N') === 7) {
            $local = $local->modify('next monday 07:00:00');
        } elseif ((int) $local->format('H') >= 19) {
            $local = $local->modify('+1 day')->setTime(7, 0);
            if ((int) $local->format('N') === 7) $local = $local->modify('+1 day');
        } elseif ((int) $local->format('H') < 7) {
            $local = $local->setTime(7, 0);
        }
        while ($dayOffset-- > 0) {
            $local = $local->modify('+1 day')->setTime(7, 0);
            if ((int) $local->format('N') === 7) $local = $local->modify('+1 day');
        }
        $local = $local->modify('+' . $batchOffset . ' minutes');
        if ((int) $local->format('H') >= 19) {
            $local = $local->modify('+1 day')->setTime(7, 0);
            if ((int) $local->format('N') === 7) $local = $local->modify('+1 day');
        }
        return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function pause(string $tag, bool $pause): bool
    {
        $table = Database::table('jet_cct_campaign_config');
        if (!Database::tableExists($table)) {
            return false;
        }
        $status = $pause ? 'paused' : 'active';
        $exists = Database::value("SELECT _ID FROM {$table} WHERE campaign_tag = ?", 's', [$tag]);
        $saved = $exists
            ? Database::execute("UPDATE {$table} SET status = ?, cct_modified = ? WHERE campaign_tag = ?", 'sss', [$status, gmdate('Y-m-d H:i:s'), $tag])
            : Database::insert($table, [
            'campaign_tag' => $tag,
            'status' => $status,
            'cct_created' => gmdate('Y-m-d H:i:s'),
            'cct_modified' => gmdate('Y-m-d H:i:s'),
        ]) > 0;

        if (Database::tableExists('skc_notification_queue')) {
            if ($pause) {
                Database::execute(
                    "UPDATE skc_notification_queue SET status='paused', updated_at=? WHERE project_code='gestor-actores' AND status='pending' AND gda_campaign_tag=?",
                    'ss',
                    [gmdate('Y-m-d H:i:s'), $tag]
                );
            } else {
                Database::execute(
                    "UPDATE skc_notification_queue SET status='pending', updated_at=? WHERE project_code='gestor-actores' AND status='paused' AND gda_campaign_tag=?",
                    'ss',
                    [gmdate('Y-m-d H:i:s'), $tag]
                );
            }
        }
        return (bool) $saved;
    }

    public function delete(string $tag): array
    {
        $queue = 'skc_notification_queue';
        $config = Database::table('jet_cct_campaign_config');
        $batches = Database::table('jet_cct_campaign_batches');
        $exclusions = Database::table('jet_cct_campaign_exclusions');

        $deleted = 0;
        if (Database::tableExists($queue)) {
            $deleted = (int) Database::execute("DELETE FROM {$queue} WHERE project_code='gestor-actores' AND gda_campaign_tag = ?", 's', [$tag]);
        }
        if (Database::tableExists($config)) {
            Database::execute("DELETE FROM {$config} WHERE campaign_tag = ?", 's', [$tag]);
        }
        if (Database::tableExists($batches)) {
            Database::execute("DELETE FROM {$batches} WHERE campaign_tag = ?", 's', [$tag]);
        }
        if (Database::tableExists($exclusions)) {
            Database::execute("DELETE FROM {$exclusions} WHERE campaign_tag = ?", 's', [$tag]);
        }
        return ['deleted' => $deleted];
    }

    public function retryFailed(string $tag, string $channel): int
    {
        $queue = 'skc_notification_queue';
        if (!Database::tableExists($queue)) {
            return 0;
        }
        $channel = strtolower(trim($channel));
        $channelWhere = in_array($channel, self::CANALES_VALIDOS, true) ? ' AND channel=?' : '';
        $types = 'ss' . ($channelWhere !== '' ? 's' : '');
        $params = [gmdate('Y-m-d H:i:s'), $tag];
        if ($channelWhere !== '') $params[] = $channel;
        $before = (int) Database::value(
            "SELECT COUNT(*) FROM {$queue} WHERE project_code='gestor-actores' AND gda_campaign_tag=? AND status='failed'{$channelWhere}",
            's' . ($channelWhere !== '' ? 's' : ''),
            $channelWhere !== '' ? [$tag, $channel] : [$tag]
        );
        Database::execute(
            "UPDATE {$queue} SET status='pending', attempts=0, next_attempt_at=NULL, locked_at=NULL, locked_by=NULL, last_error=NULL, updated_at=? WHERE project_code='gestor-actores' AND gda_campaign_tag=? AND status='failed'{$channelWhere}",
            $types,
            $params
        );
        return $before;
    }

    public function stats(string $tag): array
    {
        $queue = 'skc_notification_queue';
        if (!Database::tableExists($queue)) {
            return ['pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0];
        }
        $rows = Database::rows(
            "SELECT status, COUNT(*) AS total FROM {$queue}
             WHERE project_code='gestor-actores' AND gda_campaign_tag = ?
             GROUP BY status",
            's',
            [$tag]
        );
        $out = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['total'];
            $out[$status] = $count;
            $out['total'] += $count;
        }
        return $out;
    }

    private static function recordExclusion(string $tag, int $actorId, string $type, string $reason): void
    {
        $table = Database::table('jet_cct_campaign_exclusions');
        if (!Database::tableExists($table)) {
            return;
        }
        try {
            Database::execute(
                "INSERT IGNORE INTO {$table} (campaign_tag, id_actor, tipo_actor, reason, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)",
                'sissis',
                [$tag, $actorId, $type, $reason, (int) (Auth::user()['id'] ?? 0), gmdate('Y-m-d H:i:s')]
            );
        } catch (Throwable) {
        }
    }
}
