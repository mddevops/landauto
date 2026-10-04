import * as DialogPrimitive from '@radix-ui/react-dialog';
import { XIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { DesignTokens } from '@/blocks/design';
import { SiteTheme } from '@/blocks/theme';
import { cn } from '@/lib/utils';

/** Presentation-only Popup from the backend runtime payload (D-035). */
export type PopupRuntime = {
    public_id: string;
    name: string;
    title: string | null;
    text: string | null;
    size: 'small' | 'medium' | 'large';
    animation: 'none' | 'fade' | 'slide_up';
    close_on_overlay: boolean;
    close_on_escape: boolean;
    show_close_button: boolean;
    mobile_fullscreen: boolean;
};

const sizes: Record<PopupRuntime['size'], string> = {
    small: 'sm:max-w-sm',
    medium: 'sm:max-w-lg',
    large: 'sm:max-w-3xl',
};

const animations: Record<PopupRuntime['animation'], string> = {
    none: '',
    fade: 'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
    slide_up:
        'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-bottom-8',
};

/**
 * Accessible modal runtime: dialog semantics, focus moved inside and trapped, focus returned
 * to the trigger on close; Escape and overlay closing follow the Popup settings.
 */
export function PopupView({
    popup,
    tokens,
    open,
    onOpenChange,
    children,
}: {
    popup: PopupRuntime;
    tokens: DesignTokens;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    children?: ReactNode;
}) {
    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <SiteTheme tokens={tokens}>
                    <DialogPrimitive.Overlay
                        data-testid="popup-overlay"
                        className={cn(
                            'fixed inset-0 z-50 bg-black/60',
                            popup.animation !== 'none' &&
                                'data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none',
                        )}
                    />
                    <DialogPrimitive.Content
                        aria-modal="true"
                        {...(popup.text
                            ? {}
                            : { 'aria-describedby': undefined })}
                        onEscapeKeyDown={(event) => {
                            if (!popup.close_on_escape) {
                                event.preventDefault();
                            }
                        }}
                        onInteractOutside={(event) => {
                            if (!popup.close_on_overlay) {
                                event.preventDefault();
                            }
                        }}
                        className={cn(
                            'fixed top-1/2 left-1/2 z-50 flex max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col gap-4 overflow-y-auto rounded-(--lf-radius) bg-white p-6 text-neutral-900 shadow-xl outline-none motion-reduce:animate-none',
                            sizes[popup.size],
                            animations[popup.animation],
                            popup.mobile_fullscreen &&
                                'max-sm:top-0 max-sm:left-0 max-sm:h-svh max-sm:max-h-none max-sm:w-full max-sm:translate-x-0 max-sm:translate-y-0 max-sm:rounded-none',
                        )}
                    >
                        <DialogPrimitive.Title
                            className={cn(
                                'pr-8 text-xl font-semibold',
                                popup.title === null && 'sr-only',
                            )}
                        >
                            {popup.title ?? popup.name}
                        </DialogPrimitive.Title>
                        {popup.text && (
                            <DialogPrimitive.Description className="text-sm whitespace-pre-line text-neutral-600">
                                {popup.text}
                            </DialogPrimitive.Description>
                        )}
                        {children}
                        {popup.show_close_button && (
                            <DialogPrimitive.Close
                                aria-label="Закрыть"
                                className="absolute top-4 right-4 rounded-sm p-1 text-neutral-500 hover:text-neutral-900 focus-visible:ring-2 focus-visible:ring-(--lf-primary) focus-visible:outline-none"
                            >
                                <XIcon className="size-5" aria-hidden />
                            </DialogPrimitive.Close>
                        )}
                    </DialogPrimitive.Content>
                </SiteTheme>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
