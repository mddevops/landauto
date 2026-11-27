import { usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Copy,
    Eye,
    Link2,
    Lock,
    Monitor,
    Navigation,
    RotateCw,
    Share,
    Smartphone,
    ZoomIn,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    ColorRow,
    CtButton,
    CtSwitch,
    IconGhost,
    LabeledSelect,
    fieldBox,
} from '@/components/widget/ct';
import { cn } from '@/lib/utils';
import { SCREEN_POSITIONS } from '@/popups/launcher';
import type { ScreenPosition } from '@/popups/launcher';

export { ColorRow, LabeledSelect };
export const controlClass = fieldBox;

export type StudioTab = { id: string; label: string };

export function DeviceToggle({
    device,
    onDevice,
    onPreview,
    phone,
}: {
    device: 'desktop' | 'mobile';
    onDevice: (device: 'desktop' | 'mobile') => void;
    onPreview?: () => void;
    phone?: PhoneView;
}) {
    return (
        <div className="flex items-center justify-center gap-3">
            <div className="flex bg-[#DCD3C7] p-0.5">
                {(
                    [
                        ['desktop', Monitor, 'Десктоп'],
                        ['mobile', Smartphone, 'Телефон'],
                    ] as const
                ).map(([id, Icon, label]) => (
                    <button
                        key={id}
                        type="button"
                        aria-pressed={device === id}
                        aria-label={label}
                        className={cn(
                            'inline-flex h-7 w-10 items-center justify-center',
                            device === id
                                ? 'bg-[#CABCAA] text-[#3F3932]'
                                : 'text-[#746A5D] hover:text-[#3F3932]',
                        )}
                        onClick={() => onDevice(id)}
                    >
                        <Icon className="size-4" aria-hidden="true" />
                    </button>
                ))}
            </div>
            {device === 'mobile' && phone && <PhoneToolbar view={phone} />}
            {onPreview && (
                <button
                    type="button"
                    className="inline-flex h-8 items-center gap-1.5 rounded-full bg-[#DCD3C7] px-4 text-sm text-[#746A5D] hover:text-[#3F3932]"
                    onClick={onPreview}
                >
                    <Eye className="size-4" aria-hidden="true" />
                    Предпросмотр
                </button>
            )}
        </div>
    );
}

