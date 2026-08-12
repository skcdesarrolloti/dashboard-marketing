<?php

declare(strict_types=1);

namespace App;

final class ActorPhone
{
    public static function clean(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    public static function national(string $phone, string $callingCode = ''): string
    {
        $clean = self::clean($phone);
        $prefix = self::clean($callingCode);
        if ($prefix !== '' && str_starts_with($clean, $prefix) && strlen($clean) > strlen($prefix) + 5) {
            $clean = substr($clean, strlen($prefix));
        }
        return $clean;
    }

    public static function international(string $phone, string $callingCode): string
    {
        $national = self::national($phone, $callingCode);
        $prefix = self::clean($callingCode);
        return $national === '' ? '' : ($prefix === '' ? $national : '+' . $prefix . $national);
    }
}
