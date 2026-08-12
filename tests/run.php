<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\ActorPhone;
use App\CountryCatalog;

$failures = 0;
$assertSame = static function (mixed $expected, mixed $actual, string $label) use (&$failures): void {
    if ($expected === $actual) {
        echo "OK  {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL {$label}: esperado " . var_export($expected, true) . ', recibido ' . var_export($actual, true) . "\n";
};

$assertSame('+1268', CountryCatalog::normalizeCallingCode('+1-268'), 'normaliza indicativo con guion');
$assertSame('+57', CountryCatalog::normalizeCallingCode(' 57 '), 'normaliza indicativo sin signo');
$assertSame('', CountryCatalog::normalizeCallingCode(''), 'conserva indicativo vacío');
$assertSame('3001234567', ActorPhone::national('+57 300-123-4567', '+57'), 'elimina prefijo duplicado');
$assertSame('3001234567', ActorPhone::national('300 123 4567', '+57'), 'limpia número nacional');
$assertSame('+573001234567', ActorPhone::international('3001234567', '+57'), 'genera E.164');
$assertSame('+12685551234', ActorPhone::international('+1-268 555-1234', '+1-268'), 'genera E.164 con código compuesto');

exit($failures === 0 ? 0 : 1);
