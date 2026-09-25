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
        $existing[$name] = true;
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
        'contenido' => "Hola {{nombre}}.\n\nTe compartimos un documento de SKC SuCasa Inmobiliaria con informacion preparada para ti.\n\nSi quieres ampliar detalles, responde a este mensaje.\n\nSKC SuCasa Inmobiliaria",
    ],
    [
        'nombre' => 'WhatsApp Marketing - Texto generico',
        'whatsapp_template_name' => 'skc_marketing_texto_generico_v1',
        'whatsapp_category' => 'MARKETING',
        'whatsapp_header_type' => 'none',
        'contenido' => "Hola {{nombre}}.\n\nTenemos informacion de SKC SuCasa Inmobiliaria que puede ser util para tus planes inmobiliarios.\n\nResponde a este mensaje y con gusto te orientamos.\n\nSKC SuCasa Inmobiliaria",
    ],
    [
        'nombre' => 'WhatsApp Marketing - Guardian',
        'whatsapp_template_name' => 'guardian_marketing_presentacion_v1',
        'whatsapp_category' => 'MARKETING',
        'whatsapp_header_type' => 'none',
        'contenido' => "Hola {{nombre}}.\n\nGuardian es nuestro canal de atencion para ayudarte a gestionar solicitudes, seguimientos y respuestas de forma mas agil.\n\nPuedes escribirnos para recibir orientacion o continuar tu proceso.\n\nSKC SuCasa Inmobiliaria",
    ],
];

$created = 0;
foreach ($templates as $template) {
    if (isset($existing[$template['whatsapp_template_name']])) {
        echo 'Existe: ' . $template['whatsapp_template_name'] . PHP_EOL;
        continue;
    }

    $id = $repo->save(array_merge([
        'tipo' => 'whatsapp',
        'asunto' => '',
        'whatsapp_language' => 'es_CO',
        'whatsapp_button_type' => 'none',
    ], $template));

    echo ($id > 0 ? 'Creada: ' : 'No creada: ') . $template['whatsapp_template_name'] . PHP_EOL;
    if ($id > 0) {
        $created++;
    }
}

echo 'Total creadas: ' . $created . PHP_EOL;
