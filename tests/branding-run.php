<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\BrandAssetService;
use App\Database;

$failures = 0;
$assert = static function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo "OK  {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL {$label}\n";
};

$service = new BrandAssetService();
$table = Database::table('marketing_brand_assets');
$linksTable = Database::table('marketing_brand_asset_posts');
$assert(Database::tableExists($table), 'crea la tabla aislada del Banco de Piezas');
$assert(Database::tableExists($linksTable), 'crea la relación entre piezas y publicaciones');
$assert(count(BrandAssetService::categories()) === 7, 'expone las siete categorías definidas');
$assert(count(BrandAssetService::statuses()) === 6, 'expone los seis estados del flujo');
$assert(count(BrandAssetService::channels()) === 7, 'expone los canales de uso previstos');

$data = $service->dashboardData(['active' => '1']);
$assert(isset($data['summary']['branding_progress']), 'calcula el avance de branding');
$assert(isset($data['summary']['visual_consistency']), 'calcula la coherencia visual');
$assert(isset($data['assets'], $data['channelCounts'], $data['topAssets']), 'entrega datos de galería e impacto');

$socialPosts = $service->socialPosts();
$assert(is_array($socialPosts), 'entrega publicaciones sincronizadas para el selector');
$assert($socialPosts === [] || isset($socialPosts[0]['views'], $socialPosts[0]['interactions'], $socialPosts[0]['clicks']), 'normaliza los KPIs de cada publicación');

$invalidAssetRejected = false;
try {
    $service->replacePostLinks(0, [], 1);
} catch (\RuntimeException $e) {
    $invalidAssetRejected = str_contains($e->getMessage(), 'no existe');
}
$assert($invalidAssetRejected, 'rechaza vínculos para piezas inexistentes');

$missingFileRejected = false;
try {
    $service->save([
        'title' => 'Pieza de validación',
        'category' => 'branding',
        'status' => 'designed',
        'channels' => ['web'],
        'creation_date' => date('Y-m-d'),
        'brand_compliance' => '80',
    ], [], 1);
} catch (\RuntimeException $e) {
    $missingFileRejected = str_contains($e->getMessage(), 'Adjunta el arte final');
}
$assert($missingFileRejected, 'exige un arte final al crear una pieza');

exit($failures === 0 ? 0 : 1);
