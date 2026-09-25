<?php

declare(strict_types=1);

use App\TemplateRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$repo = new TemplateRepository();
$existing = [];
foreach ($repo->options() as $template) {
    if (strtolower((string) ($template['tipo'] ?? '')) !== 'whatsapp') {
        continue;
    }
    $config = TemplateRepository::whatsappConfig($template);
    $name = trim((string) ($config['official_template'] ?? ''));
    if ($name !== '') {
        $existing[$name] = (int) ($template['_ID'] ?? 0);
    }
}

$templates = [
    [
        'nombre' => 'WhatsApp Marketing - Documento generico',
        'whatsapp_template_name' => 'skc_marketing_documento_generico_v1',
        'whatsapp_category' => 'MARKETING',
        'whatsapp_header_type' => 'document',
        'whatsapp_header_url' => '',
        'whatsapp_header_filename' => 'documento-skc.pdf',
        'contenido' => "Hola {{nombre}}.\n\n{{custom_message}}\n\nTe compartimos el documento en el encabezado de este mensaje.\n\nSi quieres ampliar detalles, responde a este mensaje.\n\nSKC SuCasa Inmobiliaria",
    ],
    [
        'nombre' => 'WhatsApp Marketing - Texto generico',
        'whatsapp_template_name' => 'skc_marketing_texto_generico_v1',
        'whatsapp_category' => 'MARKETING',
        'whatsapp_header_type' => 'none',
        'contenido' => "Hola {{nombre}}.\n\n{{custom_message}}\n\nResponde a este mensaje y con gusto te orientamos.\n\nSKC SuCasa Inmobiliaria",
    ],
    [
        'nombre' => 'WhatsApp Marketing - Video generico',
        'whatsapp_template_name' => 'skc_marketing_video_generico_v1',
        'whatsapp_category' => 'MARKETING',
        'whatsapp_header_type' => 'video',
        'whatsapp_header_url' => '',
        'contenido' => "Hola {{nombre}}.\n\n{{custom_message}}\n\nMira el video en el encabezado de este mensaje y responde si quieres que te ampliemos la informacion.\n\nSKC SuCasa Inmobiliaria",
    ],
    [
        'nombre' => 'WhatsApp Marketing - Guardian',
        'whatsapp_template_name' => 'guardian_marketing_presentacion_v1',
        'whatsapp_category' => 'MARKETING',
        'whatsapp_header_type' => 'none',
        'contenido' => "Hola {{nombre}}.\n\n{{custom_message}}\n\nGuardian es nuestro canal de atencion para ayudarte a gestionar solicitudes, seguimientos y respuestas de forma mas agil.\n\nSKC SuCasa Inmobiliaria",
    ],
];

$created = 0;
$updated = 0;
foreach ($templates as $template) {
    $id = $repo->save(array_merge([
        '_ID' => $existing[$template['whatsapp_template_name']] ?? 0,
        'tipo' => 'whatsapp',
        'asunto' => '',
        'whatsapp_language' => 'es_CO',
        'whatsapp_button_type' => 'none',
    ], $template));

    if (($existing[$template['whatsapp_template_name']] ?? 0) > 0) {
        echo ($id > 0 ? 'Actualizada: ' : 'No actualizada: ') . $template['whatsapp_template_name'] . PHP_EOL;
        if ($id > 0) {
            $updated++;
        }
    } else {
        echo ($id > 0 ? 'Creada: ' : 'No creada: ') . $template['whatsapp_template_name'] . PHP_EOL;
        if ($id > 0) {
            $created++;
        }
    }
}

echo 'Total creadas: ' . $created . PHP_EOL;
echo 'Total actualizadas: ' . $updated . PHP_EOL;
