<?php

declare(strict_types=1);

namespace App;

final class CountryCatalog
{
    public static function all(): array
    {
        static $countries;
        if (is_array($countries)) {
            return $countries;
        }

        $table = Database::table('jet_cct_paises');
        if (!Database::tableExists($table)) {
            return $countries = [];
        }

        $rows = Database::rows(
            "SELECT _ID, pais, codigo
               FROM {$table}
              WHERE cct_status = 'publish'
                AND TRIM(COALESCE(pais, '')) != ''
                AND TRIM(COALESCE(codigo, '')) != ''
              ORDER BY pais ASC"
        );

        $countries = [];
        foreach ($rows as $row) {
            $id = (int) ($row['_ID'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $countries[$id] = [
                'id' => $id,
                'name' => trim((string) ($row['pais'] ?? '')),
                'calling_code' => self::normalizeCallingCode((string) ($row['codigo'] ?? '')),
            ];
        }
        return $countries;
    }

    public static function find(int $id): array
    {
        return self::all()[$id] ?? [];
    }

    public static function selectedId(string $country, string $callingCode): int
    {
        $country = self::fold($country);
        $callingCode = self::normalizeCallingCode($callingCode);
        foreach (self::all() as $item) {
            if ($country !== '' && self::fold((string) $item['name']) === $country) {
                return (int) $item['id'];
            }
        }

        $matches = array_values(array_filter(
            self::all(),
            static fn (array $item): bool => $callingCode !== '' && $item['calling_code'] === $callingCode
        ));
        return count($matches) === 1 ? (int) $matches[0]['id'] : 0;
    }

    public static function colombiaId(): int
    {
        foreach (self::all() as $item) {
            if (self::fold((string) $item['name']) === 'colombia') {
                return (int) $item['id'];
            }
        }
        return 0;
    }

    public static function normalizeCallingCode(string $code): string
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        return $digits === '' ? '' : '+' . $digits;
    }

    private static function fold(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        return strtr($value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    }
}
