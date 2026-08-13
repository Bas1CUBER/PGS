<?php

declare(strict_types=1);

/**
 * Upload "view document" pages config (drives src/Modules/upload_view_page.php).
 */
return [
    'cascading_activities_view' => [
        'table' => 'cascading_activities',
        'alias' => 'a',
        'join_col' => 'uploaded_by',
        'upload_dir' => 'uploads/cascading_activities/',
        'back_page' => 'cascading_activities',
        'access' => 'cascading',
    ],
    'communication_plan_view' => [
        'table' => 'communication_plan_uploads',
        'alias' => 'o',
        'join_col' => 'employee_id',
        'upload_dir' => 'uploads/communication_plan/',
        'back_page' => 'communication_plan',
        'access' => 'cascading',
    ],
    'governance_culture_view' => [
        'table' => 'governance_culture_uploads',
        'alias' => 'g',
        'join_col' => 'employee_id',
        'upload_dir' => 'uploads/governance_culture/',
        'back_page' => 'governance_culture',
        'access' => 'governance',
    ],
    'governance_sharing_view' => [
        'table' => 'governance_sharing_uploads',
        'alias' => 'g',
        'join_col' => 'employee_id',
        'upload_dir' => 'uploads/governance_sharing/',
        'back_page' => 'governance_sharing',
        'access' => 'governance',
    ],
    'operations_review_view' => [
        'table' => 'operations_review_uploads',
        'alias' => 'o',
        'join_col' => 'employee_id',
        'upload_dir' => 'uploads/operations_review/',
        'back_page' => 'operations_review_new',
        'access' => 'performance_assessment',
    ],
    'strategy_refresh_view' => [
        'table' => 'strategy_refresh_uploads',
        'alias' => 'o',
        'join_col' => 'employee_id',
        'upload_dir' => 'uploads/strategy_refresh/',
        'back_page' => 'strategy_refresh',
        'access' => 'performance_assessment',
    ],
    'strategy_review_view' => [
        'table' => 'strategy_review_uploads',
        'alias' => 's',
        'join_col' => 'employee_id',
        'upload_dir' => 'uploads/strategy_review/',
        'back_page' => 'strategy_review',
        'access' => 'performance_assessment',
    ],
];
