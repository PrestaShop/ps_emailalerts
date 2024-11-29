<?php

$psConfig = new PrestaShop\CodingStandards\CsFixer\Config();

$config = new PhpCsFixer\Config();
$config
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setRules(array_merge($psConfig->getRules(), [
        'blank_lines_before_namespace' => false,
        'nullable_type_declaration_for_default_null_value' => true,
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag' => false,
    ]))
    ->getFinder()
    ->in(__DIR__)
    ->exclude('vendor');

return $config;
