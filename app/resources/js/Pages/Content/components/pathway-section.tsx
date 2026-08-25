import { Save } from 'lucide-react';
import { type Dispatch, type SetStateAction } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PathwayPanel } from '@/Pages/Content/components/types';

interface PathwaySectionProps {
    panels: PathwayPanel[];
    setPanels: Dispatch<SetStateAction<PathwayPanel[]>>;
    onSave: () => void;
    isPending: (action: string) => boolean;
    canManage: boolean;
}

export function PathwaySection({
    panels,
    setPanels,
    onSave,
    isPending,
    canManage,
}: PathwaySectionProps) {
    function updatePanel(index: number, key: 'title' | 'status' | 'text', value: string): void {
        setPanels((current) =>
            current.map((panel, panelIndex) =>
                panelIndex === index ? { ...panel, [key]: value } : panel,
            ),
        );
    }

    return (
        <>
            <div className="grid gap-4 md:grid-cols-2">
                {panels.map((panel, index) => (
                    <Card key={`${panel.title}-${String(index)}`}>
                        <CardHeader>
                            <CardTitle>{panel.title || `Panel ${String(index + 1)}`}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {panel.image && (
                                <img
                                    src={`/legacy-img/${encodeURIComponent(panel.image)}`}
                                    alt={panel.title}
                                    className="max-h-72 w-full rounded-lg object-contain"
                                />
                            )}
                            <p className="text-muted-foreground text-sm whitespace-pre-wrap">
                                {panel.text || 'No panel content yet.'}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>
            {canManage && (
                <Card>
                    <CardHeader>
                        <CardTitle>Edit pathway panels</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {panels.map((panel, index) => (
                            <div
                                key={`edit-${String(index)}`}
                                className="grid gap-3 rounded-xl border p-4 md:grid-cols-2"
                            >
                                <div className="space-y-2">
                                    <Label>Panel title</Label>
                                    <Input
                                        value={panel.title}
                                        onChange={(e) => {
                                            updatePanel(index, 'title', e.target.value);
                                        }}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Input
                                        value={panel.status}
                                        onChange={(e) => {
                                            updatePanel(index, 'status', e.target.value);
                                        }}
                                    />
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label>Text</Label>
                                    <textarea
                                        value={panel.text}
                                        onChange={(e) => {
                                            updatePanel(index, 'text', e.target.value);
                                        }}
                                        rows={3}
                                        className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                        ))}
                        <Button
                            onClick={onSave}
                            loading={isPending('structured')}
                            loadingText="Saving"
                        >
                            <Save className="size-4" /> Save panels
                        </Button>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
