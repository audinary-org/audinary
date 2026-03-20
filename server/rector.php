<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/public',
        __DIR__ . '/routes',
        __DIR__ . '/scripts',
        __DIR__ . '/src',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/migrations', // Nur SQL files
        __DIR__ . '/var',
        __DIR__ . '/templates',
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        strictBooleans: true
    );