export function StudioFrame({
    title,
    onTitle,
    onBack,
    tabs,
    tab,
    onTab,
    enabled,
    onEnabled,
    children,
    panel,
    dirty,
    saving,
    onDiscard,
    wide = false,
    toolbar,
}: {
    title: string;
    onTitle?: (title: string) => void;
    onBack: () => void;
    tabs: StudioTab[];
    tab: string;
    onTab?: (tab: string) => void;
    enabled?: boolean;
    onEnabled?: (enabled: boolean) => void;
    children: ReactNode;
    panel?: ReactNode;
    dirty: boolean;
    saving: boolean;
    onDiscard: () => void;
    wide?: boolean;
    toolbar?: ReactNode;
}) {
    const [renaming, setRenaming] = useState(false);
    const [confirming, setConfirming] = useState(false);

    return (
        <div className="relative flex min-h-[calc(100svh-4rem)] flex-1 bg-[#E7DFD5] text-[#1F282C]">
            <div className="flex min-w-0 flex-1 flex-col px-6 pb-6">
                <header className="flex h-[68px] items-center gap-4 pt-3">
                    <button
                        type="button"
                        aria-label="Назад к виджету"
                        className="text-[#746A5D] hover:text-[#3F3932]"
                        onClick={onBack}
                    >
                        <ArrowLeft className="size-5" aria-hidden="true" />
                    </button>
                    <span className="h-7 w-px bg-[#CABCAA]" aria-hidden />
                    {enabled !== undefined && onEnabled && (
                        <CtSwitch
                            checked={enabled}
                            onChange={onEnabled}
                            label="Включить"
                        />
                    )}
                    {renaming && onTitle ? (
                        <input
                            autoFocus
                            aria-label="Название"
                            value={title}
                            maxLength={120}
                            className="h-9 min-w-64 border border-[#3DAFCC] bg-white px-2 text-2xl outline-none"
                            onChange={(event) => onTitle(event.target.value)}
                            onBlur={() => setRenaming(false)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    setRenaming(false);
                                }
                            }}
                        />
                    ) : (
                        <h1 className="text-2xl leading-8 font-normal">
                            {title}
                        </h1>
                    )}
                    {onTitle && !renaming && (
                        <IconGhost
                            label="Переименовать"
                            kind="pencil"
                            onClick={() => setRenaming(true)}
                        />
                    )}
                </header>
                <nav
                    aria-label="Разделы виджета"
                    className="flex border-b border-[#CABCAA]"
                >
                    {tabs.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            aria-current={tab === item.id ? 'page' : undefined}
                            className={cn(
                                '-mb-px border-b-[3px] px-3 py-3 text-[15px] leading-5',
                                tab === item.id
                                    ? 'border-[#3DAFCC] text-[#3F3932]'
                                    : 'border-transparent text-[#746A5D] hover:text-[#3F3932]',
                            )}
                            onClick={() => onTab?.(item.id)}
                        >
                            {item.label}
                        </button>
                    ))}
                    <div className="ml-auto flex items-center pb-1">
                        {toolbar}
                    </div>
                </nav>
                <div className="flex min-h-0 flex-1 flex-col pt-5">
                    {children}
                </div>
            </div>
            {!wide && (
                <aside className="flex w-full shrink-0 flex-col bg-white shadow-[-6px_0_10px_-4px_rgba(63,57,50,0.22)] lg:sticky lg:top-0 lg:h-svh lg:w-[430px]">
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 pt-4">
                        {panel}
                    </div>
                    <PanelFooter
                        dirty={dirty}
                        saving={saving}
                        onCancel={() => setConfirming(true)}
                    />
                </aside>
            )}
            {confirming && (
                <DiscardConfirm
                    onYes={() => {
                        setConfirming(false);
                        onDiscard();
                    }}
                    onNo={() => setConfirming(false)}
                />
            )}
        </div>
    );
}

export function DiscardConfirm({
    onYes,
    onNo,
}: {
    onYes: () => void;
    onNo: () => void;
}) {
    return (
        <div
            role="alertdialog"
            aria-label="Отмена изменений"
            className="absolute top-4 left-4 z-40 w-[380px] bg-[#1F282C] px-4 py-3 text-[13px] leading-[18px] text-white shadow-lg"
        >
            <p>
                Все несохраненные изменения будут потеряны. Вы уверены, что
                хотите продолжить?
            </p>
            <div className="mt-2 flex gap-2">
                <CtButton
                    tone="accent"
                    className="h-6 px-3 text-[13px]"
                    onClick={onYes}
                >
                    Да
                </CtButton>
                <button
                    type="button"
                    className="px-2 text-[13px] text-white/80 hover:text-white"
                    onClick={onNo}
                >
                    Нет
                </button>
            </div>
        </div>
    );
}

export function PanelFooter({
    onCancel,
    saving,
    dirty,
}: {
    onCancel: () => void;
    saving: boolean;
    dirty: boolean;
}) {
    return (
        <div className="flex shrink-0 items-center justify-between border-t border-[#EEEEEE] px-4 py-3">
            <button
                type="button"
                disabled={!dirty}
                className={cn(
                    'h-8 px-4 text-sm',
                    dirty
                        ? 'bg-white text-[#8F9496] hover:text-[#3F3932]'
                        : 'bg-[#F0F0F0] text-[#B1B3B5]',
                )}
                onClick={onCancel}
            >
                Отменить
            </button>
            <button
                type="submit"
                disabled={!dirty || saving}
                className="h-8 bg-[#3DAFCC] px-4 text-sm text-white hover:bg-[#2D9CB8] disabled:bg-[#9ED7E5] disabled:hover:bg-[#9ED7E5]"
            >
                Сохранить
            </button>
        </div>
    );
}

