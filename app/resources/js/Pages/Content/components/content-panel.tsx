import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function ContentPanel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card className="h-full">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="text-muted-foreground min-h-40 text-sm leading-7 whitespace-pre-wrap">
                {children}
            </CardContent>
        </Card>
    );
}
