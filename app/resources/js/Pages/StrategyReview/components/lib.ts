export const fieldLabels: Record<string, string> = {
    review_date: 'Review date',
    objective: 'Objective',
    directly_contributing_units: 'Directly contributing units',
    measure: 'Measure',
    target: 'Target',
    actual_to_date_measure: 'Actual to date measure',
    status_measure: 'Measure status',
    kra1_key_results_area: 'KRA 1 key results area',
    kra1_deliverable: 'KRA 1 deliverable',
    kra1_actual_to_date: 'KRA 1 actual to date',
    kra1_status: 'KRA 1 status',
    kra2_key_results_area: 'KRA 2 key results area',
    kra2_deliverable: 'KRA 2 deliverable',
    kra2_actual_to_date: 'KRA 2 actual to date',
    kra2_status: 'KRA 2 status',
    kra3_key_results_area: 'KRA 3 key results area',
    kra3_deliverable: 'KRA 3 deliverable',
    kra3_actual_to_date: 'KRA 3 actual to date',
    kra3_status: 'KRA 3 status',
    continue: 'Continue',
    stop: 'Stop',
    start: 'Start',
    prepared_by: 'Prepared by',
    approved_by: 'Approved by',
};

export function blankForm(fields: string[]): Record<string, string> {
    return Object.fromEntries(fields.map((field) => [field, '']));
}
