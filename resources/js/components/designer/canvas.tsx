import { ArrowDown, ArrowUp, Copy, Eye, EyeOff, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { blockRenderer } from '@/blocks/registry';
import { SandboxedBlock } from '@/blocks/sandboxed-block';
import type { BlockState } from '@/blocks/state';
import type { DesignerBlock } from '@/components/designer/types';
import type { AutosaveStatus } from '@/components/designer/use-block-autosave';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** Webflow-like selection accent. */
export const CANVAS_ACCENT = '#3898EC';

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

export type CanvasBlockActions = {
    canMoveUp: boolean;
    canMoveDown: boolean;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onDuplicate: () => void;
    onToggleHidden: () => void;
    onDelete: () => void;
};

export function CanvasBlock({
    block,
    state,
    selected,
    onSelect,
    canEditStructure,
    actions,
}: {
    block: DesignerBlock;
    state: BlockState;
    selected: boolean;
    onSelect: () => void;
    canEditStructure: boolean;
    actions: CanvasBlockActions | null;
}) {
    return (
        <div className="group/block relative">
            {block.is_hidden && (
                <span className="absolute top-2 right-2 z-20 rounded bg-neutral-900/80 px-2 py-0.5 text-xs text-white">
                    Скрыт
                </span>
            )}
            {selected && canEditStructure && actions && (
                <div className="absolute top-1.5 left-1.5 z-30 flex items-center gap-0.5 rounded-md border border-[#3898EC]/30 bg-white/95 p-0.5 shadow-sm">
                    <ToolbarButton
                        label={`Переместить «${block.name}» выше`}
                        disabled={!actions.canMoveUp}
                        onClick={actions.onMoveUp}
                    >
                        <ArrowUp />
                    </ToolbarButton>
                    <ToolbarButton
                        label={`Переместить «${block.name}» ниже`}
                        disabled={!actions.canMoveDown}
                        onClick={actions.onMoveDown}
                    >
                        <ArrowDown />
                    </ToolbarButton>
                    <ToolbarButton
                        label={`Дублировать «${block.name}»`}
                        onClick={actions.onDuplicate}
                    >
                        <Copy />
                    </ToolbarButton>
                    <ToolbarButton
                        label={
                            block.is_hidden
                                ? `Показать «${block.name}»`
                                : `Скрыть «${block.name}»`
                        }
                        onClick={actions.onToggleHidden}
                    >
                        {block.is_hidden ? <Eye /> : <EyeOff />}
                    </ToolbarButton>
                    <ToolbarButton
                        label={`Удалить «${block.name}»`}
                        onClick={actions.onDelete}
                    >
                        <Trash2 />
                    </ToolbarButton>
                </div>
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
                    'absolute inset-0 z-10 outline-none transition-[box-shadow]',
                    'hover:shadow-[inset_0_0_0_1px_#3898EC]',
                    'focus-visible:shadow-[inset_0_0_0_1px_#3898EC]',
                    selected && 'shadow-[inset_0_0_0_1px_#3898EC]',
                )}
            />
            {selected && (
                <span
                    className="pointer-events-none absolute bottom-0 left-0 z-20 max-w-[min(100%,16rem)] truncate px-1.5 py-0.5 text-[11px] font-medium text-white"
                    style={{ backgroundColor: CANVAS_ACCENT }}
                >
                    {block.name}
                </span>
            )}
        </div>
    );
}

function ToolbarButton({
    label,
    onClick,
    disabled,
    children,
}: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7 text-[#3898EC] hover:bg-[#3898EC]/10 hover:text-[#3898EC] [&_svg]:size-3.5"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={(event) => {
                event.stopPropagation();
                onClick();
            }}
        >
            {children}
        </Button>
    );
}
