import type { ReactNode } from 'react';
import { MoreHorizontal } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface TableRowActionsProps {
    label: string;
    children: ReactNode;
}

export function TableRowActions({ label, children }: TableRowActionsProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    className="table-row-actions-trigger"
                    aria-label={`Actions for ${label}`}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="table-row-actions-menu">
                {children}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
