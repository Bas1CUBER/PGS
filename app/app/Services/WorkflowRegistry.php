<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliverableStatus;
use App\Models\Deliverable;
use App\Models\StrategyReviewForm;

/**
 * Single source of truth for every production transition map
 * (docs/Workflows.md). The container bindings in AppServiceProvider and
 * the test suite both read from here so they can never drift apart.
 */
final class WorkflowRegistry
{
    /** @return TransitionsWorkflowService<Deliverable> */
    public static function deliverables(): TransitionsWorkflowService
    {
        // Deliverable progress workflow (docs/Workflows.md §2).
        return new TransitionsWorkflowService(Deliverable::class, [
            DeliverableStatus::NotYetStarted->value => [
                ['to' => DeliverableStatus::Ongoing->value, 'actor' => '*'],
                ['to' => DeliverableStatus::Accomplished->value, 'actor' => 'admin|focal'],
            ],
            DeliverableStatus::Ongoing->value => [
                ['to' => DeliverableStatus::Accomplished->value, 'actor' => '*'],
                ['to' => DeliverableStatus::NotYetStarted->value, 'actor' => 'admin|focal'],
            ],
            DeliverableStatus::Accomplished->value => [
                ['to' => DeliverableStatus::Ongoing->value, 'actor' => 'admin|focal'],
            ],
        ]);
    }

    /** @return TransitionsWorkflowService<StrategyReviewForm> */
    public static function strategyReviewForms(): TransitionsWorkflowService
    {
        return new TransitionsWorkflowService(StrategyReviewForm::class, [
            'Draft' => [
                ['to' => 'Submitted', 'actor' => '*'],
            ],
            'Submitted' => [
                ['to' => 'Approved', 'actor' => 'admin|focal'],
                ['to' => 'Returned', 'actor' => 'admin|focal'],
            ],
            'Returned' => [
                ['to' => 'Submitted', 'actor' => '*'],
                ['to' => 'Draft', 'actor' => '*'],
            ],
            // Approved forms are final; no transitions out.
        ]);
    }
}
