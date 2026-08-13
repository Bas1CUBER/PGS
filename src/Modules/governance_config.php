<?php

declare(strict_types=1);

/**
 * Governance upload modules config (drives src/Modules/governance_page.php).
 */
return [
    'culture' => [
        'title' => 'Governance Culture',
        'table' => 'governance_culture_uploads',
        'upload_dir' => 'uploads/governance_culture/',
        'unique_prefix' => 'gov_culture_',
        'notify_type' => 'governance_culture',
        'css' => 'governance_culture.css',
        'js' => 'governance_culture_1.js',
        'page' => 'governance_culture',
        'view_page' => 'governance_culture_view',
    ],
    'sharing' => [
        'title' => 'Governance Sharing',
        'table' => 'governance_sharing_uploads',
        'upload_dir' => 'uploads/governance_sharing/',
        'unique_prefix' => 'gov_sharing_',
        'notify_type' => 'governance_sharing',
        'css' => 'governance_sharing.css',
        'js' => 'governance_sharing_1.js',
        'page' => 'governance_sharing',
        'view_page' => 'governance_sharing_view',
    ],
];
