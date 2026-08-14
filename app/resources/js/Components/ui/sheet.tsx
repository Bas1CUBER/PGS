import * as React from 'react';

import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogSurface,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

function Sheet({ ...props }: React.ComponentProps<typeof Dialog>) {
    return <Dialog {...props} />;
}

function SheetTrigger({ ...props }: React.ComponentProps<typeof DialogTrigger>) {
    return <DialogTrigger data-slot="sheet-trigger" {...props} />;
}

function SheetClose({ ...props }: React.ComponentProps<typeof DialogClose>) {
    return <DialogClose data-slot="sheet-close" {...props} />;
}

function SheetContent({
    className,
    children,
    side = 'right',
    showCloseButton = true,
    ...props
}: React.HTMLAttributes<HTMLDivElement> & {
    side?: 'top' | 'right' | 'bottom' | 'left';
    showCloseButton?: boolean;
}) {
    return (
        <DialogSurface
            data-slot="sheet-content"
            data-side={side}
            className={cn(
                'bg-popover text-popover-foreground data-open:animate-in data-open:fade-in-0 data-[side=bottom]:data-open:slide-in-from-bottom-10 data-[side=left]:data-open:slide-in-from-left-10 data-[side=right]:data-open:slide-in-from-right-10 data-[side=top]:data-open:slide-in-from-top-10 data-closed:animate-out data-closed:fade-out-0 data-[side=bottom]:data-closed:slide-out-to-bottom-10 data-[side=left]:data-closed:slide-out-to-left-10 data-[side=right]:data-closed:slide-out-to-right-10 data-[side=top]:data-closed:slide-out-to-top-10 fixed z-50 flex flex-col bg-clip-padding text-xs/relaxed shadow-lg transition duration-200 ease-in-out data-[side=bottom]:inset-x-0 data-[side=bottom]:bottom-0 data-[side=bottom]:h-auto data-[side=bottom]:border-t data-[side=left]:inset-y-0 data-[side=left]:left-0 data-[side=left]:h-full data-[side=left]:w-3/4 data-[side=left]:border-r data-[side=right]:inset-y-0 data-[side=right]:right-0 data-[side=right]:h-full data-[side=right]:w-3/4 data-[side=right]:border-l data-[side=top]:inset-x-0 data-[side=top]:top-0 data-[side=top]:h-auto data-[side=top]:border-b data-[side=left]:sm:max-w-sm data-[side=right]:sm:max-w-sm',
                className,
            )}
            closeButtonClassName="top-4 right-4"
            showCloseButton={showCloseButton}
            {...props}
        >
            {children}
        </DialogSurface>
    );
}

function SheetHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sheet-header"
            className={cn('flex flex-col gap-1.5 p-6', className)}
            {...props}
        />
    );
}

function SheetFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sheet-footer"
            className={cn('mt-auto flex flex-col gap-2 p-6', className)}
            {...props}
        />
    );
}

function SheetTitle({ className, ...props }: React.ComponentProps<typeof DialogTitle>) {
    return (
        <DialogTitle
            data-slot="sheet-title"
            className={cn('font-heading text-foreground text-sm font-medium', className)}
            {...props}
        />
    );
}

function SheetDescription({ className, ...props }: React.ComponentProps<typeof DialogDescription>) {
    return (
        <DialogDescription
            data-slot="sheet-description"
            className={cn('text-muted-foreground text-xs/relaxed', className)}
            {...props}
        />
    );
}

export {
    Sheet,
    SheetTrigger,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetFooter,
    SheetTitle,
    SheetDescription,
};
