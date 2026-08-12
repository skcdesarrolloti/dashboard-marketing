<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

$projectRoot = dirname(__DIR__);
$GLOBALS['environment_file'] = app_load_environment($projectRoot);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($path)) {
        require $path;
    }
});

$GLOBALS['config'] = require $projectRoot . '/config/app.php';

date_default_timezone_set((string) ($GLOBALS['config']['app']['timezone'] ?? 'America/Bogota'));

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function system_image(string $function, string $fallback = 'https://sucasainmobiliaria.com.co/wp-content/uploads/2026/06/isologo-skc.png'): string
{
    static $cache = [];

    if (array_key_exists($function, $cache)) {
        return $cache[$function];
    }

    try {
        $table = \App\Database::table('jet_cct_confi_sistema');
        if (!\App\Database::tableExists($table)) {
            return $cache[$function] = $fallback;
        }

        $url = \App\Database::value(
            'SELECT COALESCE(NULLIF(imagen, ""), NULLIF(valor, ""))
             FROM ' . $table . '
             WHERE funcion = ?
             ORDER BY _ID DESC
             LIMIT 1',
            's',
            [$function]
        );

        $url = trim((string) $url);
        return $cache[$function] = $url !== '' ? $url : $fallback;
    } catch (Throwable) {
        return $cache[$function] = $fallback;
    }
}

function url(array $params = []): string
{
    $params = clean_url_params($params);
    $page = trim((string) ($params['page'] ?? ''));
    unset($params['page']);

    $base = clean_url_base();
    if ($page === '' || $page === 'dashboard') {
        $path = $base === '' ? '/' : $base . '/';
    } else {
        $path = ($base === '' ? '' : $base) . '/' . rawurlencode($page);
    }

    return $path . ($params ? '?' . http_build_query($params) : '');
}

function url_page(string $page = 'dashboard', array $params = []): string
{
    return url(array_merge(['page' => $page], $params));
}

function clean_url_base(): string
{
    $script = strtok((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '?') ?: '/index.php';
    $script = str_replace('\\', '/', $script);
    $dir = trim(str_replace('\\', '/', dirname($script)), '/');
    return $dir === '' || $dir === '.' ? '' : '/' . $dir;
}

function clean_url_params(array $params): array
{
    $clean = [];
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            $nested = clean_url_params($value);
            if ($nested !== []) {
                $clean[$key] = $nested;
            }
            continue;
        }

        if ($value === null) {
            continue;
        }

        $text = trim((string) $value);
        if ($text === '') {
            continue;
        }

        $clean[$key] = $value;
    }

    return $clean;
}

function redirect_to(array $params = []): never
{
    header('Location: ' . url($params));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['_csrf'])
        && hash_equals((string) $_SESSION['_csrf'], $token);
}
