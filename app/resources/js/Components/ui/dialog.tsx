'use client';

import * as React from 'react';
import { createPortal } from 'react-dom';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';

type DialogRootProps = React.PropsWithChildren<{
    open?: boolean;
    defaultOpen?: boolean;
    onOpenChange?: (open: boolean) => void;
    modal?: boolean;
}>;

interface DialogContextValue {
    open: boolean;
    setOpen: (open: boolean) => void;
    titleId: string;
    descriptionId: string;
    triggerRef: React.RefObject<HTMLElement | null>;
}

const DialogContext = React.createContext<DialogContextValue | null>(null);

function useDialogContext() {
    const context = React.useContext(DialogContext);

    if (!context) {
        throw new Error('Dialog components must be used inside a Dialog.');
    }

    return context;
}

function Dialog({ open: openProp, defaultOpen = false, onOpenChange, children }: DialogRootProps) {
    const [uncontrolledOpen, setUncontrolledOpen] = React.useState(defaultOpen);
    const open = openProp ?? uncontrolledOpen;
    const id = React.useId();
    const triggerRef = React.useRef<HTMLElement | null>(null);

    const setOpen = React.useCallback(
        (nextOpen: boolean) => {
            if (openProp === undefined) {
                setUncontrolledOpen(nextOpen);
            }

            onOpenChange?.(nextOpen);
        },
        [onOpenChange, openProp],
    );

    const contextValue = React.useMemo<DialogContextValue>(
        () => ({
            open,
            setOpen,
            titleId: `dialog-title-${id}`,
            descriptionId: `dialog-description-${id}`,
            triggerRef,
        }),
        [id, open, setOpen],
    );

    return <DialogContext.Provider value={contextValue}>{children}</DialogContext.Provider>;
}

type DialogPortalProps = React.PropsWithChildren<{
    forceMount?: boolean;
    container?: Element | DocumentFragment | null;
}>;

function DialogPortal({ children, forceMount = false, container }: DialogPortalProps) {
    const { open } = useDialogContext();

    if (typeof document === 'undefined' || (!open && !forceMount)) {
        return null;
    }

    return createPortal(children, container ?? document.body);
}

type DialogOverlayProps = React.HTMLAttributes<HTMLDivElement>;

function DialogOverlay({ className, onClick, ...props }: DialogOverlayProps) {
    const { open, setOpen } = useDialogContext();

    return (
        <div
            data-slot="dialog-overlay"
            data-state={open ? 'open' : 'closed'}
            aria-hidden="true"
            className={cn(
                'data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0 fixed inset-0 isolate z-50 bg-black/80 duration-100 supports-backdrop-filter:backdrop-blur-xs',
                className,
            )}
            onClick={(event) => {
                onClick?.(event);

                if (!event.defaultPrevented && event.target === event.currentTarget) {
                    setOpen(false);
                }
            }}
            {...props}
        />
    );
}

type DialogSurfaceProps = React.HTMLAttributes<HTMLDivElement> & {
    showCloseButton?: boolean;
    overlayClassName?: string;
    closeButtonClassName?: string;
};

function DialogSurface({
    className,
    children,
    showCloseButton = true,
    overlayClassName,
    closeButtonClassName,
    onKeyDown,
    ...props
}: DialogSurfaceProps) {
    const { open, setOpen, titleId, descriptionId, triggerRef } = useDialogContext();
    const contentRef = React.useRef<HTMLDivElement | null>(null);
    const previousFocusRef = React.useRef<HTMLElement | null>(null);

    React.useEffect(() => {
        if (!open) {
            return;
        }

        const focusTrigger = triggerRef.current;
        previousFocusRef.current =
            document.activeElement instanceof HTMLElement ? document.activeElement : null;
        document.body.classList.add('pgs-dialog-open');

        const focusFrame = window.requestAnimationFrame(() => {
            const activeElement = document.activeElement;
            const autoFocusElement = contentRef.current?.querySelector<HTMLElement>(
                '[autofocus], [data-autofocus]',
            );

            if (autoFocusElement) {
                autoFocusElement.focus();
            } else if (activeElement === previousFocusRef.current) {
                contentRef.current?.focus({ preventScroll: true });
            }
        });

        return () => {
            window.cancelAnimationFrame(focusFrame);
            document.body.classList.remove('pgs-dialog-open');

            const focusTarget = focusTrigger ?? previousFocusRef.current;
            focusTarget?.focus({ preventScroll: true });
            previousFocusRef.current = null;
        };
    }, [open, triggerRef]);

    const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
        onKeyDown?.(event);

        if (event.defaultPrevented) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            return;
        }

        if (event.key !== 'Tab' || !contentRef.current) {
            return;
        }

        const focusableElements = Array.from(
            contentRef.current.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            ),
        ).filter((element) => element.getAttribute('aria-hidden') !== 'true');

        if (focusableElements.length === 0) {
            event.preventDefault();
            contentRef.current.focus();
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
        } else if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    };

    return (
        <DialogPortal>
            <DialogOverlay className={overlayClassName} />
            <div
                ref={contentRef}
                data-state={open ? 'open' : 'closed'}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={descriptionId}
                tabIndex={-1}
                className={className}
                onKeyDown={handleKeyDown}
                {...props}
            >
                {children}
                {showCloseButton && (
                    <Button
                        type="button"
                        variant="ghost"
                        className={cn('absolute top-2 right-2', closeButtonClassName)}
                        size="icon-sm"
                        onClick={() => {
                            setOpen(false);
                        }}
                    >
                        <X size={16} strokeWidth={2} />
                        <span className="sr-only">Close</span>
                    </Button>
                )}
            </div>
        </DialogPortal>
    );
}

