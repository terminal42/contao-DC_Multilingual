<?php

$date = date('Y');

$header = <<<EOF
dc_multilingual Extension for Contao Open Source CMS
 
@copyright  Copyright (c) 2011-$date, terminal42 gmbh
@author     terminal42 gmbh <info@terminal42.ch>
@license    http://opensource.org/licenses/lgpl-3.0.html LGPL
@link       http://github.com/terminal42/contao-dc_multilingual
EOF;

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules(
        [
            '@Symfony' => true,
            '@Symfony:risky' => true,
            'array_syntax' => ['syntax' => 'short'],
            'combine_consecutive_unsets' => true,
            'header_comment' => ['header' => $header],
            'heredoc_to_nowdoc' => true,
            'no_extra_consecutive_blank_lines' => ['break', 'continue', 'extra', 'return', 'throw', 'use', 'parenthesis_brace_block', 'square_brace_block', 'curly_brace_block'],
            'no_unreachable_default_argument_value' => true,
            'no_useless_else' => true,
            'no_useless_return' => true,
            'ordered_class_elements' => true,
            'ordered_imports' => true,
            'php_unit_strict' => true,
            'phpdoc_add_missing_param_annotation' => true,
            'phpdoc_order' => true,
            'psr4' => true,
            'strict_comparison' => true,
            'strict_param' => true,
        ]
    )
    ->setFinder(
        PhpCsFixer\Finder::create()->in([__DIR__.'/src'])
    )
;
