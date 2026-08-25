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

function tryParseJson(value: string): { ok: true } | { ok: false; message: string } {
    try {
        const parsed: unknown = JSON.parse(value);
        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return { ok: false, message: 'The matrix must be a JSON object with "columns" and "rows".' };
        }

        return { ok: true };
    } catch (error) {
        return { ok: false, message: error instanceof Error ? error.message : 'Invalid JSON.' };
    }
}

export function AccessSection({
    structuredContent,
    matrix,
    setMatrix,
    onSave,
    isPending,
    canManage,
}: AccessSectionProps) {
    const parseState = tryParseJson(matrix);

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
                            className={`bg-background border-input flex w-full min-w-[680px] rounded-md border px-3 py-2 font-mono text-xs ${
                                parseState.ok ? 'border-input' : 'border-destructive'
                            }`}
                        />
                        {!parseState.ok && (
                            <p className="text-destructive text-sm">
                                Invalid JSON — the server will reject this: {parseState.message}
                            </p>
                        )}
                        <Button
                            onClick={onSave}
                            disabled={!parseState.ok}
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
