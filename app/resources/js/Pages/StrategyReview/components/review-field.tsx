import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ReviewFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    area?: boolean;
    type?: string;
    error?: string;
}

export function ReviewField({
    label,
    value,
    onChange,
    area = false,
    type = 'text',
    error,
}: ReviewFieldProps) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {area ? (
                <textarea
                    value={value}
                    onChange={(e) => {
                        onChange(e.target.value);
                    }}
                    rows={3}
                    className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                />
            ) : (
                <Input
                    type={type}
                    value={value}
                    onChange={(e) => {
                        onChange(e.target.value);
                    }}
                />
            )}
            {error && <p className="text-destructive text-sm">{error}</p>}
        </div>
    );
}
