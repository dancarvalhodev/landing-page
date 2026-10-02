<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create();
$finder->in([
    __DIR__ . '/bootstrap',
    __DIR__ . '/config',
    __DIR__ . '/public',
    __DIR__ . '/routes',
    __DIR__ . '/src',
    __DIR__ . '/tests',
]);
$finder->append([__FILE__]);

$config = new PhpCsFixer\Config();
$config->setFinder($finder);
$config->setRiskyAllowed(true);
$config->setRules([
    '@PER-CS' => true,
    'declare_strict_types' => true,
    'no_unused_imports' => true,
    'ordered_imports' => true,
]);

return $config;
