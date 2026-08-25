import { Save } from 'lucide-react';
import { type Dispatch, type SetStateAction } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { ContentPanel } from '@/Pages/Content/components/content-panel';
import type { CharterContent } from '@/Pages/Content/components/types';

interface CharterSectionProps {
    charter: CharterContent;
    setCharter: Dispatch<SetStateAction<CharterContent>>;
    onSave: () => void;
    isPending: (action: string) => boolean;
    canManage: boolean;
}

export function CharterSection({
    charter,
    setCharter,
    onSave,
    isPending,
    canManage,
}: CharterSectionProps) {
    return (
        <>
            <div className="grid gap-4 lg:grid-cols-3">
                <ContentPanel title="Vision">{charter.vision}</ContentPanel>
                <ContentPanel title="Mission">{charter.mission}</ContentPanel>
                <ContentPanel title="Core values">
                    <div className="space-y-2">
                        {charter.core_values.map((value) => (
                            <div key={value} className="pgs-content-chip">
                                {value}
                            </div>
                        ))}
                    </div>
                </ContentPanel>
            </div>
            {canManage && (
                <Card>
                    <CardHeader>
                        <CardTitle>Edit charter statements</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="vision">Vision</Label>
                            <textarea
                                id="vision"
                                value={charter.vision}
                                onChange={(e) => {
                                    setCharter({ ...charter, vision: e.target.value });
                                }}
                                rows={4}
                                className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="mission">Mission</Label>
                            <textarea
                                id="mission"
                                value={charter.mission}
                                onChange={(e) => {
                                    setCharter({ ...charter, mission: e.target.value });
                                }}
                                rows={4}
                                className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="core-values">Core values</Label>
                            <textarea
                                id="core-values"
                                value={charter.core_values.join('\n')}
                                onChange={(e) => {
                                    setCharter({
                                        ...charter,
                                        core_values: e.target.value.split(/\r?\n/).filter(Boolean),
                                    });
                                }}
                                rows={4}
                                className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                            />
                            <p className="text-muted-foreground text-xs">
                                Enter one value per line.
                            </p>
                        </div>
                        <Button
                            onClick={onSave}
                            loading={isPending('structured')}
                            loadingText="Saving"
                        >
                            <Save className="size-4" /> Save statements
                        </Button>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
