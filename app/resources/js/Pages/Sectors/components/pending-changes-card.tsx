import { CheckCircle2, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PendingDecisionTarget, SectorShowPageProps } from './types';

interface PendingChangesCardProps {
    changes: SectorShowPageProps['pendingChanges'];
    isPending: (action: string) => boolean;
    onSelectDecision: (target: PendingDecisionTarget) => void;
}

export function PendingChangesCard({
    changes,
    isPending,
    onSelectDecision,
}: PendingChangesCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Pending roadmap changes</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {changes.map((change) => (
                    <div
                        key={change.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                    >
                        <div>
                            <p className="text-sm font-medium">
                                {change.change_type === 'add_row'
                                    ? 'New indicator'
                                    : 'Progress update'}{' '}
                                — {change.category} ({change.year})
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {change.description ?? change.status ?? 'No details'} ·{' '}
                                {new Date(change.submitted_at).toLocaleString()}
                            </p>
                        </div>
                        <div className="flex gap-1">
                            <Button
                                className="pgs-success-action-button"
                                onClick={() => {
                                    onSelectDecision({
                                        id: change.id,
                                        decision: 'Approved',
                                        category: change.category,
                                        year: change.year,
                                        description: change.description,
                                    });
                                }}
                                loading={isPending(`decision:${String(change.id)}`)}
                            >
                                <CheckCircle2 className="size-4" /> Approve
                            </Button>
                            <Button
                                variant="destructive"
                                className="pgs-destructive-action-button"
                                onClick={() => {
                                    onSelectDecision({
                                        id: change.id,
                                        decision: 'Rejected',
                                        category: change.category,
                                        year: change.year,
                                        description: change.description,
                                    });
                                }}
                                loading={isPending(`decision:${String(change.id)}`)}
                            >
                                <XCircle className="size-4" /> Reject
                            </Button>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
