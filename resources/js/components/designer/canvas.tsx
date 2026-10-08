import type { ReactNode } from 'react';
import { blockRenderer } from '@/blocks/registry';
import { SandboxedBlock } from '@/blocks/sandboxed-block';
import type { BlockState } from '@/blocks/state';
import type { DesignerBlock } from '@/components/designer/types';
import type { AutosaveStatus } from '@/components/designer/use-block-autosave';
import { cn } from '@/lib/utils';

const autosaveLabels: Record<AutosaveStatus, string | null> = {
    idle: null,
    pending: 'Есть несохранённые изменения',
    saving: 'Сохранение…',
    saved: 'Сохранено',
    error: 'Не удалось сохранить',
};

export function AutosaveIndicator({ status }: { status: AutosaveStatus }) {
    return (
        <p
            role="status"
            aria-live="polite"
            className={cn(
                'hidden text-xs sm:block',
                status === 'error'
                    ? 'font-medium text-destructive'
                    : 'text-muted-foreground',
            )}
        >
            {autosaveLabels[status]}
        </p>
    );
}

export function DesignerTabs<T extends string>({
    value,
    onChange,
    tabs,
}: {
    value: T;
    onChange: (value: T) => void;
    tabs: { value: T; label: ReactNode }[];
}) {
    return (
        <div role="tablist" className="flex border-b">
            {tabs.map((tab) => (
                <button
                    key={tab.value}
                    type="button"
                    role="tab"
                    id={`designer-tab-${tab.value}`}
                    aria-selected={tab.value === value}
                    aria-controls={`designer-panel-${tab.value}`}
                    onClick={() => onChange(tab.value)}
                    className={cn(
                        'flex-1 border-b-2 border-transparent px-3 py-2.5 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                        tab.value === value &&
                            'border-primary font-medium text-foreground',
                    )}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}

/** Renders one Block with the official renderer or the ADR-008 sandbox. */
export function RenderedBlock({
    block,
    state,
}: {
    block: DesignerBlock;
    state: BlockState;
}) {
    const Renderer = blockRenderer(block.slug);

    if (block.sandbox) {
        return <SandboxedBlock source={block.sandbox} state={state} />;
    }

    return Renderer ? (
        <Renderer state={state} />
    ) : (
        <div className="p-6 text-sm text-neutral-500">
            {`Блок «${block.name}» не удаётся отобразить.`}
        </div>
    );
}

export function CanvasBlock({
    block,
    state,
    selected,
    onSelect,
}: {
    block: DesignerBlock;
    state: BlockState;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <div className="relative">
            {block.is_hidden && (
                <span className="absolute top-2 right-2 z-10 rounded bg-neutral-900/80 px-2 py-0.5 text-xs text-white">
                    Скрыт
                </span>
            )}
            <div inert className={cn(block.is_hidden && 'opacity-40')}>
                <RenderedBlock block={block} state={state} />
            </div>
            <button
                type="button"
                aria-label={`Выбрать блок «${block.name}»`}
                aria-pressed={selected}
                onClick={onSelect}
                className={cn(
                    'absolute inset-0 outline-none hover:ring-2 hover:ring-primary/40 hover:ring-inset focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset',
                    selected && 'ring-2 ring-primary ring-inset',
                )}
            />
        </div>
    );
}
