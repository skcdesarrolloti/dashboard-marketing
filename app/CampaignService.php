<?php

declare(strict_types=1);

namespace App;

final class CampaignService
{
    public function sendTest(string $email, string $subject, string $message, int $templateId, string $templateName): bool
    {
        if (!Database::tableExists('skc_notification_queue')) return false;
        $meta = ['gda'=>['id_funcionario'=>(int)(Auth::user()['id']??0),'id_actor'=>0,'tipo_actor'=>'prueba','campaign_tag'=>'prueba-plantilla','template_id'=>$templateId,'template_name'=>$templateName,'source_module'=>'template-test']];
        $data = ['project_code'=>'gestor-actores','source_module'=>'template-test','channel'=>'email','provider'=>'email_smtp','destination'=>$email,'destination_name'=>'Usuario de prueba','subject'=>$subject,'message_html'=>$message,'message_text'=>strip_tags($message),'template_name'=>$templateName,'payload_json'=>'{}','meta_json'=>json_encode($meta, JSON_UNESCAPED_UNICODE),'status'=>'pending','priority'=>'10','created_by'=>'dashboard-marketing','scheduled_at'=>gmdate('Y-m-d H:i:s'),'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')];
        $inserted = Database::insert('skc_notification_queue', array_intersect_key($data, Database::columns('skc_notification_queue'))) > 0;
        if ($inserted) {
            ReportCache::forget('campaign_list');
        }
        return $inserted;
    }
    public function list(): array
    {
        return ReportCache::remember('campaign_list', [(string) app_config('db.database', '')], 20, static function (): array {
            $queue = 'skc_notification_queue';
            $items = [];

            if (Database::tableExists($queue)) {
                $rows = Database::rows(
                    "SELECT gda_campaign_tag AS tag,
                        SUM(CASE WHEN channel = 'email' THEN 1 ELSE 0 END) AS email_total,
                        SUM(CASE WHEN channel = 'sms' THEN 1 ELSE 0 END) AS sms_total,
                        SUM(CASE WHEN status NOT IN ('sent', 'failed') THEN 1 ELSE 0 END) AS pending_total,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_total,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_total,
                        MAX(created_at) AS last_activity
                   FROM {$queue}
                  WHERE project_code = 'gestor-actores'
                    AND gda_campaign_tag IS NOT NULL AND gda_campaign_tag != ''
                  GROUP BY tag
                  ORDER BY last_activity DESC"
                );
                foreach ($rows as $row) {
                    $tag = (string) $row['tag'];
                    $items[$tag] = $row;
                }
            }

            return array_values($items);
        });
    }

    public function listForActorType(string $type): array
    {
        $type = trim($type);
        if ($type === '' || !Database::tableExists('skc_notification_queue')) return [];

        return Database::rows(
            "SELECT gda_campaign_tag AS tag,
                    SUM(status IN ('pending','processing','paused')) AS pending_total,
                    SUM(status='sent') AS sent_total,
                    SUM(status='failed') AS failed_total,
                    COUNT(*) AS total,
                    MAX(created_at) AS last_activity
               FROM skc_notification_queue
              WHERE project_code='gestor-actores'
                AND gda_tipo_actor=?
                AND COALESCE(gda_campaign_tag,'')!=''
              GROUP BY tag
              ORDER BY last_activity DESC, tag ASC",
            's',
            [$type]
        );
    }

    public function send(string $type, array $ids, string $channel, string $tag, string $subject, string $message, array $template = []): array
    {
        $repo = new ActorRepository();
        $recipients = $repo->recipients($type, $ids, $channel);
        $queued = 0;
        $failed = 0;
        $user = Auth::user();

        foreach ($recipients as $recipient) {
            $contact = trim((string) $recipient['contacto']);
            if ($contact === '') {
                $failed++;
                continue;
            }

            if ($this->enqueue($channel, [
                'destination' => $contact,
                'destination_name' => (string) $recipient['nombre'],
                'subject' => $subject,
                'message' => $message,
                'id_actor' => (int) $recipient['_ID'],
                'tipo_actor' => $type,
                'id_funcionario' => (int) ($user['id'] ?? 0),
                'nombre_funcionario' => (string) ($user['nombre'] ?? 'Sistema'),
                'campaign_tag' => $tag,
                'template_id' => (int) ($template['id'] ?? 0),
                'template_name' => (string) ($template['name'] ?? ''),
            ])) {
                $queued++;
            } else {
                $failed++;
            }
        }

        if ($queued > 0) {
            ReportCache::forget('campaign_list');
        }

        return ['queued' => $queued, 'failed' => $failed, 'total' => count($recipients)];
    }

    private function enqueue(string $channel, array $payload): bool
    {
        if (Database::tableExists('skc_notification_queue')) {
            $meta = [
                'gda' => [
                    'id_funcionario' => $payload['id_funcionario'],
                    'id_actor' => $payload['id_actor'],
                    'tipo_actor' => $payload['tipo_actor'],
                    'campaign_tag' => $payload['campaign_tag'],
                    'template_id' => (int) ($payload['template_id'] ?? 0),
                    'template_name' => (string) ($payload['template_name'] ?? ''),
                    'nombre_funcionario' => $payload['nombre_funcionario'],
                    'source_module' => 'dashboard-marketing',
                ],
            ];

            $queueData = [
                'project_code' => 'gestor-actores',
                'source_module' => 'dashboard-marketing',
                'channel' => $channel,
                'provider' => match ($channel) {
                    'sms' => 'sms_onurix',
                    'whatsapp' => 'whatsapp',
                    default => 'email_smtp',
                },
                'destination' => $payload['destination'],
                'destination_name' => $payload['destination_name'],
                'subject' => $channel === 'email' ? $payload['subject'] : '',
                'message_html' => $channel === 'email' ? $payload['message'] : '',
                'message_text' => strip_tags($payload['message']),
                'template_name' => (string) ($payload['template_name'] ?? ''),
                'payload_json' => '{}',
                'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'pending',
                'priority' => '100',
                'created_by' => 'dashboard-marketing',
                'scheduled_at' => gmdate('Y-m-d H:i:s'),
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];

            $columns = Database::columns('skc_notification_queue');
            $inserted = Database::insert('skc_notification_queue', array_intersect_key($queueData, $columns));
            return $inserted > 0;
        }

        return false;
    }
}
