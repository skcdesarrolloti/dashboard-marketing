<?php

declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return 'test-csrf-token'; }
function url_page(string $page = 'dashboard', array $params = []): string
{
    return '/' . $page . ($params ? '?' . http_build_query($params) : '');
}

function renderMeetings(array $data): string
{
    extract($data);
    ob_start();
    require dirname(__DIR__) . '/views/meetings.php';
    return (string) ob_get_clean();
}

$employee = ['id' => 8, 'nombre' => 'Daniela Hernández', 'correo' => 'daniela@example.com'];
$action = [
    'id' => 31, 'meeting_id' => 7, 'section' => 'action',
    'title' => 'Actualizar el calendario editorial', 'content' => 'Definir responsables y fechas.',
    'observation' => '', 'responsible_employee_id' => 8, 'responsible_name' => 'Daniela Hernández',
    'due_date' => '2026-08-15', 'status' => 'pending', 'failure_reason' => '',
    'carried_from_item_id' => 19, 'sort_order' => 10, 'active' => 1,
];
$meeting = [
    'id' => 7, 'title' => '<script>alert(1)</script> Seguimiento agosto', 'meeting_date' => '2026-08-07',
    'objective' => 'Revisar avances del equipo.', 'participants' => [['id' => 8, 'name' => 'Daniela Hernández']],
    'conclusions' => '', 'status' => 'in_progress', 'created_by_name' => 'Roy Guardo',
    'items' => [
        'achievement' => [['id' => 20, 'meeting_id' => 7, 'section' => 'achievement', 'title' => 'Mejoró el alcance', 'content' => 'Se superó la meta.', 'observation' => '', 'sort_order' => 10]],
        'improvement' => [['id' => 21, 'meeting_id' => 7, 'section' => 'improvement', 'title' => 'Tiempo de respuesta', 'content' => 'Hay retrasos en mensajes.', 'observation' => 'Medir semanalmente.', 'sort_order' => 10]],
        'action' => [$action],
    ],
    'history' => [],
    'progress' => ['total' => 1, 'reviewed' => 0, 'completed' => 0, 'not_completed' => 0, 'review_percent' => 0, 'compliance_percent' => 0],
];

$dashboard = renderMeetings([
    'meetings' => [$meeting + ['action_total' => 1, 'action_open' => 1, 'compliance' => 0]],
    'summary' => ['total' => 1, 'upcoming' => 0, 'in_progress' => 1, 'completed' => 0, 'compliance' => 0],
    'selected' => [], 'employees' => [$employee], 'filters' => [], 'mode' => 'dashboard',
    'canManage' => true, 'canReopen' => true, 'canDelete' => true,
]);

$guide = renderMeetings([
    'meetings' => [], 'summary' => [], 'selected' => $meeting, 'employees' => [$employee],
    'filters' => [], 'mode' => 'guide', 'canManage' => true, 'canReopen' => true, 'canDelete' => true,
]);

$readonly = renderMeetings([
    'meetings' => [], 'summary' => [], 'selected' => $meeting + ['status' => 'completed'], 'employees' => [$employee],
    'filters' => [], 'mode' => 'dashboard', 'canManage' => false, 'canReopen' => false, 'canDelete' => false,
]);

$adminDetail = renderMeetings([
    'meetings' => [], 'summary' => [], 'selected' => $meeting, 'employees' => [$employee],
    'filters' => [], 'mode' => 'dashboard', 'canManage' => true, 'canReopen' => true, 'canDelete' => true,
]);

if (PHP_SAPI === 'cli-server') {
    $screen = ($_GET['screen'] ?? 'guide') === 'dashboard' ? $dashboard : $guide;
    $bodyClass = ($_GET['screen'] ?? 'guide') === 'dashboard' ? '' : 'meeting-guide-page';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<link rel="stylesheet" href="../public/assets/app.css"><link rel="stylesheet" href="../public/assets/meetings.css">';
    echo '<title>Fixture Reuniones</title></head><body class="' . $bodyClass . '"><main class="app-main"><div class="shell">' . $screen . '</div></main>';
    echo '<script src="../public/assets/meetings.js"></script></body></html>';
    exit;
}

$checks = [
    'dashboard heading' => str_contains($dashboard, '<h1>Reuniones</h1>'),
    'create action present' => str_contains($dashboard, 'value="meeting_save"'),
    'searchable participant picker' => str_contains($dashboard, 'data-participant-search') && str_contains($dashboard, 'data-participant-checkbox'),
    'five guide steps' => substr_count($guide, 'data-guide-step="') === 5,
    'guide sequence' => str_contains($guide, 'Seguimiento anterior') && str_contains($guide, 'Plan nuevo') && str_contains($guide, 'Conclusiones'),
    'dynamic action controls' => str_contains($guide, 'data-action-status="completed"') && str_contains($guide, 'data-action-reason-text'),
    'csrf present' => str_contains($guide, 'value="test-csrf-token"'),
    'malicious title escaped' => str_contains($guide, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($guide, '<script>alert(1)</script>'),
    'admin delete action' => str_contains($adminDetail, 'value="meeting_delete"') && str_contains($adminDetail, 'Eliminar definitivamente'),
    'readonly has no mutation' => !str_contains($readonly, 'value="meeting_item_save"') && !str_contains($readonly, 'value="meeting_reopen"') && !str_contains($readonly, 'value="meeting_delete"'),
];

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
if ($failed) {
    fwrite(STDERR, 'FAIL: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'OK meetings view: ' . count($checks) . " checks\n";
