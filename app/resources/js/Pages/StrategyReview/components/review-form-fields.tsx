import type { InertiaFormProps } from '@inertiajs/react';
import { fieldLabels } from './lib';
import { ReviewField } from './review-field';

interface ReviewFormFieldsProps {
    form: InertiaFormProps<Record<string, string>>;
}

export function ReviewFormFields({ form }: ReviewFormFieldsProps) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <ReviewField
                    label="Review date"
                    value={form.data.review_date}
                    type="date"
                    error={form.errors.review_date}
                    onChange={(value) => {
                        form.setData('review_date', value);
                    }}
                />
                <ReviewField
                    label="Objective"
                    value={form.data.objective}
                    area
                    error={form.errors.objective}
                    onChange={(value) => {
                        form.setData('objective', value);
                    }}
                />
                <ReviewField
                    label="Directly contributing units"
                    value={form.data.directly_contributing_units}
                    area
                    error={form.errors.directly_contributing_units}
                    onChange={(value) => {
                        form.setData('directly_contributing_units', value);
                    }}
                />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                {['measure', 'target', 'actual_to_date_measure', 'status_measure'].map((field) => (
                    <ReviewField
                        key={field}
                        label={fieldLabels[field] ?? field}
                        value={form.data[field]}
                        error={form.errors[field]}
                        onChange={(value) => {
                            form.setData(field, value);
                        }}
                    />
                ))}
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                {[1, 2, 3].map((number) => (
                    <div
                        key={number}
                        className="pgs-nested-card rounded-[var(--kinetic-radius-control)] p-4"
                    >
                        <p className="mb-3 text-sm font-semibold">KRA {number}</p>
                        <div className="space-y-3">
                            {['key_results_area', 'deliverable', 'actual_to_date', 'status'].map(
                                (suffix) => {
                                    const field = `kra${String(number)}_${suffix}`;
                                    return (
                                        <ReviewField
                                            key={field}
                                            label={fieldLabels[field] ?? field}
                                            value={form.data[field]}
                                            area={suffix === 'key_results_area'}
                                            error={form.errors[field]}
                                            onChange={(value) => {
                                                form.setData(field, value);
                                            }}
                                        />
                                    );
                                },
                            )}
                        </div>
                    </div>
                ))}
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                {['continue', 'stop', 'start'].map((field) => (
                    <ReviewField
                        key={field}
                        label={fieldLabels[field] ?? field}
                        value={form.data[field]}
                        area
                        error={form.errors[field]}
                        onChange={(value) => {
                            form.setData(field, value);
                        }}
                    />
                ))}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <ReviewField
                    label="Prepared by"
                    value={form.data.prepared_by}
                    error={form.errors.prepared_by}
                    onChange={(value) => {
                        form.setData('prepared_by', value);
                    }}
                />
                <ReviewField
                    label="Approved by (unit head)"
                    value={form.data.approved_by}
                    error={form.errors.approved_by}
                    onChange={(value) => {
                        form.setData('approved_by', value);
                    }}
                />
            </div>
        </>
    );
}