function DialogTrigger({
    asChild = false,
    children,
    onClick,
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement> & { asChild?: boolean }) {
    const { open, setOpen, triggerRef } = useDialogContext();

    const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        onClick?.(event);

        if (!event.defaultPrevented) {
            setOpen(!open);
        }
    };

    if (asChild && React.isValidElement<{ onClick?: React.MouseEventHandler }>(children)) {
        return React.cloneElement(children, {
            ...props,
            onClick: handleClick,
        });
    }

    return (
        <button
            type="button"
            data-slot="dialog-trigger"
            {...props}
            ref={triggerRef as React.RefObject<HTMLButtonElement>}
            onClick={handleClick}
        >
            {children}
        </button>
    );
}

function DialogClose({
    asChild = false,
    children,
    onClick,
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement> & { asChild?: boolean }) {
    const { setOpen } = useDialogContext();

    const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        onClick?.(event);

        if (!event.defaultPrevented) {
            setOpen(false);
        }
    };

    if (asChild && React.isValidElement<{ onClick?: React.MouseEventHandler }>(children)) {
        return React.cloneElement(children, {
            ...props,
            onClick: handleClick,
        });
    }

    return (
        <button type="button" data-slot="dialog-close" {...props} onClick={handleClick}>
            {children}
        </button>
    );
}

function DialogContent({
    className,
    children,
    showCloseButton = true,
    ...props
}: React.HTMLAttributes<HTMLDivElement> & { showCloseButton?: boolean }) {
    return (
        <DialogSurface
            data-slot="dialog-content"
            className={cn(
                'pgs-modal-content bg-popover text-popover-foreground ring-foreground/10 data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95 fixed top-1/2 left-1/2 z-50 grid w-full max-w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 gap-4 rounded-xl p-4 text-xs/relaxed ring-1 duration-100 outline-none sm:max-w-sm',
                className,
            )}
            showCloseButton={showCloseButton}
            {...props}
        >
            {children}
        </DialogSurface>
    );
}

function DialogHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="dialog-header"
            className={cn('flex flex-col gap-1', className)}
            {...props}
        />
    );
}

function DialogBody({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="dialog-body" className={cn('pgs-modal-body', className)} {...props} />;
}

function DialogFooter({
    className,
    showCloseButton = false,
    children,
    ...props
}: React.ComponentProps<'div'> & { showCloseButton?: boolean }) {
    return (
        <div
            data-slot="dialog-footer"
            className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', className)}
            {...props}
        >
            {children}
            {showCloseButton && (
                <DialogClose asChild>
                    <Button variant="outline">Close</Button>
                </DialogClose>
            )}
        </div>
    );
}

function DialogTitle({ className, ...props }: React.ComponentProps<'h2'>) {
    const { titleId } = useDialogContext();

    return (
        <h2
            data-slot="dialog-title"
            id={props.id ?? titleId}
            className={cn('font-heading text-sm font-medium', className)}
            {...props}
        />
    );
}

function DialogDescription({ className, ...props }: React.ComponentProps<'p'>) {
    const { descriptionId } = useDialogContext();

    return (
        <p
            data-slot="dialog-description"
            id={props.id ?? descriptionId}
            className={cn(
                'text-muted-foreground *:[a]:hover:text-foreground text-xs/relaxed *:[a]:underline *:[a]:underline-offset-3',
                className,
            )}
            {...props}
        />
    );
}

export {
    Dialog,
    DialogClose,
    DialogContent,
    DialogBody,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogOverlay,
    DialogPortal,
    DialogSurface,
    DialogTitle,
    DialogTrigger,
};
