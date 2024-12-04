<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'nullable_type_declaration_for_default_null_value' => true,
    ])
    ->setFinder($finder)
;