function SiteMockBody() {
    return (
        <>
            <div className="flex h-[34px] items-center gap-2 bg-[#FAFAFA] px-3">
                <span className="size-3 rounded-full bg-[#DDDFDF]" />
                <span className="size-3 rounded-full bg-[#DDDFDF]" />
                <span className="size-3 rounded-full bg-[#DDDFDF]" />
                <span className="ml-8 h-5 flex-1 rounded-sm bg-[#F2F2F2]" />
            </div>
            <div className="p-4">
                <div className="flex items-center gap-4">
                    <span className="size-12 rounded-full bg-[#F2F2F2]" />
                    <span className="ml-auto h-5 w-14 bg-[#DDDFDF]" />
                    <span className="h-5 w-14 bg-[#DDDFDF]" />
                    <span className="h-5 w-14 bg-[#DDDFDF]" />
                </div>
                <div className="mt-5 grid grid-cols-[1.1fr_1fr] gap-4">
                    <div className="h-36 bg-[#F2F2F2]" />
                    <div className="grid content-start gap-2">
                        <div className="h-9 bg-[#DDDFDF]" />
                        <div className="h-2 bg-[#F2F2F2]" />
                        <div className="h-2 bg-[#F2F2F2]" />
                        <div className="h-2 bg-[#F2F2F2]" />
                        <div className="h-2 w-1/2 bg-[#F2F2F2]" />
                    </div>
                </div>
                <div className="mt-4 grid grid-cols-3 gap-4">
                    <div className="h-10 bg-[#F2F2F2]" />
                    <div className="h-10 bg-[#F2F2F2]" />
                    <div className="h-10 bg-[#F2F2F2]" />
                </div>
            </div>
        </>
    );
}

/** Окно сайта 802×402 с серыми блоками, как на экране «Кнопка». */
export function SiteMock({
    children,
    className,
}: {
    children?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative mx-auto aspect-[802/402] w-full max-w-[802px] overflow-hidden rounded-md border-[3px] border-[#D2CCC3] bg-white',
                className,
            )}
        >
            <SiteMockBody />
            {children}
        </div>
    );
}
export const PHONE_MODELS = [
    {
        id: 'iphone-13-mini',
        width: 375,
        height: 812,
        label: '375 x 812 — iPhone 13 mini',
        short: 'iPhone 13 mini',
    },
    {
        id: 'iphone-14',
        width: 390,
        height: 844,
        label: '390 x 844 — iPhone 14',
        short: 'iPhone 14',
    },
    {
        id: 'iphone-15',
        width: 393,
        height: 852,
        label: '393 x 852 — iPhone 15 / iPhone 15 Pro / iPhone 14 Pro',
        short: 'iPhone 15',
    },
    {
        id: 'iphone-15-pro-max',
        width: 430,
        height: 932,
        label: '430 x 932 — iPhone 15 Pro Max / iPhone 14 Pro Max',
        short: 'iPhone 15 Pro Max',
    },
] as const;

export type PhoneView = {
    model: string;
    zoom: 0.75 | 1;
    onModel: (model: string) => void;
    onZoom: (zoom: 0.75 | 1) => void;
};

export function usePhoneView(): PhoneView {
    const [model, onModel] = useState<string>(PHONE_MODELS[0].id);
    const [zoom, onZoom] = useState<0.75 | 1>(0.75);

    return { model, zoom, onModel, onZoom };
}

