<?php
declare(strict_types=1);
namespace App;
use RuntimeException;
use Throwable;
final class Router
{
    public function dispatch(): void
    {
        $action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
        $inventoryApiPath = $this->inventoryApiPath();
        if ($inventoryApiPath !== '') {
            $this->inventoryApi($inventoryApiPath);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === '') {
            $this->redirectLegacyPageUrl();
        }
        if ($action === 'login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->login();
        }
        if ($action === 'magic-login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $this->magicLogin();
        }
        if ($action === 'logout') {
            Auth::logout();
            redirect_to();
        }
        if ($action === 'track-email' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $this->trackEmail();
            return;
        }
        if ($action === 'gda-register-hit') {
            $this->trackerHit();
            return;
        }
        if (!Auth::check()) {
            $this->view('login', ['error' => $_SESSION['flash_error'] ?? null]);
            unset($_SESSION['flash_error']);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $this->isYouTubeCallbackPath()) {
            $this->youtubeCallback();
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $this->isTikTokCallbackPath()) {
            $this->tiktokCallback();
            return;
        }
        if ($action === 'social-stats-panel' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!verify_csrf($_POST['_token'] ?? null)) {
                http_response_code(419);
                echo 'La sesión expiró. Recarga la página e intenta nuevamente.';
                return;
            }
            $this->socialStatsPanel($_POST);
            return;
        }
        if ($action === 'branding-file' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            if (!PermissionService::canModule('branding')) {
                http_response_code(403);
                echo 'No autorizado.';
                return;
            }
            (new BrandAssetService())->streamFile(
                (int) ($_GET['id'] ?? 0),
                (int) ($_GET['download'] ?? 0) === 1
            );
        }
        if ($action === 'actor-modal' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $this->actorModal();
            return;
        }
        if ($action === 'inventory-evidence' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            if (!PermissionService::canModule('inventario')) {
                http_response_code(403);
                echo 'No autorizado.';
                return;
            }
            (new InventoryService())->streamEvidence((int) ($_GET['id'] ?? 0));
        }
        if ($action === 'my-deliveries' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $repo = new DeliveryRepository();
            $myQueue = $repo->myQueue();
            $recent = $repo->recentForUser();
            header('Content-Type: text/html; charset=UTF-8');
            require dirname(__DIR__) . '/views/_my-deliveries.php';
            return;
        }
        if ($action === 'campaign-analytics-panel' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $this->campaignAnalyticsPanel();
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->post($action);
        }
        $page = (string) ($_GET['page'] ?? $this->pathPage());
        $this->page($page);
    }
    private function login(): void
    {
        if (!verify_csrf($_POST['_token'] ?? null)) {
            if ($action === 'preview_actor_update') {
                http_response_code(419);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'message' => 'La sesión expiró. Recarga la página e intenta nuevamente.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['flash_error'] = 'La sesion expiro. Intenta nuevamente.';
            redirect_to();
        }
        if (!Auth::attempt(trim((string) ($_POST['usuario'] ?? '')), trim((string) ($_POST['password'] ?? '')))) {
            $_SESSION['flash_error'] = 'Usuario o contrasena incorrectos.';
            redirect_to();
        }
        redirect_to();
    }
    private function magicLogin(): void
    {
        if (!Auth::attemptMagicLink(trim((string) ($_GET['token'] ?? '')), (int) ($_GET['id_empleado'] ?? 0))) {
            $_SESSION['flash_error'] = 'Enlace de acceso invalido o desactivado.';
            redirect_to();
        }
        redirect_to();
    }
    private function post(string $action): void
    {
        if (!verify_csrf($_POST['_token'] ?? null)) {
            if (($action === 'sync_social_stats' || $action === 'meeting_action_status') && ($_POST['response'] ?? '') === 'json') {
                http_response_code(419);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(
                    ['ok' => false, 'message' => 'La sesión expiró. Recarga la página e intenta nuevamente.'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                exit;
            }
            if ($action === 'inventory_add_category') {
                http_response_code(419);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(
                    ['ok' => false, 'message' => 'La sesión expiró. Recarga la página e intenta nuevamente.'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                exit;
            }
            $_SESSION['flash_error'] = 'La sesion expiro. Intenta nuevamente.';
            redirect_to(['page' => str_starts_with($action, 'meeting_')
                ? 'reuniones'
                : (str_starts_with($action, 'inventory_')
                ? 'inventario'
                : (str_starts_with($action, 'branding_')
                    ? 'branding'
                    : (str_starts_with($action, 'youtube_') || str_starts_with($action, 'tiktok_') || $action === 'sync_social_stats' ? 'redes' : 'actores')))]);
        }
        if (str_starts_with($action, 'meeting_')) {
            $this->meetingPost($action);
            return;
        }
        if (str_starts_with($action, 'branding_')) {
            if (!PermissionService::canManageBranding()) {
                http_response_code(403);
                exit('No tienes permiso para modificar el Banco de Piezas.');
            }
            $service = new BrandAssetService();
            $userId = (int) (Auth::user()['id'] ?? 0);
            $id = max(0, (int) ($_POST['id'] ?? 0));
            try {
                if ($action === 'branding_save_asset') {
                    $id = $service->save($_POST, $_FILES, $userId);
                    $_SESSION['flash_success'] = 'La pieza quedó guardada en el banco de branding.';
                } elseif ($action === 'branding_archive_asset') {
                    $service->setActive($id, false, $userId);
                    $_SESSION['flash_success'] = 'La pieza fue archivada.';
                } elseif ($action === 'branding_restore_asset') {
                    $service->setActive($id, true, $userId);
                    $_SESSION['flash_success'] = 'La pieza volvió al banco activo.';
                } elseif ($action === 'branding_replace_post_links') {
                    $count = $service->replacePostLinks($id, (array) ($_POST['post_ids'] ?? []), $userId);
                    $_SESSION['flash_success'] = match ($count) {
                        0 => 'Se eliminaron los vínculos de publicaciones de la pieza.',
                        1 => 'La publicación quedó vinculada a la pieza.',
                        default => "Se guardaron {$count} publicaciones vinculadas a la pieza.",
                    };
                } else {
                    throw new RuntimeException('Acción de branding inválida.');
                }
                redirect_to(['page' => 'branding', 'saved' => 1]);
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                if ($action === 'branding_replace_post_links') {
                    redirect_to(['page' => 'branding', 'link' => $id ?: null]);
                }
                $_SESSION['branding_form'] = $_POST;
                redirect_to(['page' => 'branding', 'edit' => $id ?: null, 'form_error' => 1]);
            }
        }
        if (str_starts_with($action, 'inventory_')) {
            $service = new InventoryService();
            $userId = (int) (Auth::user()['id'] ?? 0);
            $tab = trim((string) ($_POST['return_tab'] ?? 'stock')) ?: 'stock';
            if ($action === 'inventory_add_category') {
                header('Content-Type: application/json; charset=UTF-8');
                try {
                    $category = $service->addCategory((string) ($_POST['category'] ?? ''));
                    echo json_encode(
                        ['ok' => true, 'category' => $category],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                } catch (InventoryException $e) {
                    http_response_code($e->httpStatus());
                    echo json_encode(
                        ['ok' => false, 'message' => $e->getMessage()],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                } catch (Throwable) {
                    http_response_code(500);
                    echo json_encode(
                        ['ok' => false, 'message' => 'No fue posible guardar la categoría. Intenta nuevamente.'],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }
                exit;
            }
            if ($action === 'inventory_sync_pph') {
                try {
                    $summary = $service->syncPphNow();
                    $_SESSION['flash_success'] = sprintf(
                        'Sincronización PPH completada: %d productos nuevos, %d actualizados y %d entregas importadas.',
                        (int) ($summary['products_created'] ?? 0),
                        (int) ($summary['products_updated'] ?? 0),
                        (int) ($summary['deliveries_imported'] ?? 0)
                    );
                    redirect_to(['page' => 'inventario', 'tab' => $tab]);
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = $e->getMessage();
                    redirect_to(['page' => 'inventario', 'tab' => $tab]);
                }
            }
            try {
                match ($action) {
                    'inventory_save_product' => $service->saveProduct($_POST, $_FILES, $userId),
                    'inventory_adjust_stock' => $service->adjustStock(
                        (int) ($_POST['product_id'] ?? 0),
                        (int) ($_POST['delta'] ?? 0),
                        (string) ($_POST['reason'] ?? ''),
                        $_FILES,
                        $userId
                    ),
                    'inventory_create_delivery' => $service->createDelivery($_POST, $_FILES, 'dashboard', $userId),
                    'inventory_cancel_delivery' => $service->cancelDelivery(
                        (int) ($_POST['delivery_id'] ?? 0),
                        (string) ($_POST['reason'] ?? ''),
                        $userId
                    ),
                    'inventory_archive_product' => $service->archiveProduct(
                        (int) ($_POST['product_id'] ?? 0),
                        $userId
                    ),
                    default => throw new RuntimeException('Acción de inventario inválida.'),
                };
                redirect_to(['page' => 'inventario', 'tab' => $tab, 'saved' => 1]);
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                redirect_to(['page' => 'inventario', 'tab' => $tab]);
            }
        }
        $repo = new ActorRepository();
        if ($action === 'preview_actor_update') {
            header('Content-Type: application/json; charset=UTF-8');
            try {
                $type = (string) ($_POST['type'] ?? '');
                echo json_encode((new ActorUpdateService())->preview($type, $_POST), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (Throwable $e) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            exit;
        }
        if ($action === 'save_actor') {
            try {
                $type = (string) ($_POST['type'] ?? '');
                $id = (int) ($_POST['_ID'] ?? 0);
                $id = $id > 0
                    ? (new ActorUpdateService())->commit($type, $_POST)
                    : $repo->save($type, $_POST);
                redirect_to(['page' => 'actores', 'type' => $type, 'edit' => $id, 'saved' => 1]);
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                redirect_to(['page' => 'actores', 'type' => (string) ($_POST['type'] ?? ''), 'edit' => (int) ($_POST['_ID'] ?? 0)]);
            }
        }
        if ($action === 'delete_actor') {
            $type = (string) ($_POST['type'] ?? '');
            $ok = $repo->delete($type, (int) ($_POST['id'] ?? 0));
            redirect_to(['page' => 'actores', 'type' => $type, $ok ? 'deleted' : 'delete_denied' => 1]);
        }
        if ($action === 'clean_actor_phones') {
            $type = (string) ($_POST['type'] ?? '');
            $updated = $repo->cleanPhones($type);
            redirect_to(['page' => 'actores', 'type' => $type, 'phones_cleaned' => $updated]);
        }
        if ($action === 'send_campaign') {
            $type = (string) ($_POST['type'] ?? '');
            $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
            $massScope = (string) ($_POST['mass_scope'] ?? 'selected');
            if ($massScope === 'filtered' || preg_match('/^filtered_(\d+)$/', $massScope, $scopeMatch)) {
                $limit = $massScope === 'filtered' ? 5000 : min(5000, max(1, (int) ($scopeMatch[1] ?? 0)));
                $ids = $repo->ids($type, trim((string) ($_POST['q'] ?? '')), $this->postedActorFilters($type), $limit);
            }
            $tag = trim((string) ($_POST['campaign_tag'] ?? ''));
            $channel = (string) ($_POST['channel'] ?? 'email');
            $categoria = (string) ($_POST['categoria'] ?? 'info');
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $template = [];
            $templateRecord = [];
            if ($templateId > 0) {
                $tpl = (new TemplateRepository())->find($templateId);
                $tplChannel = strtolower((string) ($tpl['tipo'] ?? 'email'));
                if ($tplChannel === 'wsp') {
                    $tplChannel = 'whatsapp';
                }
                if ($tplChannel !== strtolower($channel)) throw new RuntimeException('La plantilla seleccionada no corresponde al canal de envío.');
                $templateRecord = $tpl;
                $template = ['id' => $templateId, 'name' => trim((string) ($tpl['nombre'] ?? ''))];
                if ($tplChannel === 'whatsapp') {
                    $template = array_merge($template, TemplateRepository::whatsappConfig($tpl));
                }
            }
            $subject = trim((string) ($_POST['subject'] ?? ($templateRecord['asunto'] ?? '')));
            $message = trim((string) ($_POST['message'] ?? ($templateRecord['contenido'] ?? '')));
            if (strtolower($channel) === 'whatsapp' && $templateRecord !== []) {
                $message = trim((string) ($template['body_text'] ?? TemplateRepository::whatsappBody($templateRecord)));
                foreach (['header_url' => 'whatsapp_media_url', 'button_url_parameter' => 'whatsapp_button_url_parameter'] as $configKey => $postKey) {
                    $postedValue = trim((string) ($_POST[$postKey] ?? ''));
                    if ($postedValue !== '') {
                        $template[$configKey] = $postedValue;
                    }
                }
            }
            if ($subject === '' && $templateRecord !== []) $subject = trim((string) ($templateRecord['asunto'] ?? ''));
            if ($message === '' && $templateRecord !== []) $message = trim((string) ($templateRecord['contenido'] ?? ''));
            $attachment = null;
            if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
                $attachment = $this->storeCampaignAttachment($_FILES['attachment']);
                if ($attachment === null && !empty($_SESSION['flash_error'])) {
                    redirect_to(['page' => 'actores', 'type' => $type]);
                }
            }
            try {
                $result = (new CampaignEngine())->send(
                    $type,
                    $ids,
                    $channel,
                    $tag,
                    $subject,
                    $message,
                    $categoria,
                    $template,
                    $attachment,
                    (bool) (new AdminRepository())->getOption('gda_force_active', 0)
                );
                redirect_to([
                    'page' => 'actores',
                    'type' => $type,
                    'campaign' => $tag,
                    'queued' => $result['queued'],
                    'failed' => $result['failed'],
                    'filtered' => $result['filtered'],
                    'duplicates' => $result['duplicates'],
                    'excluded' => $result['excluded'],
                    'not_found' => $result['not_found'],
                    'selected' => $result['selected'],
                ]);
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                redirect_to(['page' => 'actores', 'type' => $type]);
            }
        }
        if ($action === 'pause_campaign') {
            $tag = trim((string) ($_POST['campaign_tag'] ?? ''));
            $pause = (int) ($_POST['pause'] ?? 0) === 1;
            (new CampaignEngine())->pause($tag, $pause);
            redirect_to(['page' => 'campanas', 'campaign' => $tag, $pause ? 'paused' : 'resumed' => 1]);
        }
        if ($action === 'delete_campaign') {
            $tag = trim((string) ($_POST['campaign_tag'] ?? ''));
            (new CampaignEngine())->delete($tag);
            redirect_to(['page' => 'campanas', 'deleted' => 1]);
        }
        if ($action === 'retry_campaign_failed') {
            $tag = trim((string) ($_POST['campaign_tag'] ?? ''));
            $channel = (string) ($_POST['channel'] ?? 'email');
            $reset = (new CampaignEngine())->retryFailed($tag, $channel);
            redirect_to(['page' => 'campanas', 'campaign' => $tag, 'retried' => $reset]);
        }
        if ($action === 'save_template') {
            $id = (new TemplateRepository())->save($_POST);
            redirect_to(['page' => 'plantilla-editor', 'id' => $id, 'saved' => 1]);
        }
        if ($action === 'delete_template') {
            (new TemplateRepository())->delete((int) ($_POST['id'] ?? 0));
            redirect_to(['page' => 'plantillas', 'deleted' => 1]);
        }
        if ($action === 'duplicate_template') {
            $id = (new TemplateRepository())->duplicate((int) ($_POST['id'] ?? 0));
            redirect_to(['page' => 'plantilla-editor', 'id' => $id, 'duplicated' => 1]);
        }
        if ($action === 'create_campaign') {
            $tag = trim((string) ($_POST['campaign_tag'] ?? ''));
            (new CampaignRepository())->create($tag);
            redirect_to(['page' => 'campanas', 'campaign' => $tag, 'created' => 1]);
        }
        if ($action === 'save_campaign_config') {
            $tag = trim((string) ($_POST['campaign_tag'] ?? ''));
            (new CampaignRepository())->saveConfig($tag, $_POST);
            redirect_to(['page' => 'campanas', 'campaign' => $tag, 'saved' => 1]);
        }
        if ($action === 'import_actors') {
            try {
                $result = (new ActorImporter())->importFromUpload(
                    (string) ($_POST['type'] ?? ''),
                    (array) ($_FILES['file'] ?? []),
                    (int) (Auth::user()['id'] ?? 0)
                );
                redirect_to([
                    'page' => 'actores',
                    'type' => (string) ($_POST['type'] ?? ''),
                    'import_inserted' => $result['inserted'],
                    'import_duplicates' => $result['duplicates'],
                    'import_skipped' => $result['skipped'],
                ]);
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                redirect_to(['page' => 'actores', 'type' => (string) ($_POST['type'] ?? '')]);
            }
        }
        if ($action === 'toggle_my_queue') {
            (new AdminRepository())->toggleUser((int) (Auth::user()['id'] ?? 0));
            redirect_to(['page'=>'envios','my_queue_updated'=>1]);
        }
        if ($action === 'remove_queue_item') {
            $status = (new DeliveryRepository())->removeFromQueue((int) ($_POST['id'] ?? 0));
            $params = ['page' => 'envios', 'tab' => 'queue', 'queue_remove' => $status];
            foreach (['p', 'channel', 'status', 'gda_tipo_actor', 'destination_name', 'destination'] as $key) {
                $value = trim((string) ($_POST[$key] ?? ''));
                if ($value !== '') {
                    $params[$key] = $value;
                }
            }
            redirect_to($params);
        }
        if ($action === 'rename_campaign') {
            $old = trim((string) ($_POST['campaign_tag'] ?? ''));
            $new = trim((string) ($_POST['new_tag'] ?? ''));
            (new CampaignRepository())->rename($old, $new);
            redirect_to(['page'=>'campanas','campaign'=>$new,'renamed'=>1]);
        }
        if ($action === 'exclude_campaign_actor') {
            (new CampaignRepository())->exclude(trim((string) $_POST['campaign_tag']), (int) $_POST['id_actor'], trim((string) $_POST['tipo_actor']), trim((string) ($_POST['reason'] ?? 'Exclusión manual')));
            redirect_to(['page'=>'campanas','campaign'=>(string) $_POST['campaign_tag'],'excluded_actor'=>1]);
        }
        if ($action === 'send_test_template') {
            $tpl = (new TemplateRepository())->find((int) ($_POST['id'] ?? 0));
            $email = trim((string) ($_POST['email'] ?? Auth::user()['correo'] ?? ''));
            if (!$tpl || ($tpl['tipo'] ?? 'email') !== 'email' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('La prueba por correo solo está disponible para plantillas de email.');
            $message = (new CampaignEngine())->resolveVariables((string) ($tpl['contenido'] ?? ''), ['nombre'=>'Usuario de prueba','correo'=>$email,'celular'=>'3000000000'], 'contactos');
            if (!(new CampaignService())->sendTest($email, '[PRUEBA] '.(string) ($tpl['asunto'] ?? 'Plantilla'), $message, (int) ($tpl['_ID'] ?? 0), (string) ($tpl['nombre'] ?? ''))) throw new RuntimeException('No fue posible encolar la prueba.');
            redirect_to(['page'=>'plantillas','test_sent'=>1]);
        }
        if ($action === 'save_planner_post') {
            $id = (new PlannerRepository())->save($_POST);
            $week = $this->plannerWeekForDate((string) ($_POST['date'] ?? date('Y-m-d')));
            redirect_to(['page' => 'planificador', 'week' => $week, 'edit' => $id, 'saved' => 1]);
        }
        if ($action === 'delete_planner_post') {
            (new PlannerRepository())->delete((int) ($_POST['id'] ?? 0));
            redirect_to(['page' => 'planificador', 'deleted' => 1]);
        }
        if ($action === 'youtube_connect') {
            if (!PermissionService::canModule('redes')) {
                throw new RuntimeException('No autorizado.');
            }
            header('Location: ' . (new YouTubeApiService())->authorizationUrl());
            exit;
        }
        if ($action === 'youtube_disconnect') {
            if (!PermissionService::canModule('redes')) {
                throw new RuntimeException('No autorizado.');
            }
            (new YouTubeApiService())->disconnect();
            $_SESSION['social_active_platform'] = 'youtube';
            $_SESSION['flash_success'] = 'El canal de YouTube fue desconectado. Las estadísticas guardadas se conservaron.';
            redirect_to(['page' => 'redes']);
        }
        if ($action === 'tiktok_connect') {
            if (!PermissionService::canModule('redes')) {
                throw new RuntimeException('No autorizado.');
            }
            header('Location: ' . (new TikTokApiService())->authorizationUrl());
            exit;
        }
        if ($action === 'tiktok_disconnect') {
            if (!PermissionService::canModule('redes')) {
                throw new RuntimeException('No autorizado.');
            }
            (new TikTokApiService())->disconnect();
            $_SESSION['social_active_platform'] = 'tiktok';
            $_SESSION['flash_success'] = 'La cuenta de TikTok fue desconectada. Los videos guardados se conservaron.';
            redirect_to(['page' => 'redes']);
        }
        if ($action === 'sync_social_stats') {
            if (!PermissionService::canModule('redes')) {
                throw new RuntimeException('No autorizado.');
            }
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            $from = $this->postedDate('from', date('Y-01-01', strtotime('-1 year')));
            $to = $this->postedDate('to', date('Y-m-d'));
            $platform = trim((string) ($_POST['platform'] ?? ''));
            if (!in_array($platform, ['', 'instagram', 'facebook', 'ads', 'youtube', 'tiktok'], true)) {
                $platform = '';
            }
            try {
                $summary = match ($platform) {
                    'youtube' => (new YouTubeSyncService())->sync($from, $to),
                    'tiktok' => (new TikTokSyncService())->sync($from, $to),
                    default => (new MetaSocialSyncService())->sync($from, $to, $platform),
                };
                $_SESSION['social_sync_warnings'] = array_values(array_map(
                    static fn (mixed $warning): string => mb_substr(trim((string) $warning), 0, 1000),
                    $summary['warnings'] ?? []
                ));
                if (($_POST['response'] ?? '') === 'json') {
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(
                        ['ok' => true, 'summary' => $summary],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                    exit;
                }
                if ($platform === 'youtube') {
                    $_SESSION['social_active_platform'] = 'youtube';
                    $_SESSION['flash_success'] = 'YouTube se sincronizó correctamente.';
                    redirect_to(['page' => 'redes']);
                }
                if ($platform === 'tiktok') {
                    $_SESSION['social_active_platform'] = 'tiktok';
                    $_SESSION['flash_success'] = 'TikTok se sincronizó correctamente.';
                    redirect_to(['page' => 'redes']);
                }
                redirect_to([
                    'page' => 'redes',
                    'from' => $from,
                    'to' => $to,
                    'platform' => $platform === '' ? 'instagram' : $platform,
                    'meta_synced' => 1,
                    'meta_accounts' => $summary['accounts'] ?? 0,
                    'meta_daily' => $summary['daily'] ?? 0,
                    'meta_posts' => $summary['posts'] ?? 0,
                    'meta_insights' => $summary['insights'] ?? 0,
                    'meta_ads' => $summary['ads'] ?? 0,
                    'meta_audience' => $summary['audience'] ?? 0,
                    'meta_leads' => $summary['leads'] ?? 0,
                    'meta_conversations' => $summary['conversations'] ?? 0,
                    'meta_warnings' => count($summary['warnings'] ?? []),
                ]);
            } catch (Throwable $e) {
                if (($_POST['response'] ?? '') === 'json') {
                    http_response_code(422);
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(
                        ['ok' => false, 'message' => $e->getMessage()],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                    exit;
                }
                $_SESSION['flash_error'] = $e->getMessage();
                if ($platform === 'youtube') {
                    $_SESSION['social_active_platform'] = 'youtube';
                    redirect_to(['page' => 'redes']);
                }
                if ($platform === 'tiktok') {
                    $_SESSION['social_active_platform'] = 'tiktok';
                    redirect_to(['page' => 'redes']);
                }
                redirect_to(['page' => 'redes', 'from' => $from, 'to' => $to, 'platform' => $platform]);
            }
        }
        if ($action === 'merge_analytics_campaign') {
            if (!PermissionService::canManageSystemConfig()) {
                throw new RuntimeException('No autorizado.');
            }
            $fromCampaign = trim((string) ($_POST['from_campaign'] ?? ''));
            $toCampaign = trim((string) ($_POST['to_campaign'] ?? ''));
            $merge = (new AnalyticsRepository())->mergeCampaign($fromCampaign, $toCampaign);
            redirect_to([
                'page' => 'analiticas',
                'tab' => 'campanas',
                'campaign_merge_status' => $merge['status'] ?? 'invalid',
                'campaign_merged' => (int) ($merge['updated'] ?? 0),
                'campaign_from' => (string) ($merge['from'] ?? $fromCampaign),
                'campaign_to' => (string) ($merge['to'] ?? $toCampaign),
            ]);
        }
        if (str_starts_with($action, 'admin_')) {
            if ($action === 'admin_save_promotion') {
                if (!PermissionService::canEditPromotionConfig()) throw new RuntimeException('No autorizado.');
            } elseif (!PermissionService::canManageSystemConfig()) {
                throw new RuntimeException('No autorizado.');
            }
            $admin = new AdminRepository();
            match ($action) {
                'admin_save_limits' => $admin->saveLimits((int) $_POST['email_limit'], (int) $_POST['sms_limit']),
                'admin_save_onurix' => $admin->saveOnurix(trim((string) $_POST['client']), trim((string) $_POST['key'])),
                'admin_toggle_queue' => $admin->toggleOption('gda_queue_paused'),
                'admin_toggle_force' => $admin->toggleOption('gda_force_active'),
                'admin_toggle_user' => $admin->toggleUser((int) $_POST['user_id']),
                'admin_queue_action' => $admin->queueAction((int) $_POST['user_id'], (string) $_POST['channel'], (string) $_POST['operation']),
                'admin_clear_logs' => $admin->clearLogs(false),
                'admin_reset_limits' => $admin->clearLogs(true),
                'admin_save_permissions' => $admin->savePermissions((array) ($_POST['permissions'] ?? [])),
                'admin_add_doc_type' => $admin->addDocumentType(trim((string) $_POST['type'])),
                'admin_delete_doc_type' => $admin->deleteDocumentType((int) $_POST['id']),
                'admin_save_promotion' => $admin->savePromotionConfig($_POST, $_FILES),
                'admin_save_config_row' => $admin->saveConfigRow($_POST),
                'admin_delete_config_row' => $admin->deleteConfigRow((int) $_POST['id']),
                default => throw new RuntimeException('Acción administrativa inválida.'),
            };
            redirect_to(['page'=>'configuracion','saved'=>1]);
        }
    }
    private function storeCampaignAttachment(array $file): ?string
    {
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Adjunto excede 5 MB.';
            return null;
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $_SESSION['flash_error'] = 'Adjunto no permitido (PDF/JPG/PNG).';
            return null;
        }
        $basePath = dirname(__DIR__) . '/storage/campaign-attachments';
        if (!is_dir($basePath)) {
            @mkdir($basePath, 0775, true);
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($file['name'] ?? 'adjunto')) ?? 'adjunto';
        $filename = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
        $dest = $basePath . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            $_SESSION['flash_error'] = 'No se pudo guardar el adjunto.';
            return null;
        }
        return $dest;
    }
    private function meetingPost(string $action): void
    {
        $isJson = $action === 'meeting_action_status' && ($_POST['response'] ?? '') === 'json';
        if (!PermissionService::canModule('reuniones') || !PermissionService::canManageMeetings()) {
            if ($isJson) {
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'message' => 'No tienes permiso para modificar reuniones.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            http_response_code(403);
            exit('No tienes permiso para modificar reuniones.');
        }
        if (in_array($action, ['meeting_reopen', 'meeting_delete'], true) && !PermissionService::canDeleteMeetings()) {
            http_response_code(403);
            exit('Solo un administrador puede realizar esta acción sobre la reunión.');
        }

        $service = new MeetingService();
        $meetingId = max(0, (int) ($_POST['meeting_id'] ?? $_POST['id'] ?? 0));
        try {
            switch ($action) {
                case 'meeting_save':
                    $meetingId = $service->saveMeeting($_POST);
                    $_SESSION['flash_success'] = empty($_POST['id']) ? 'La reunión quedó creada.' : 'Los datos de la reunión se actualizaron.';
                    break;
                case 'meeting_item_save':
                    $service->saveItem($_POST);
                    $_SESSION['flash_success'] = 'El punto quedó guardado.';
                    break;
                case 'meeting_item_move':
                    $service->moveItem((int) ($_POST['item_id'] ?? 0), trim((string) ($_POST['direction'] ?? '')));
                    break;
                case 'meeting_item_archive':
                    $meetingId = $service->archiveItem((int) ($_POST['item_id'] ?? 0));
                    $_SESSION['flash_success'] = 'El punto fue archivado sin borrar su historial.';
                    break;
                case 'meeting_start':
                    $service->start($meetingId);
                    $_SESSION['flash_success'] = 'La reunión está en curso.';
                    redirect_to(['page' => 'reuniones', 'id' => $meetingId, 'mode' => 'guide']);
                    break;
                case 'meeting_complete':
                    $service->complete($meetingId, (string) ($_POST['conclusions'] ?? ''));
                    $_SESSION['flash_success'] = 'La reunión fue finalizada y quedó bloqueada.';
                    break;
                case 'meeting_reopen':
                    $service->reopen($meetingId);
                    $_SESSION['flash_success'] = 'La reunión fue reabierta.';
                    break;
                case 'meeting_archive':
                    $service->archive($meetingId);
                    $_SESSION['flash_success'] = 'La reunión fue archivada; su historial se conserva.';
                    redirect_to(['page' => 'reuniones']);
                    break;
                case 'meeting_delete':
                    $service->delete($meetingId);
                    $_SESSION['flash_success'] = 'La reunión y todos sus logros, mejoras, acciones e historial fueron eliminados.';
                    redirect_to(['page' => 'reuniones']);
                    break;
                case 'meeting_action_status':
                    $result = $service->updateActionStatus(
                        (int) ($_POST['item_id'] ?? 0),
                        trim((string) ($_POST['status'] ?? '')),
                        (string) ($_POST['reason'] ?? '')
                    );
                    if ($isJson) {
                        header('Content-Type: application/json; charset=UTF-8');
                        echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit;
                    }
                    $_SESSION['flash_success'] = 'El estado de la acción se actualizó.';
                    break;
                default:
                    throw new RuntimeException('Acción de reuniones inválida.');
            }
            redirect_to(['page' => 'reuniones', 'id' => $meetingId ?: null]);
        } catch (Throwable $error) {
            if ($isJson) {
                http_response_code(422);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $_SESSION['flash_error'] = $error->getMessage();
            redirect_to(['page' => 'reuniones', 'id' => $meetingId ?: null]);
        }
    }
    private function page(string $page): void
    {
        if ($page === 'modulos') {
            redirect_to();
        }
        $permissionModule = [''=>'dashboard','dashboard'=>'dashboard','campanas'=>'campanas','plantillas'=>'plantillas','plantilla-editor'=>'plantillas','actores'=>(string)($_GET['type']??'contactos'),'configuracion'=>'confi_sistema'];
        if (!PermissionService::canModule($permissionModule[$page] ?? $page)) { http_response_code(403); echo 'No tienes permiso para acceder a este módulo.'; return; }
        $today = date('Y-m-d');
        $from = $this->dateParam('from', date('Y-m-d', strtotime('-30 days')));
        $to = $this->dateParam('to', $today);
        $dashboardFrom = $this->optionalDateParam('from');
        $dashboardTo = $this->optionalDateParam('to');
        match ($page) {
            'actores' => $this->actors(),
            'plantillas' => $this->templates(),
            'plantilla-editor' => $this->templateEditor(),
            'campanas' => $this->campaigns(),
            'envios' => $this->deliveries(),
            'inventario' => $this->inventory(),
            'actividad' => $this->view('activity', [
                'from' => $from,
                'to' => $to,
                'actor' => trim((string) ($_GET['actor'] ?? '')),
                'usuario' => trim((string) ($_GET['usuario'] ?? '')),
                'nombre' => trim((string) ($_GET['nombre'] ?? '')),
                'menu' => trim((string) ($_GET['menu'] ?? '')),
                'data' => (new ActivityRepository())->summary(
                    $from,
                    $to,
                    trim((string) ($_GET['actor'] ?? '')),
                    trim((string) ($_GET['usuario'] ?? '')),
                    trim((string) ($_GET['nombre'] ?? '')),
                    trim((string) ($_GET['menu'] ?? ''))
                ),
            ]),
            'analiticas' => $this->analytics(),
            'redes' => $this->socialStats(),
            'branding' => $this->branding(),
            'planificador' => $this->planner(),
            'reuniones' => $this->meetings(),
            'herramienta' => $this->view('utm', ['analyticsOptions' => (new AnalyticsRepository())->filterOptions()]),
            'configuracion' => $this->settings(),
            default => $this->dashboard($dashboardFrom, $dashboardTo),
        };
    }
    private function pathPage(): string
    {
        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $scriptDir = trim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = trim(substr($path, strlen($scriptDir)), '/');
        }
        $first = explode('/', $path)[0] ?? '';
        return $first !== '' && in_array($first, ['dashboard', 'actores', 'plantillas', 'plantilla-editor', 'campanas', 'envios', 'inventario', 'actividad', 'analiticas', 'redes', 'branding', 'planificador', 'reuniones', 'herramienta', 'configuracion'], true)
            ? $first
            : 'dashboard';
    }
    private function redirectLegacyPageUrl(): void
    {
        $page = trim((string) ($_GET['page'] ?? ''));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($page === '' || !str_contains($uri, 'index.php')) {
            return;
        }
        $params = $_GET;
        $target = url($params);
        if ($target !== $uri) {
            header('Location: ' . $target, true, 302);
            exit;
        }
    }
    private function dashboard(string $from, string $to): void
    {
        $analytics = (new AnalyticsRepository())->dashboardSummary($from, $to);
        $activity = (new ActivityRepository())->dashboardSummary();
        $campaigns = (new CampaignService())->list();
        $this->view('dashboard', compact('from', 'to', 'analytics', 'activity', 'campaigns'));
    }
    private function actors(): void
    {
        try {
            $type = (string) ($_GET['type'] ?? 'contactos');
            if (!ActorCatalog::get($type)) {
                $type = 'contactos';
            }
            $page = max(1, (int) ($_GET['p'] ?? 1));
            $search = trim((string) ($_GET['q'] ?? ''));
            $repo = new ActorRepository();
            $edit = isset($_GET['edit']) ? $repo->find($type, (int) $_GET['edit']) : [];
            $config = $this->actorConfigForView($type);
            $filters = $this->actorFilters($config);
            $campaignStats = [];
            foreach (['pending' => 'Pendientes', 'sent' => 'Enviados', 'failed' => 'Fallidos'] as $key => $_label) {
                $campaignStats[$key] = 0;
            }
            $error = $_SESSION['flash_error'] ?? null;
            unset($_SESSION['flash_error']);
            $this->view('actors', [
                'type' => $type,
                'catalog' => ActorCatalog::all(),
                'config' => $config,
                'search' => $search,
                'filters' => $filters,
                'pageNumber' => $page,
                'data' => $repo->paginate($type, $search, $page, 20, $filters),
                'edit' => $edit,
                'templates' => (new TemplateRepository())->options(),
                'campaigns' => (new CampaignRepository())->list(),
                'campaignStats' => $campaignStats,
                'error' => $error,
                'rolPersona' => $config['rol_persona'] ?? 'Contacto',
                'funcionariosOptions' => ActorColumnResolver::funcionariosListForFilter($type),
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo '<div style="padding:24px;font-family:Arial,sans-serif">';
            echo '<h2>Error en modulo Actores</h2>';
            echo '<pre style="white-space:pre-wrap;background:#fff3f3;border:1px solid #f5c2c2;padding:16px;border-radius:8px">';
            echo htmlspecialchars($e::class . ': ' . $e->getMessage() . "\nArchivo: " . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8');
            echo '</pre>';
            echo '</div>';
        }
    }
    private function actorModal(): void
    {
        $type = (string) ($_GET['type'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);
        $config = $this->actorConfigForView($type);
        if (!$config || $id <= 0) {
            http_response_code(404);
            echo '<div class="modal-content"><div class="empty-state"><h2>Actor no encontrado</h2><p>Tipo o identificador invalido.</p></div></div>';
            return;
        }
        $actor = (new ActorRepository())->find($type, $id);
        if (!$actor) {
            http_response_code(404);
            echo '<div class="modal-content"><div class="empty-state"><h2>Actor no encontrado</h2><p>No existe un registro con ese ID.</p></div></div>';
            return;
        }
        header('Content-Type: text/html; charset=UTF-8');
        $this->renderActorModal($type, $config, $actor);
    }
    private function renderActorModal(string $type, array $config, array $actor): void
    {
        $view = dirname(__DIR__) . '/views/_actor-modal.php';
        $rolPersona = $config['rol_persona'] ?? 'Contacto';
        $authorId = (int) ($actor['cct_author_id'] ?? 0);
        $actorId = (int) ($actor['_ID'] ?? 0);
        if (!PermissionService::canFullEdit($type, $actorId, $authorId)) {
            $config['title'] = 'Preferencias de comunicación';
            $config['campos'] = ActorPreferences::campos();
            $config['preferences_only'] = true;
        }
        require $view;
    }
    private function templates(): void
    {
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'tipo' => trim((string) ($_GET['tipo'] ?? '')),
        ];
        $repo = new TemplateRepository();
        $this->view('templates', [
            'filters' => $filters,
            'pageNumber' => $page,
            'data' => $repo->paginate($filters, $page),
        ]);
    }
    private function templateEditor(): void
    {
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $this->view('template-editor', ['edit'=>$id ? (new TemplateRepository())->find($id) : []]);
    }
    private function campaigns(): void
    {
        $repo = new CampaignRepository();
        $campaigns = $repo->list();
        $selected = trim((string) ($_GET['campaign'] ?? ''));
        $filters = [
            'from' => $this->optionalDateParam('from'),
            'to' => $this->optionalDateParam('to'),
            'canal' => trim((string) ($_GET['canal'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'opened' => trim((string) ($_GET['opened'] ?? '')),
            'tipo_actor' => trim((string) ($_GET['tipo_actor'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $hasDetailFilters = false;
        foreach ($filters as $filterValue) {
            if (trim((string) $filterValue) !== '') {
                $hasDetailFilters = true;
                break;
            }
        }
        $detailFilters = $filters;
        $detailFilters['_light'] = !isset($_GET['history']) && $page === 1 && !$hasDetailFilters;
        $detail = $selected !== ''
            ? $repo->detail($selected, $detailFilters, $page)
            : [
                'status' => [],
                'config' => [],
                'tracking' => [
                    'tracked' => array_sum(array_map(static fn (array $row): int => (int) ($row['tracked_total'] ?? 0), $campaigns)),
                    'opened' => array_sum(array_map(static fn (array $row): int => (int) ($row['opened_total'] ?? 0), $campaigns)),
                    'total_opens' => array_sum(array_map(static fn (array $row): int => (int) ($row['total_opens'] ?? 0), $campaigns)),
                ],
                'templates' => [],
                'batches' => [],
                'exclusions' => [],
                'actor_types' => [],
                'failed' => [],
                'history' => ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0],
            ];
        $this->view('campaigns', [
            'campaigns' => $campaigns,
            'selected' => $selected,
            'filters' => $filters,
            'analyticsOptions' => $selected !== '' ? (new AnalyticsRepository())->filterOptions() : [],
            'actorTypeOptions' => $selected !== '' ? $repo->actorTypeOptions($selected) : [],
            'pageNumber' => $page,
            'detail' => $detail,
            'engine' => new CampaignEngine(),
        ]);
    }
    private function deliveries(): void
    {
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $filters = [
            'channel' => trim((string) ($_GET['channel'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'gda_tipo_actor' => trim((string) ($_GET['gda_tipo_actor'] ?? '')),
            'destination_name' => trim((string) ($_GET['destination_name'] ?? '')),
            'destination' => trim((string) ($_GET['destination'] ?? '')),
        ];
        $repo = new DeliveryRepository();
        $this->view('deliveries', [
            'filters' => $filters,
            'pageNumber' => $page,
            'tab' => 'queue',
            'summary' => $repo->summary($filters),
            'queue' => $repo->queue($filters, $page),
            'filterOptions' => $repo->filterOptions(),
        ]);
    }
    private function planner(): void
    {
        $repo = new PlannerRepository();
        $week = $this->plannerWeekForDate((string) ($_GET['week'] ?? ($_GET['month'] ?? date('Y-m-d'))));
        $weekStart = new \DateTimeImmutable($week);
        $weekEnd = $weekStart->modify('+6 days');
        $month = $weekStart->format('Y-m');
        $from = $weekStart->format('Y-m-d');
        $to = $weekEnd->format('Y-m-d');
        $filters = [
            'status' => trim((string) ($_GET['status'] ?? '')),
            'channel' => trim((string) ($_GET['channel'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $items = $repo->all($filters);
        $calendarItems = $repo->forRange($from, $to, $filters);
        $edit = isset($_GET['edit']) ? $repo->find((int) $_GET['edit']) : [];
        $this->view('planner', [
            'week' => $week,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'month' => $month,
            'monthStart' => new \DateTimeImmutable($month . '-01'),
            'calendarFrom' => $from,
            'calendarTo' => $to,
            'items' => $items,
            'calendarItems' => $calendarItems,
            'summary' => $repo->summary($items),
            'filters' => $filters,
            'edit' => $edit,
        ]);
    }
    private function plannerWeekForDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: new \DateTimeImmutable();
        return $date->modify('monday this week')->format('Y-m-d');
    }
    private function socialStats(): void
    {
        $this->view('social-stats', $this->socialStatsViewData());
    }
    private function meetings(): void
    {
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $mode = trim((string) ($_GET['mode'] ?? '')) === 'guide' ? 'guide' : 'dashboard';
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        $this->view('meetings', (new MeetingService())->pageData($filters, $id) + [
            'filters' => $filters,
            'mode' => $mode,
            'canManage' => PermissionService::canManageMeetings(),
            'canReopen' => PermissionService::currentRole() === PermissionService::ROLE_ADMIN,
            'canDelete' => PermissionService::canDeleteMeetings(),
            'error' => $error,
            'success' => $success,
        ]);
    }
    private function socialStatsPanel(array $request): void
    {
        if (!PermissionService::canModule('redes')) {
            http_response_code(403);
            echo 'No tienes permiso para acceder a este módulo.';
            return;
        }
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        extract($this->socialStatsViewData($request));
        require dirname(__DIR__) . '/views/social-stats.php';
    }
    private function socialStatsViewData(?array $request = null): array
    {
        $request ??= $_GET;
        $fromFallback = date('Y-01-01', strtotime('-1 year'));
        $toFallback = date('Y-m-d');
        $fromValue = (string) ($request['from'] ?? $fromFallback);
        $toValue = (string) ($request['to'] ?? $toFallback);
        $fromDate = \DateTime::createFromFormat('Y-m-d', $fromValue);
        $toDate = \DateTime::createFromFormat('Y-m-d', $toValue);
        $from = $fromDate && $fromDate->format('Y-m-d') === $fromValue ? $fromValue : $fromFallback;
        $to = $toDate && $toDate->format('Y-m-d') === $toValue ? $toValue : $toFallback;
        $platform = trim((string) ($request['platform'] ?? ($_SESSION['social_active_platform'] ?? 'instagram')));
        if (!in_array($platform, ['instagram', 'facebook', 'ads', 'youtube', 'tiktok', 'linkedin', 'google'], true)) {
            $platform = 'instagram';
        }
        $_SESSION['social_active_platform'] = $platform;
        $dashboardPlatform = $platform === 'ads' ? '' : $platform;
        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        $syncWarnings = $_SESSION['social_sync_warnings'] ?? [];
        unset($_SESSION['flash_error']);
        unset($_SESSION['flash_success']);
        unset($_SESSION['social_sync_warnings']);
        if (in_array($platform, ['linkedin', 'google'], true)) {
            $data = [
                'summary' => [],
                'previous' => [],
                'platforms' => [],
                'series' => [],
                'topPosts' => [],
                'posts' => [],
                'accounts' => [],
                'ads' => ['summary' => [], 'campaigns' => [], 'ads' => [], 'series' => [], 'date' => ''],
                'audience' => [],
                'period' => [
                    'current' => ['from' => $from, 'to' => $to],
                    'previous' => [],
                    'comparison_ready' => false,
                    'requested_days' => max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1),
                    'covered_days' => 0,
                    'available_from' => '',
                    'available_to' => '',
                ],
                'hasLiveData' => false,
                'youtube' => [],
            ];
            $platformOptions = [$platform];
        } elseif ($platform === 'youtube') {
            $youtubeRepo = new YouTubeRepository();
            $youtubeApi = new YouTubeApiService($youtubeRepo);
            $youtube = $youtubeRepo->dashboard($from, $to);
            $youtube['configured'] = $youtubeApi->isConfigured();
            $youtube['configured_channel_id'] = $youtubeApi->configuredChannelId();
            $youtube['configured_channel_name'] = trim((string) app_config('youtube.channel_name', ''));
            $youtube['channel_matches_configuration'] = $youtubeApi->connectionMatchesConfiguration($youtube['connection'] ?? null);
            $data = [
                'summary' => [],
                'previous' => [],
                'platforms' => [],
                'series' => [],
                'topPosts' => [],
                'posts' => [],
                'accounts' => [],
                'ads' => ['summary' => [], 'campaigns' => [], 'ads' => [], 'date' => ''],
                'audience' => [],
                'period' => $youtube['period'],
                'hasLiveData' => (bool) $youtube['hasLiveData'],
                'youtube' => $youtube,
            ];
            $platformOptions = ['youtube'];
        } elseif ($platform === 'tiktok') {
            $tiktokRepo = new TikTokRepository();
            $tiktokApi = new TikTokApiService($tiktokRepo);
            $tiktok = $tiktokRepo->dashboard($from, $to);
            $tiktok['configured'] = $tiktokApi->isConfigured();
            $data = [
                'summary' => [], 'previous' => [], 'platforms' => [], 'series' => [], 'topPosts' => [], 'posts' => [],
                'accounts' => [], 'ads' => ['summary' => [], 'campaigns' => [], 'ads' => [], 'series' => [], 'date' => ''],
                'audience' => [], 'period' => $tiktok['period'], 'hasLiveData' => (bool) $tiktok['hasLiveData'],
                'youtube' => [], 'tiktok' => $tiktok,
            ];
            $platformOptions = ['tiktok'];
        } else {
            $repo = new SocialStatsRepository();
            $data = $repo->dashboard($from, $to, $dashboardPlatform);
            $platformOptions = $repo->platforms();
        }
        return [
            'from' => $from,
            'to' => $to,
            'platform' => $platform,
            'platformOptions' => $platformOptions,
            'data' => $data,
            'error' => $error,
            'success' => $success,
            'syncWarnings' => is_array($syncWarnings) ? $syncWarnings : [],
            'requestParams' => $request,
        ];
    }

    private function youtubeCallback(): void
    {
        if (!PermissionService::canModule('redes')) {
            http_response_code(403);
            echo 'No autorizado.';
            return;
        }

        $_SESSION['social_active_platform'] = 'youtube';
        $googleError = trim((string) ($_GET['error_description'] ?? $_GET['error'] ?? ''));
        if ($googleError !== '') {
            $_SESSION['flash_error'] = 'Google no autorizó la conexión de YouTube: ' . mb_substr($googleError, 0, 500);
            redirect_to(['page' => 'redes']);
        }

        try {
            $connection = (new YouTubeApiService())->completeAuthorization(
                trim((string) ($_GET['code'] ?? '')),
                trim((string) ($_GET['state'] ?? ''))
            );
            $_SESSION['flash_success'] = 'Canal conectado: ' . (string) ($connection['channel_title'] ?? 'YouTube') . '. Ya puedes sincronizar las estadísticas.';
        } catch (Throwable $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        }
        redirect_to(['page' => 'redes']);
    }

    private function isYouTubeCallbackPath(): bool
    {
        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $scriptDir = trim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = trim(substr($path, strlen($scriptDir)), '/');
        }
        return $path === 'youtube/callback';
    }

    private function tiktokCallback(): void
    {
        if (!PermissionService::canModule('redes')) {
            http_response_code(403);
            echo 'No autorizado.';
            return;
        }
        $_SESSION['social_active_platform'] = 'tiktok';
        $tiktokError = trim((string) ($_GET['error_description'] ?? $_GET['error'] ?? ''));
        if ($tiktokError !== '') {
            $_SESSION['flash_error'] = 'TikTok no autorizó la conexión: ' . mb_substr($tiktokError, 0, 500);
            redirect_to(['page' => 'redes']);
        }
        try {
            $connection = (new TikTokApiService())->completeAuthorization(
                trim((string) ($_GET['code'] ?? '')),
                trim((string) ($_GET['state'] ?? ''))
            );
            $_SESSION['flash_success'] = 'Cuenta conectada: ' . (string) ($connection['display_name'] ?? 'TikTok') . '. Ya puedes sincronizar los videos.';
        } catch (Throwable $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        }
        redirect_to(['page' => 'redes']);
    }

    private function isTikTokCallbackPath(): bool
    {
        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $scriptDir = trim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = trim(substr($path, strlen($scriptDir)), '/');
        }
        return $path === 'tiktok/callback';
    }
    private function branding(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'category' => trim((string) ($_GET['category'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'channel' => trim((string) ($_GET['channel'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')) ?: '1',
        ];
        $service = new BrandAssetService();
        $edit = isset($_GET['edit']) ? $service->find((int) $_GET['edit']) : [];
        $posted = $_SESSION['branding_form'] ?? [];
        if (is_array($posted) && $posted !== []) {
            $edit = array_merge($edit, $posted);
            $edit['channels'] = array_map('strval', (array) ($posted['channels'] ?? []));
        }
        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['branding_form']);
        $this->view('branding', [
            'data' => $service->dashboardData($filters),
            'filters' => $filters,
            'edit' => $edit,
            'formOpen' => isset($_GET['edit']) || isset($_GET['new']) || isset($_GET['form_error']),
            'canManage' => PermissionService::canManageBranding(),
            'categories' => BrandAssetService::categories(),
            'statuses' => BrandAssetService::statuses(),
            'channels' => BrandAssetService::channels(),
            'socialPosts' => $service->socialPosts(),
            'linkAssetId' => max(0, (int) ($_GET['link'] ?? 0)),
            'error' => $error,
            'success' => $success,
        ]);
    }
    private function settings(): void
    {
        if (!PermissionService::canAccessSettings()) { http_response_code(403); echo 'No autorizado.'; return; }
        $this->view('settings', [
            'data'=>(new AdminRepository())->dashboard(),
            'canEditPromotionConfig' => PermissionService::canEditPromotionConfig(),
            'canManageSystemConfig' => PermissionService::canManageSystemConfig(),
        ]);
    }
    private function trackEmail(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $table = Database::table('jet_cct_email_tracking');
        if (preg_match('/^[a-f0-9]{32,64}$/i', $token) && Database::tableExists($table)) {
            Database::execute("UPDATE {$table} SET opened_at=COALESCE(opened_at,UTC_TIMESTAMP()), last_opened_at=UTC_TIMESTAMP(), open_count=open_count+1, last_ip=?, last_user_agent=? WHERE tracking_token=?", 'sss', [substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''),0,100), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500), $token]);
        }
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    }
    private function trackerHit(): void
    {
        if (!$this->trackingCors()) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Metodo no permitido'], 405);
            return;
        }

        try {
            $payload = $_POST;
            if ($payload === []) {
                $raw = file_get_contents('php://input') ?: '';
                $decoded = json_decode($raw, true);
                $payload = is_array($decoded) ? $decoded : [];
            }

            $id = (new TrackerRepository())->register($payload);
            $this->json(['ok' => true, 'id' => $id]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
    private function trackingCors(): bool
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $allowed = (array) app_config('tracking.allowed_origins', []);
        $allowed = array_map('strtolower', $allowed);

        if ($origin !== '') {
            if (!in_array('*', $allowed, true) && !in_array(strtolower($origin), $allowed, true)) {
                $this->json(['ok' => false, 'error' => 'Origen no permitido'], 403);
                return false;
            }

            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');

        return true;
    }
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    private function inventoryApiPath(): string
    {
        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $scriptDir = trim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = trim(substr($path, strlen($scriptDir)), '/');
        }
        if (preg_match('#^api/inventory/(products|deliveries)$#', $path, $matches)) {
            return (string) $matches[1];
        }
        return '';
    }
    private function inventoryApi(string $resource): void
    {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Inventory-Token');
        header('X-Content-Type-Options: nosniff');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'OPTIONS') {
            http_response_code(204);
            return;
        }
        if (!$this->validInventoryApiToken()) {
            $this->json(['ok' => false, 'message' => 'Token de inventario inválido o no configurado.'], 401);
            return;
        }
        try {
            $service = new InventoryService();
            if ($resource === 'products' && $method === 'GET') {
                $this->json(['ok' => true, 'products' => $service->activeProducts()]);
                return;
            }
            if ($resource === 'deliveries' && $method === 'POST') {
                $payload = $_POST;
                $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
                if (str_contains($contentType, 'application/json')) {
                    $decoded = json_decode(file_get_contents('php://input') ?: '', true);
                    if (!is_array($decoded)) {
                        throw new InventoryException('El cuerpo JSON no es válido.');
                    }
                    $payload = $decoded;
                }
                $result = $service->createDelivery($payload, $_FILES, 'api', null);
                $this->json(['ok' => true, 'delivery' => $result], !empty($result['duplicate']) ? 200 : 201);
                return;
            }
            $this->json(['ok' => false, 'message' => 'Método no permitido.'], 405);
        } catch (InventoryException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Throwable $e) {
            error_log('Inventory API: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'No fue posible procesar la operación de inventario.'], 500);
        }
    }
    private function validInventoryApiToken(): bool
    {
        $expected = trim((string) (getenv('SKC_INVENTORY_API_TOKEN') ?: app_config('inventory.api_token', '')));
        if ($expected === '') {
            return false;
        }
        $provided = trim((string) ($_SERVER['HTTP_X_INVENTORY_TOKEN'] ?? ''));
        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($provided === '' && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            $provided = trim((string) $matches[1]);
        }
        return $provided !== '' && hash_equals($expected, $provided);
    }
    private function analytics(): void
    {
        $from = $this->optionalDateParam('from');
        $to = $this->optionalDateParam('to');
        $tab = $this->analyticsTab();
        $filters = $this->analyticsFilters();
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $this->view('analytics', [
            'from' => $from,
            'to' => $to,
            'tab' => $tab,
            'filters' => $filters,
            'pageNumber' => $page,
            'canMergeAnalyticsCampaigns' => PermissionService::canManageSystemConfig(),
            'data' => (new AnalyticsRepository())->summary($from, $to, $filters, $tab, $page),
        ]);
    }
    private function inventory(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'history_q' => trim((string) ($_GET['history_q'] ?? '')),
            'history_from' => trim((string) ($_GET['history_from'] ?? '')),
            'history_to' => trim((string) ($_GET['history_to'] ?? '')),
            'history_product' => max(0, (int) ($_GET['history_product'] ?? 0)),
            'history_source' => trim((string) ($_GET['history_source'] ?? '')),
            'history_status' => trim((string) ($_GET['history_status'] ?? '')),
            'movement_q' => trim((string) ($_GET['movement_q'] ?? '')),
            'movement_from' => trim((string) ($_GET['movement_from'] ?? '')),
            'movement_to' => trim((string) ($_GET['movement_to'] ?? '')),
            'movement_product' => max(0, (int) ($_GET['movement_product'] ?? 0)),
            'movement_source' => trim((string) ($_GET['movement_source'] ?? '')),
            'movement_type' => trim((string) ($_GET['movement_type'] ?? '')),
        ];
        $service = new InventoryService();
        $data = $service->dashboardData($filters);
        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        $this->view('inventory', [
            'data' => $data,
            'filters' => $filters,
            'tab' => trim((string) ($_GET['tab'] ?? 'stock')) ?: 'stock',
            'canManage' => PermissionService::canManageInventory(),
            'error' => $error,
            'success' => $success,
        ]);
    }
    private function campaignAnalyticsPanel(): void
    {
        $from = $this->optionalDateParam('from');
        $to = $this->optionalDateParam('to');
        $channel = strtolower(trim((string) ($_GET['channel'] ?? '')));
        $channel = in_array($channel, ['email', 'sms', 'whatsapp'], true) ? $channel : '';
        $filters = [
            'campaign' => trim((string) ($_GET['campaign'] ?? '')),
            'source' => trim((string) ($_GET['source'] ?? '')),
            'medium' => trim((string) ($_GET['medium'] ?? '')),
            'channel' => $channel,
        ];

        if ($filters['campaign'] === '' || $filters['channel'] === '') {
            $this->json(['ok' => false, 'message' => 'Selecciona campaña y canal antes de consultar.'], 422);
            return;
        }

        $this->json([
            'ok' => true,
            'filters' => array_merge($filters, ['from' => $from, 'to' => $to]),
            'data' => (new AnalyticsRepository())->campaignPanel($from, $to, $filters),
        ]);
    }
    private function dateParam(string $key, string $fallback): string
    {
        $value = (string) ($_GET[$key] ?? $fallback);
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : $fallback;
    }
    private function optionalDateParam(string $key): string
    {
        $value = trim((string) ($_GET[$key] ?? ''));
        if ($value === '') {
            return '';
        }
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }
    private function postedDate(string $key, string $fallback): string
    {
        $value = trim((string) ($_POST[$key] ?? $fallback));
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : $fallback;
    }
    private function monthParam(string $key, string $fallback): string
    {
        $value = trim((string) ($_GET[$key] ?? $fallback));
        $date = \DateTime::createFromFormat('Y-m', $value);
        return $date && $date->format('Y-m') === $value ? $value : $fallback;
    }
    private function analyticsTab(): string
    {
        $tab = (string) ($_GET['tab'] ?? 'general');
        return in_array($tab, ['general', 'campanas', 'inmuebles', 'eventos', 'logs'], true) ? $tab : 'general';
    }
    private function analyticsFilters(): array
    {
        $keys = ['source', 'medium', 'campaign', 'channel', 'country', 'city', 'user_type', 'event_type', 'search_ref', 'q'];
        $filters = [];
        $legacyFilters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];
        foreach ($keys as $key) {
            $filters[$key] = trim((string) ($_GET[$key] ?? $legacyFilters[$key] ?? ''));
        }
        return $filters;
    }
    private function actorFilters(array $config): array
    {
        $filters = [];
        $list = $config['vista'] ?? [];
        $allowed = array_filter($list, static fn ($c) => !str_starts_with((string) $c, 'v_') && $c !== 'author_name' && $c !== 'total_familia');
        foreach ($allowed as $column) {
            $filters[$column] = trim((string) ($_GET['f'][$column] ?? ''));
        }
        if (in_array('v_estado', $list, true)) {
            $filters['v_estado'] = trim((string) ($_GET['f']['v_estado'] ?? ''));
        }
        if (in_array('author_name', $list, true)) {
            $filters['author_name'] = trim((string) ($_GET['f']['author_name'] ?? ''));
        }
        if (in_array(($config['table'] ?? ''), [Database::table('jet_cct_newsletter'), Database::table('jet_cct_club_pph')], true)) {
            $filters['fecha'] = trim((string) ($_GET['f']['fecha'] ?? ''));
        }
        if (!empty($config['es_actor'])) {
            $filters['campaign_tag'] = trim((string) ($_GET['f']['campaign_tag'] ?? ''));
            $filters['campaign_channel'] = trim((string) ($_GET['f']['campaign_channel'] ?? ''));
            $filters['campaign_delivery'] = trim((string) ($_GET['f']['campaign_delivery'] ?? ''));
            if ($filters['campaign_tag'] !== '' && $filters['campaign_delivery'] === '') $filters['campaign_delivery'] = 'exclude_any';
        }
        return $filters;
    }
    private function actorConfigForView(string $type): array
    {
        $config = ActorCatalog::get($type) ?? [];
        foreach (($config['opciones_filtros'] ?? []) as $column => $options) {
            if ($options === '__distinct__') {
                $config['opciones_filtros'][$column] = ActorColumnResolver::distinctValues($type, (string) $column);
            } elseif ($options === '__cargos__') {
                $config['opciones_filtros'][$column] = ActorColumnResolver::cargosListForFilter();
            }
        }
        foreach (($config['campos'] ?? []) as &$field) {
            $fieldId = (string) ($field['id'] ?? '');
            if (($field['type'] ?? '') === 'select' && isset($config['opciones_filtros'][$fieldId]) && empty($field['opts'])) {
                $field['opts'] = $config['opciones_filtros'][$fieldId];
            }
        }
        unset($field);
        return $config;
    }
    private function postedActorFilters(string $type): array
    {
        $config = ActorCatalog::get($type) ?? [];
        $filters = [];
        $list = $config['vista'] ?? [];
        $allowed = array_filter($list, static fn ($c) => !str_starts_with((string) $c, 'v_') && $c !== 'author_name' && $c !== 'total_familia');
        foreach ($allowed as $column) {
            $filters[$column] = trim((string) ($_POST['f'][$column] ?? ''));
        }
        if (in_array('author_name', $list, true)) {
            $filters['author_name'] = trim((string) ($_POST['f']['author_name'] ?? ''));
        }
        if (in_array(($config['table'] ?? ''), [Database::table('jet_cct_newsletter'), Database::table('jet_cct_club_pph')], true)) {
            $filters['fecha'] = trim((string) ($_POST['f']['fecha'] ?? ''));
        }
        if (!empty($config['es_actor'])) {
            $filters['campaign_tag'] = trim((string) ($_POST['f']['campaign_tag'] ?? ''));
            $filters['campaign_channel'] = trim((string) ($_POST['f']['campaign_channel'] ?? ''));
            $filters['campaign_delivery'] = trim((string) ($_POST['f']['campaign_delivery'] ?? ''));
            if ($filters['campaign_tag'] !== '' && $filters['campaign_delivery'] === '') $filters['campaign_delivery'] = 'exclude_any';
        }
        return $filters;
    }
    private function view(string $template, array $data = []): void
    {
        extract($data);
        $view = dirname(__DIR__) . '/views/' . $template . '.php';
        require dirname(__DIR__) . '/views/layout.php';
    }
}
