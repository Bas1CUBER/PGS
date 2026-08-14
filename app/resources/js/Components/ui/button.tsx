import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { LoaderCircle } from 'lucide-react';
import { Slot } from 'radix-ui';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    "group/button inline-flex shrink-0 items-center justify-center rounded-lg border border-transparent bg-clip-padding text-sm font-medium whitespace-nowrap transition-all outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 active:not-aria-[haspopup]:translate-y-px disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-primary/80',
                outline:
                    'border-border hover:bg-input/50 hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground dark:bg-input/30',
                secondary:
                    'bg-secondary text-secondary-foreground hover:bg-[color-mix(in_oklch,var(--secondary),var(--foreground)_5%)] aria-expanded:bg-secondary aria-expanded:text-secondary-foreground',
                ghost: 'hover:bg-muted hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground dark:hover:bg-muted/50',
                destructive:
                    'bg-destructive/10 text-destructive hover:bg-destructive/20 focus-visible:border-destructive/40 focus-visible:ring-destructive/20 dark:bg-destructive/20 dark:hover:bg-destructive/30 dark:focus-visible:ring-destructive/40',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default:
                    'h-10 gap-1.5 px-4 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3',
                xs: 'h-10 gap-1.5 px-4 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3',
                sm: 'h-10 gap-1.5 px-4 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3',
                lg: 'h-10 gap-1.5 px-4 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3',
                icon: 'size-10',
                'icon-xs': 'size-10',
                'icon-sm': 'size-10 rounded-lg',
                'icon-lg': 'size-10',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant = 'default',
    size = 'default',
    asChild = false,
    loading = false,
    disabled = false,
    loadingText = 'Loading',
    loadingLabel,
    children,
    ...props
}: React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
        loading?: boolean;
        loadingText?: React.ReactNode;
        loadingLabel?: string;
    }) {
    const Comp = asChild ? Slot.Root : 'button';
    const accessibleLoadingLabel =
        loadingLabel ??
        (typeof loadingText === 'string' && loadingText.length > 0
            ? loadingText
            : (props['aria-label'] ?? 'Loading'));
    const content = asChild ? (
        children
    ) : (
        <>
            <span className="loading-button-content" aria-hidden={loading || undefined}>
                {children}
            </span>
            {loading ? (
                <span className="loading-button-status" aria-hidden="true">
                    <LoaderCircle className="loading-button-spinner" />
                    {loadingText}
                </span>
            ) : null}
        </>
    );

    return (
        <Comp
            {...props}
            data-slot="button"
            data-variant={variant}
            data-size={size}
            className={cn(
                buttonVariants({
                    variant,
                    size,
                    className: cn(!asChild && 'loading-button', className),
                }),
            )}
            aria-label={loading ? accessibleLoadingLabel : props['aria-label']}
            aria-busy={loading || undefined}
            data-loading={loading || undefined}
            disabled={disabled || loading}
        >
            {content}
        </Comp>
    );
}

export { Button, buttonVariants };
