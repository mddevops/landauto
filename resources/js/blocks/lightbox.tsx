import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ChevronLeft, ChevronRight, XIcon } from 'lucide-react';
import type { KeyboardEvent, RefObject } from 'react';

export type LightboxImage = {
    url: string;
    alt: string;
    width?: number;
    height?: number;
};

const controlClass =
    'inline-flex size-11 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none';

/**
 * Media viewer, separate from the business Popup (D-033). Shows already authorized image URLs:
 * previous/next buttons and arrow keys, Escape closes, focus stays inside while open and
 * returns to the opening element on close.
 */
export function Lightbox({
    label,
    images,
    index,
    onIndexChange,
    open,
    onOpenChange,
    returnFocusTo,
}: {
    label: string;
    images: LightboxImage[];
    index: number;
    onIndexChange: (index: number) => void;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    returnFocusTo: RefObject<HTMLElement | null>;
}) {
    const count = images.length;
    const current = images[Math.min(Math.max(index, 0), count - 1)];

    if (!current) {
        return null;
    }

    const go = (step: number) => onIndexChange((index + step + count) % count);

    function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        if (count < 2) {
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            go(1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            go(-1);
        }
    }

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/90" />
                <DialogPrimitive.Content
                    aria-modal="true"
                    aria-describedby={undefined}
                    onKeyDown={onKeyDown}
                    onCloseAutoFocus={(event) => {
                        if (returnFocusTo.current?.isConnected) {
                            event.preventDefault();
                            returnFocusTo.current.focus();
                        }
                    }}
                    className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-3 p-4 outline-none sm:p-8"
                >
                    <DialogPrimitive.Title className="sr-only">
                        {label}
                    </DialogPrimitive.Title>
                    <img
                        key={current.url}
                        src={current.url}
                        alt={current.alt}
                        width={current.width}
                        height={current.height}
                        className="max-h-[calc(100svh-9rem)] w-auto max-w-full object-contain"
                    />
                    <div className="flex items-center gap-4 text-white">
                        {count > 1 && (
                            <button
                                type="button"
                                aria-label="Предыдущее фото"
                                onClick={() => go(-1)}
                                className={controlClass}
                            >
                                <ChevronLeft
                                    aria-hidden="true"
                                    className="size-6"
                                />
                            </button>
                        )}
                        <p
                            aria-live="polite"
                            className="min-w-16 text-center text-sm"
                        >
                            {`${index + 1} из ${count}`}
                        </p>
                        {count > 1 && (
                            <button
                                type="button"
                                aria-label="Следующее фото"
                                onClick={() => go(1)}
                                className={controlClass}
                            >
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-6"
                                />
                            </button>
                        )}
                    </div>
                    <DialogPrimitive.Close
                        aria-label="Закрыть"
                        className={`${controlClass} absolute top-3 right-3`}
                    >
                        <XIcon aria-hidden="true" className="size-6" />
                    </DialogPrimitive.Close>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