function ToolbarMenu({
    label,
    icon,
    value,
    items,
    onChange,
}: {
    label: string;
    icon?: ReactNode;
    value: string;
    items: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const current = items.find((item) => item.value === value);

    useEffect(() => {
        if (!open) {
            return;
        }

        function close(event: MouseEvent) {
            if (!root.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    return (
        <div ref={root} className="relative">
            <button
                type="button"
                aria-label={label}
                aria-haspopup="listbox"
                aria-expanded={open}
                className="inline-flex h-8 items-center gap-2 rounded-md bg-[#DCD3C7] px-3 text-sm text-[#746A5D] hover:text-[#3F3932]"
                onClick={() => setOpen((state) => !state)}
            >
                {icon}
                {current?.label}
                <ChevronDown
                    className={cn('size-4', open && 'rotate-180')}
                    aria-hidden="true"
                />
            </button>
            {open && (
                <ul
                    role="listbox"
                    aria-label={label}
                    className="absolute top-full left-0 z-30 mt-1 min-w-full rounded bg-[#746A5D] py-1 text-sm whitespace-nowrap text-white shadow-lg"
                >
                    {items.map((item) => (
                        <li
                            key={item.value}
                            role="option"
                            aria-selected={item.value === value}
                            className="cursor-pointer px-3 py-1.5 hover:bg-white/10"
                            onClick={() => {
                                onChange(item.value);
                                setOpen(false);
                            }}
                        >
                            {item.label}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function PhoneToolbar({ view }: { view: PhoneView }) {
    const model = PHONE_MODELS.find((item) => item.id === view.model);

    return (
        <>
            <ToolbarMenu
                label="Модель телефона"
                value={view.model}
                items={PHONE_MODELS.map((item) => ({
                    value: item.id,
                    label: item.label,
                }))}
                onChange={view.onModel}
            />
            <span className="sr-only">{model?.short}</span>
            <ToolbarMenu
                label="Масштаб телефона"
                icon={<ZoomIn className="size-4" aria-hidden="true" />}
                value={String(view.zoom)}
                items={[
                    { value: '0.75', label: '0,75x' },
                    { value: '1', label: '1x' },
                ]}
                onChange={(value) => view.onZoom(value === '1' ? 1 : 0.75)}
            />
        </>
    );
}

/**
 * Телефон с открытым Safari: адрес берётся из APP_URL (общий Inertia-проп `appHost`).
 * Виджет внутри рисуется в реальных пикселях, уменьшается только сам макет страницы.
 */
export function PhoneMock({
    children,
    view,
}: {
    children?: ReactNode;
    view?: PhoneView;
}) {
    const { appHost } = usePage().props;
    const model =
        PHONE_MODELS.find((item) => item.id === view?.model) ?? PHONE_MODELS[0];
    const zoom = view?.zoom ?? 0.75;
    const k = zoom / 0.75;
    const factor = zoom * 0.96;
    const width = Math.round(model.width * factor);
    const height = Math.round(model.height * factor);
    const status = Math.round(34 * k);
    const bar = Math.round(82 * k);
    const bezel = 10;

    return (
        <div
            className="relative mx-auto"
            style={{ width: width + bezel * 2, height: height + bezel * 2 }}
        >
            {[
                [58, 14],
                [86, 30],
                [126, 30],
            ].map(([top, size]) => (
                <span
                    key={top}
                    aria-hidden="true"
                    className="absolute -left-[2px] w-[3px] rounded-l bg-[#4A4A4D]"
                    style={{ top: top * k, height: size * k }}
                />
            ))}
            <span
                aria-hidden="true"
                className="absolute -right-[2px] w-[3px] rounded-r bg-[#4A4A4D]"
                style={{ top: 110 * k, height: 48 * k }}
            />
            <div
                className="absolute inset-0 bg-black ring-1 ring-[#8A8A8E]"
                style={{ borderRadius: 44 * k }}
            />
            <div
                className="absolute overflow-hidden bg-white"
                style={{
                    inset: bezel,
                    borderRadius: 34 * k,
                }}
            >
                <div
                    className="absolute inset-x-0 top-0 flex items-center justify-between bg-white px-5 text-[#000]"
                    style={{ height: status, fontSize: 12 * k }}
                >
                    <span className="flex items-center gap-1 font-semibold">
                        9:41
                        <Navigation
                            className="size-2.5"
                            fill="currentColor"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        aria-hidden="true"
                        className="absolute top-0 left-1/2 -translate-x-1/2 rounded-b-[12px] bg-black"
                        style={{ width: '41%', height: 22 * k }}
                    />
                    <span className="flex items-center gap-1">
                        <svg viewBox="0 0 17 11" className="h-2.5 w-3.5">
                            <rect x="0" y="7" width="3" height="4" rx="0.6" />
                            <rect
                                x="4.6"
                                y="5"
                                width="3"
                                height="6"
                                rx="0.6"
                                opacity="0.35"
                            />
                            <rect
                                x="9.2"
                                y="2.5"
                                width="3"
                                height="8.5"
                                rx="0.6"
                                opacity="0.35"
                            />
                            <rect
                                x="13.8"
                                y="0"
                                width="3"
                                height="11"
                                rx="0.6"
                                opacity="0.35"
                            />
                        </svg>
                        <Link2
                            className="size-3"
                            aria-hidden="true"
                            strokeWidth={2.5}
                        />
                        <span className="rounded-[3px] bg-[#F5C242] px-1 text-[8px] leading-[11px] font-semibold">
                            32
                        </span>
                    </span>
                </div>
                <div
                    className="absolute inset-x-0 overflow-hidden"
                    style={{ top: status, bottom: bar }}
                >
                    <div className="absolute inset-x-1.5 top-0 space-y-1.5 pt-0.5">
                        <div
                            className="flex items-center gap-2 bg-[#F0F0F0] px-2"
                            style={{ height: 48 * k }}
                        >
                            <span
                                className="shrink-0 rounded-full bg-[#D4D4D4]"
                                style={{ width: 34 * k, height: 34 * k }}
                            />
                            <span className="ml-auto h-3 w-9 bg-[#D4D4D4]" />
                            <span className="h-3 w-9 bg-[#D4D4D4]" />
                            <span className="h-3 w-9 bg-[#D4D4D4]" />
                        </div>
                        <div className="grid grid-cols-3 gap-1.5 pt-1.5">
                            {[0, 1, 2].map((item) => (
                                <div
                                    key={item}
                                    className="bg-[#F0F0F0]"
                                    style={{ height: 100 * k }}
                                />
                            ))}
                        </div>
                        <div
                            className="mt-1.5 bg-[#F0F0F0]"
                            style={{ height: 104 * k }}
                        />
                        <div style={{ height: 70 * k }} />
                        <div
                            className="bg-[#D4D4D4]"
                            style={{ height: 28 * k }}
                        />
                        <div className="space-y-2 pt-1">
                            {[0, 1, 2, 3].map((item) => (
                                <div
                                    key={item}
                                    className="h-[7px] bg-[#F0F0F0]"
                                />
                            ))}
                        </div>
                    </div>
                    {children}
                </div>
                <div
                    className="absolute inset-x-0 bottom-0 bg-[#F8F8F9]"
                    style={{ height: bar }}
                >
                    <div
                        className="mx-3 mt-1.5 flex items-center justify-between rounded-[10px] bg-white px-2.5 shadow-[0_0_0_0.5px_rgba(0,0,0,0.08)]"
                        style={{ height: 28 * k }}
                    >
                        <span className="text-[10px] font-semibold text-[#1F1F1F]">
                            A<span className="text-[8px]">A</span>
                        </span>
                        <span className="flex min-w-0 items-center gap-1 text-[12px] text-[#1F1F1F]">
                            <Lock
                                className="size-2.5 text-[#8A8A8E]"
                                fill="currentColor"
                                aria-hidden="true"
                            />
                            <span className="truncate">{appHost}</span>
                        </span>
                        <RotateCw
                            className="size-3 text-[#1F1F1F]"
                            aria-hidden="true"
                        />
                    </div>
                    <div className="mt-1 flex items-center justify-around px-3 text-[#007AFF]">
                        <ChevronLeft className="size-5" aria-hidden="true" />
                        <ChevronRight className="size-5" aria-hidden="true" />
                        <Share className="size-4" aria-hidden="true" />
                        <BookOpen className="size-4" aria-hidden="true" />
                        <Copy className="size-4" aria-hidden="true" />
                    </div>
                    <span
                        aria-hidden="true"
                        className="absolute bottom-1.5 left-1/2 h-1 w-[36%] -translate-x-1/2 rounded-full bg-black"
                    />
                </div>
            </div>
        </div>
    );
}
export function PositionGrid({
    value,
    onChange,
    positions = SCREEN_POSITIONS,
}: {
    value: string;
    onChange: (position: ScreenPosition) => void;
    positions?: readonly ScreenPosition[];
}) {
    return (
        <div className="grid w-fit grid-cols-3 gap-1">
            {positions.map((position) => (
                <button
                    key={position}
                    type="button"
                    aria-label={position}
                    aria-pressed={value === position}
                    className={cn(
                        'size-7 border border-[#CABCAA] bg-[#F0F0F0]',
                        value === position && 'border-[#3DAFCC] bg-[#3DAFCC]',
                    )}
                    onClick={() => onChange(position)}
                />
            ))}
        </div>
    );
}
