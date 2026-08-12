<?php

declare(strict_types=1);

namespace App;

final class InventoryCategoryStore
{
    private const DEFAULTS = ['PPH', 'Souvenir'];

    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? dirname(__DIR__) . '/storage/inventory-categories.json';
    }

    public function all(): array
    {
        if (!is_file($this->path)) {
            return $this->merge([]);
        }

        $handle = @fopen($this->path, 'rb');
        if ($handle === false) {
            throw new InventoryException('No fue posible leer las categorías del inventario.');
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new InventoryException('No fue posible bloquear el archivo de categorías.');
            }
            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $this->decode((string) $contents);
    }

    public function add(string $name): string
    {
        $category = $this->normalize($name);
        $categories = $this->writeLocked(array_merge(self::DEFAULTS, [$category]));

        foreach ($categories as $stored) {
            if ($this->key($stored) === $this->key($category)) {
                return $stored;
            }
        }

        throw new InventoryException('No fue posible guardar la categoría.');
    }

    public function merge(array $names): array
    {
        $normalized = [];
        foreach (array_merge(self::DEFAULTS, $names) as $name) {
            $text = trim((string) $name);
            if ($text !== '') {
                $normalized[] = $this->normalize($text);
            }
        }

        return $this->writeLocked($normalized);
    }

    public function contains(string $name): bool
    {
        $key = $this->key($this->normalize($name));
        foreach ($this->all() as $category) {
            if ($this->key($category) === $key) {
                return true;
            }
        }
        return false;
    }

    private function writeLocked(array $additions): array
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new InventoryException('No fue posible crear el almacenamiento de categorías.');
        }

        $handle = @fopen($this->path, 'c+b');
        if ($handle === false) {
            throw new InventoryException('No fue posible abrir el archivo de categorías para escritura.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new InventoryException('No fue posible bloquear el archivo de categorías.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $categories = trim((string) $contents) === '' ? [] : $this->decode((string) $contents);
            $byKey = [];
            foreach (array_merge($categories, $additions) as $category) {
                $normalized = $this->normalize((string) $category);
                $byKey[$this->key($normalized)] ??= $normalized;
            }
            $categories = array_values($byKey);
            natcasesort($categories);
            $categories = array_values($categories);

            $json = json_encode(
                ['categories' => $categories],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $json) === false || !fflush($handle)) {
                throw new InventoryException('No fue posible guardar las categorías del inventario.');
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $categories;
    }

    private function decode(string $contents): array
    {
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InventoryException('El archivo JSON de categorías no tiene un formato válido.');
        }

        if (!is_array($decoded) || !is_array($decoded['categories'] ?? null)) {
            throw new InventoryException('El archivo JSON de categorías no contiene la lista esperada.');
        }

        $categories = [];
        foreach ($decoded['categories'] as $category) {
            if (is_string($category) && trim($category) !== '') {
                $normalized = $this->normalize($category);
                $categories[$this->key($normalized)] ??= $normalized;
            }
        }
        return array_values($categories);
    }

    private function normalize(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($length < 2 || $length > 60) {
            throw new InventoryException('La categoría debe tener entre 2 y 60 caracteres.');
        }
        if (preg_match('/[<>{}\[\]\\\\]/u', $name)) {
            throw new InventoryException('La categoría contiene caracteres no permitidos.');
        }
        return $name;
    }

    private function key(string $name): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }
}
