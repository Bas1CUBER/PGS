import { Pencil, Save } from 'lucide-react';
import { type Dispatch, type SetStateAction } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AccessTable } from '@/Pages/Content/components/access-table';
import type { AccessMatrix, CharterContent, PathwayPanel } from '@/Pages/Content/components/types';

interface AccessSectionProps {
    structuredContent: PathwayPanel[] | CharterContent | AccessMatrix | null;
    matrix: string;
    setMatrix: Dispatch<SetStateAction<string>>;
    onSave: () => void;
    isPending: (action: string) => boolean;
    canManage: boolean;
}

export function AccessSection({
    structuredContent,
    matrix,
    setMatrix,
    onSave,
    isPending,
    canManage,
}: AccessSectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>User access matrix</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto">
                {structuredContent !== null &&
                typeof structuredContent === 'object' &&
                !Array.isArray(structuredContent) &&
                'columns' in structuredContent &&
                'rows' in structuredContent ? (
                    <AccessTable matrix={structuredContent} />
                ) : (
                    <p className="text-muted-foreground text-sm">Access matrix not available.</p>
                )}
                {canManage && (
                    <div className="mt-6 space-y-3">
                        <div className="flex items-center gap-2">
                            <Pencil className="size-4" />
                            <p className="font-medium">Edit matrix JSON</p>
                        </div>
                        <textarea
                            value={matrix}
                            onChange={(e) => {
                                setMatrix(e.target.value);
                            }}
                            rows={18}
                            className="border-input bg-background flex w-full min-w-[680px] rounded-md border px-3 py-2 font-mono text-xs"
                        />
                        <Button
                            onClick={onSave}
                            loading={isPending('structured')}
                            loadingText="Saving"
                        >
                            <Save className="size-4" /> Save matrix
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
