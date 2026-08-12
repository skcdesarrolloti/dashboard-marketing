<?php

declare(strict_types=1);

namespace App;

use Throwable;

final class ReportCache
{
    public static function remember(string $namespace, array $key, int $ttl, callable $loader): array
    {
        $file = self::file($namespace, $key);
        $cached = self::read($file);
        if ($cached !== null && (int) ($cached['expires_at'] ?? 0) >= time()) {
            return (array) ($cached['data'] ?? []);
        }

        $directory = dirname($file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $lock = @fopen($file . '.lock', 'c');
        $ownsLock = is_resource($lock) && @flock($lock, LOCK_EX | LOCK_NB);
        if (!$ownsLock && $cached !== null && (int) ($cached['created_at'] ?? 0) >= time() - 900) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            return (array) ($cached['data'] ?? []);
        }

        try {
            if ($ownsLock) {
                $fresh = self::read($file);
                if ($fresh !== null && (int) ($fresh['expires_at'] ?? 0) >= time()) {
                    return (array) ($fresh['data'] ?? []);
                }
            }

            $data = (array) $loader();
            $payload = json_encode([
                'created_at' => time(),
                'expires_at' => time() + max(1, $ttl),
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($payload)) {
                @file_put_contents($file, $payload, LOCK_EX);
            }

            return $data;
        } finally {
            if ($ownsLock) {
                @flock($lock, LOCK_UN);
            }
            if (is_resource($lock)) {
                fclose($lock);
            }
        }
    }

    public static function forget(string $namespace): void
    {
        $safeNamespace = preg_replace('/[^a-z0-9_-]+/i', '_', $namespace) ?: 'report';
        $directory = dirname(__DIR__) . '/storage/cache';
        foreach (glob($directory . '/' . $safeNamespace . '_*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function file(string $namespace, array $key): string
    {
        $safeNamespace = preg_replace('/[^a-z0-9_-]+/i', '_', $namespace) ?: 'report';
        $encoded = json_encode($key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($key);
        return dirname(__DIR__) . '/storage/cache/' . $safeNamespace . '_' . sha1($encoded) . '.json';
    }

    private static function read(string $file): ?array
    {
        if (!is_readable($file)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($file), true);
            return is_array($decoded) && is_array($decoded['data'] ?? null) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }
}
