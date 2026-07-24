<?php

define('HAVER_FORCE_ADMIN', true);

$rootDir = dirname(__DIR__);
$bootstrapFile = $rootDir . '/HaverFotografie.php';

if (!is_file($bootstrapFile)) {
    $bootstrapFile = $rootDir . '/index.php';
}

require $bootstrapFile;
