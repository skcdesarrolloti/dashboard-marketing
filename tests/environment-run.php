<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/environment.php';

$failures = 0;
$assertSame = static function (mixed $expected, mixed $actual, string $label) use (&$failures): void {
    if ($expected === $actual) {
        echo "OK  {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL {$label}: esperado " . var_export($expected, true)
        . ', recibido ' . var_export($actual, true) . "\n";
};

$variables = [
    'SKC_ENV_TEST_PLAIN',
    'SKC_ENV_TEST_QUOTED',
    'SKC_ENV_TEST_HASH',
    'SKC_ENV_TEST_EXISTING',
];

foreach ($variables as $variable) {
    putenv($variable);
    unset($_ENV[$variable], $_SERVER[$variable]);
}

$temporaryPath = tempnam(sys_get_temp_dir(), 'skc-env-');
if ($temporaryPath === false) {
    exit("FAIL no fue posible crear el archivo temporal\n");
}

file_put_contents($temporaryPath, implode("\n", [
    '# comentario',
    'SKC_ENV_TEST_PLAIN=valor',
    'SKC_ENV_TEST_QUOTED="valor con espacios"',
    'SKC_ENV_TEST_HASH=token#con#numerales',
    'SKC_ENV_TEST_EXISTING=archivo',
]));

putenv('SKC_ENV_TEST_EXISTING=servidor');
app_load_environment_file($temporaryPath);

$assertSame('valor', getenv('SKC_ENV_TEST_PLAIN'), 'carga valor simple');
$assertSame('valor con espacios', getenv('SKC_ENV_TEST_QUOTED'), 'carga valor entre comillas');
$assertSame('token#con#numerales', getenv('SKC_ENV_TEST_HASH'), 'conserva numeral dentro del valor');
$assertSame('servidor', getenv('SKC_ENV_TEST_EXISTING'), 'respeta variable existente del servidor');

unlink($temporaryPath);
foreach ($variables as $variable) {
    putenv($variable);
    unset($_ENV[$variable], $_SERVER[$variable]);
}

exit($failures === 0 ? 0 : 1);
