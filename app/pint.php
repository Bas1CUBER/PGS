<?php

declare(strict_types=1);

return [
    'preset' => 'laravel',
    'rules' => [
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'blank_line_before_statement' => ['statements' => ['return']],
    ],
];
