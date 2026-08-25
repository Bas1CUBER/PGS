import type { InertiaFormProps } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { blockTypes } from './lib';

interface RoadmapAddBlockFormProps {
    form: InertiaFormProps<{ block_type: string; content: string }>;
    onAddBlock: () => void;
}

export function RoadmapAddBlockForm({ form, onAddBlock }: RoadmapAddBlockFormProps) {
    return (
        <div className="pgs-roadmap-block-form space-y-3">
            <p className="text-sm font-medium">Add block</p>
            <div className="flex flex-col gap-3 sm:flex-row">
                <select
                    value={form.data.block_type}
                    onChange={(e) => {
                        form.setData('block_type', e.target.value);
                    }}
                    className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                    aria-label="Block type"
                >
                    {blockTypes.map((type) => (
                        <option key={type} value={type}>
                            {type}
                        </option>
                    ))}
                </select>
                <Input
                    value={form.data.content}
                    onChange={(e) => {
                        form.setData('content', e.target.value);
                    }}
                    placeholder='{"label":"...", "value":"..."}'
                    className="font-sans"
                    aria-label="Block content JSON"
                />
            </div>
            {form.errors.block_type && (
                <p className="text-destructive text-sm">{form.errors.block_type}</p>
            )}
            {form.errors.content && (
                <p className="text-destructive text-sm">{form.errors.content}</p>
            )}
            <Button size="sm" loading={form.processing} loadingText="Adding" onClick={onAddBlock}>
                <Plus className="size-4" />
                Add block
            </Button>
        </div>
    );
}
