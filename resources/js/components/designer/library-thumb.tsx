import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { BlockWireframe } from '@/components/designer/block-wireframe';
import type { DesignerLibraryBlock } from '@/components/designer/types';
import { cn } from '@/lib/utils';

type LibraryThumbProps = {
    item: DesignerLibraryBlock;
    onAdd: () => void;
};

/** Official Landflow Blocks without access conditions need no access line. */
function accessLine(item: DesignerLibraryBlock): string | null {
    if (item.author === null && !item.access.restricted) {
        return null;
    }

    return [
        item.author ? `Автор: ${item.author}` : null,
        [item.access.label, item.access.detail].filter(Boolean).join(' · '),
    ]
        .filter(Boolean)
        .join('. ');
}

export function LibraryThumb({ item, onAdd }: LibraryThumbProps) {
    const access = accessLine(item);
    const accessId = `library-${item.slug}-access`;
    const reasonId = `library-${item.slug}-reason`;
    const describedBy =
        [access ? accessId : null, item.reason ? reasonId : null]
            .filter(Boolean)
            .join(' ') || undefined;
    const buttonRef = useRef<HTMLButtonElement>(null);
    const [hover, setHover] = useState(false);
    const [coords, setCoords] = useState<{ top: number; left: number } | null>(
        null,
    );

    useEffect(() => {
        if (!hover || !buttonRef.current) {
            setCoords(null);

            return;
        }

        const update = () => {
            const rect = buttonRef.current?.getBoundingClientRect();

            if (!rect) {
                return;
            }

            const previewWidth = 288;
            const gap = 12;
            const left =
                rect.right + gap + previewWidth > window.innerWidth
                    ? Math.max(12, rect.left - previewWidth - gap)
                    : rect.right + gap;
            const top = Math.min(
                Math.max(12, rect.top),
                window.innerHeight - 220,
            );

            setCoords({ top, left });
        };

        update();
        window.addEventListener('scroll', update, true);
        window.addEventListener('resize', update);

        return () => {
            window.removeEventListener('scroll', update, true);
            window.removeEventListener('resize', update);
        };
    }, [hover]);

    return (
        <li data-testid="catalog-block" className="min-w-0">
            <button
                ref={buttonRef}
                type="button"
                disabled={!item.available}
                aria-label={`Добавить блок «${item.name}»`}
                aria-describedby={describedBy}
                onClick={onAdd}
                onMouseEnter={() => setHover(true)}
                onMouseLeave={() => setHover(false)}
                onFocus={() => setHover(true)}
                onBlur={() => setHover(false)}
                className={cn(
                    'flex w-full flex-col gap-1.5 rounded-lg border border-transparent bg-transparent p-1 text-left transition-colors',
                    'hover:border-[#3898EC]/40 hover:bg-[#3898EC]/5',
                    'focus-visible:border-[#3898EC] focus-visible:ring-1 focus-visible:ring-[#3898EC] focus-visible:outline-none',
                    !item.available && 'cursor-not-allowed opacity-50',
                )}
            >
                <div className="aspect-[5/3] overflow-hidden rounded-md border border-neutral-200 bg-white">
                    <BlockWireframe slug={item.slug} category={item.category} />
                </div>
                <span className="truncate px-0.5 text-xs font-medium text-neutral-700">
                    {item.name}
                </span>
                {access && (
                    <span
                        id={accessId}
                        className="px-0.5 text-xs leading-tight break-words text-neutral-500"
                    >
                        {access}
                    </span>
                )}
                {item.reason && (
                    <span
                        id={reasonId}
                        className="px-0.5 text-[10px] leading-tight text-amber-700"
                    >
                        {item.reason}
                    </span>
                )}
            </button>
            {hover &&
                coords &&
                createPortal(
                    <div
                        role="presentation"
                        className="pointer-events-none fixed z-[80] w-72 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xl"
                        style={{ top: coords.top, left: coords.left }}
                    >
                        <div className="border-b border-neutral-100 px-3 py-2">
                            <p className="truncate text-sm font-semibold text-neutral-900">
                                {item.name}
                            </p>
                            <p className="truncate text-xs text-neutral-500">
                                {item.category_label}
                                {item.author ? ` · ${item.author}` : ''}
                            </p>
                        </div>
                        <div className="aspect-[16/10] p-3">
                            <BlockWireframe
                                slug={item.slug}
                                category={item.category}
                                className="rounded-md"
                            />
                        </div>
                        {!item.available && item.reason && (
                            <p className="border-t border-neutral-100 px-3 py-2 text-xs text-amber-700">
                                {item.reason}
                            </p>
                        )}
                    </div>,
                    document.body,
                )}
        </li>
    );
}
