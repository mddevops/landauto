import { CheckCircle2, Phone, X } from 'lucide-react';
import { useRef } from 'react';
import type { PointerEvent as ReactPointerEvent, ReactNode } from 'react';
import { Illustration } from '@/components/widget/illustrations';
import { cn } from '@/lib/utils';
import { buttonBackground } from '@/popups/launcher';
import type { LauncherLook } from '@/popups/launcher';
import type {
    CanvasBlock,
    ConfirmBlock,
    Corner,
    PopupAppearance,
    PopupCanvas,
} from '@/popups/launch';

export const RED = '#FF0000';

export function gradientLook(
    gradient: boolean,
    from: string,
    fromOpacity: number,
    to: string,
    toOpacity: number,
    angle: number,
): string {
    const look: LauncherLook = {
        gradient,
        color_from: from,
        opacity_from: fromOpacity,
        color_to: to,
        opacity_to: toOpacity,
        angle,
        icon_color: '#FFFFFF',
        position: 'bottom-right',
        radius: 0,
        offset_x: 0,
        offset_y: 0,
    };

    return buttonBackground(look);
}

function cornerStyle(corner: Corner, x: number, y: number) {
    return {
        [corner.endsWith('left') ? 'left' : 'right']: x,
        [corner.startsWith('top') ? 'top' : 'bottom']: y,
    } as Record<string, number>;
}

const handles: { key: string; style: string; cursor?: string }[] = [
    { key: 'tl', style: 'left-0 top-0' },
    { key: 'tm', style: 'left-1/2 top-0' },
    { key: 'tr', style: 'left-full top-0' },
    { key: 'ml', style: 'left-0 top-1/2', cursor: 'ew-resize' },
    { key: 'mr', style: 'left-full top-1/2', cursor: 'ew-resize' },
    { key: 'bl', style: 'left-0 top-full' },
    { key: 'bm', style: 'left-1/2 top-full', cursor: 'ns-resize' },
    { key: 'br', style: 'left-full top-full' },
];

/** Красная рамка выделения с восемью маркерами, как на экране «Форма». */
export function SelectionFrame({
    onResize,
}: {
    onResize?: (axis: 'x' | 'y', delta: number, side: -1 | 1) => void;
}) {
    const last = useRef<{ x: number; y: number } | null>(null);

    function drag(event: ReactPointerEvent, axis: 'x' | 'y', side: -1 | 1) {
        if (!last.current || event.buttons !== 1) {
            return;
        }

        const delta =
            axis === 'x'
                ? event.clientX - last.current.x
                : event.clientY - last.current.y;
        last.current = { x: event.clientX, y: event.clientY };
        onResize?.(axis, delta, side);
    }

    return (
        <span
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-30 border"
            style={{ borderColor: RED }}
        >
            {handles.map((handle) => {
                const axis =
                    handle.key === 'ml' || handle.key === 'mr'
                        ? 'x'
                        : handle.key === 'bm'
                          ? 'y'
                          : null;
                const side: -1 | 1 = handle.key === 'ml' ? -1 : 1;

                return (
                    <span
                        key={handle.key}
                        className={cn(
                            'absolute size-[7px] -translate-x-1/2 -translate-y-1/2 rounded-full border bg-white',
                            handle.style,
                            axis && onResize
                                ? 'pointer-events-auto touch-none'
                                : '',
                        )}
                        style={{
                            borderColor: RED,
                            cursor:
                                axis && onResize ? handle.cursor : undefined,
                        }}
                        onPointerDown={(event) => {
                            if (!axis || !onResize) {
                                return;
                            }

                            event.currentTarget.setPointerCapture(
                                event.pointerId,
                            );
                            last.current = {
                                x: event.clientX,
                                y: event.clientY,
                            };
                        }}
                        onPointerMove={(event) =>
                            axis && drag(event, axis, side)
                        }
                        onPointerUp={() => {
                            last.current = null;
                        }}
                    />
                );
            })}
        </span>
    );
}

function Selectable({
    id,
    selected,
    onSelect,
    className,
    children,
    preview,
}: {
    id: string;
    selected: string | null;
    onSelect: (id: string) => void;
    className?: string;
    children: ReactNode;
    preview: boolean;
}) {
    return (
        <div
            className={cn(
                'relative z-10',
                !preview && 'cursor-pointer',
                className,
            )}
            onClick={(event) => {
                event.stopPropagation();

                if (!preview) {
                    onSelect(id);
                }
            }}
        >
            {children}
            {!preview && selected === id && (
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -inset-px z-30 border"
                    style={{ borderColor: RED }}
                >
                    {[
                        'left-0 top-0',
                        'left-full top-0',
                        'left-0 top-full',
                        'left-full top-full',
                        'left-0 top-1/2',
                        'left-full top-1/2',
                        'left-1/2 top-0',
                        'left-1/2 top-full',
                    ].map((position) => (
                        <span
                            key={position}
                            className={cn(
                                'absolute size-[7px] -translate-x-1/2 -translate-y-1/2 rounded-full border bg-white',
                                position,
                            )}
                            style={{ borderColor: RED }}
                        />
                    ))}
                </span>
            )}
        </div>
    );
}

export function FormCard({
    title,
    text,
    canvas,
    appearance,
    blocks,
    selected,
    onSelect,
    preview,
    onResize,
    mobile = false,
}: {
    title: string;
    text: string;
    canvas: PopupCanvas;
    appearance: PopupAppearance;
    blocks: Record<CanvasBlock, boolean>;
    selected: string | null;
    onSelect: (key: string | null) => void;
    preview: boolean;
    onResize: (axis: 'x' | 'y', delta: number, side: -1 | 1) => void;
    mobile?: boolean;
}) {
    const dark = appearance.theme === 'dark';
    const ink = dark ? '#FFFFFF' : appearance.title_color;
    const sub = dark ? '#D4D4D4' : '#1F282C';
    const muted = dark ? '#B1B3B5' : '#8F9496';
    const hasArt = blocks.image;
    const radius = canvas.element_radius;
    const background = gradientLook(
        appearance.gradient,
        appearance.color_from,
        appearance.opacity_from,
        appearance.color_to,
        appearance.opacity_to,
        appearance.angle,
    );
    const sel = (key: string) => ({
        id: key,
        selected,
        onSelect: (id: string) => onSelect(id),
        preview,
    });

    return (
        <div
            className="relative"
            style={{ width: mobile ? '100%' : canvas.width }}
            onClick={() => onSelect(null)}
        >
            {blocks.backdrop && (
                <span
                    aria-hidden="true"
                    className="absolute z-0 rounded-full"
                    style={{
                        width: appearance.backdrop_width,
                        height: appearance.backdrop_height,
                        background: gradientLook(
                            appearance.backdrop_gradient,
                            appearance.backdrop_from,
                            appearance.backdrop_from_opacity,
                            appearance.backdrop_to,
                            appearance.backdrop_to_opacity,
                            appearance.backdrop_angle,
                        ),
                        ...cornerStyle(
                            appearance.backdrop_corner,
                            appearance.backdrop_x,
                            appearance.backdrop_y,
                        ),
                    }}
                />
            )}
            {blocks.backdrop && !preview && selected === 'backdrop' && (
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute z-40 border"
                    style={{
                        borderColor: RED,
                        width: appearance.backdrop_width,
                        height: appearance.backdrop_height,
                        ...cornerStyle(
                            appearance.backdrop_corner,
                            appearance.backdrop_x,
                            appearance.backdrop_y,
                        ),
                    }}
                />
            )}
            <article
                className="relative z-10 overflow-hidden font-[Arial,sans-serif]"
                style={{
                    borderRadius: canvas.radius,
                    padding: `${appearance.pad_top}px ${appearance.pad_right}px ${appearance.pad_bottom}px ${appearance.pad_left}px`,
                    background:
                        dark && !appearance.gradient ? '#1F282C' : background,
                    minHeight: appearance.height_auto
                        ? undefined
                        : appearance.height,
                    border: `${appearance.border_width}px solid ${appearance.border_color}${Math.round(
                        (appearance.border_opacity / 100) * 255,
                    )
                        .toString(16)
                        .padStart(2, '0')}`,
                    color: ink,
                }}
            >
                {blocks.image && (
                    <div
                        className="absolute z-0"
                        style={{
                            width: appearance.illustration_width,
                            height: appearance.illustration_height,
                            opacity: appearance.illustration_opacity / 100,
                            ...cornerStyle(
                                appearance.illustration_corner,
                                appearance.illustration_x,
                                appearance.illustration_y,
                            ),
                        }}
                        onClick={(event) => {
                            event.stopPropagation();

                            if (!preview) {
                                onSelect('image');
                            }
                        }}
                    >
                        <Illustration
                            kind={appearance.illustration_kind}
                            className="size-full"
                        />
                        {!preview && selected === 'image' && (
                            <span
                                aria-hidden="true"
                                className="pointer-events-none absolute inset-0 border"
                                style={{ borderColor: RED }}
                            />
                        )}
                    </div>
                )}
                {blocks.close && (
                    <Selectable
                        {...sel('close')}
                        className="absolute top-2 right-2 z-20 text-[#CABCAA]"
                    >
                        <X className="size-4" aria-label="Закрыть" />
                    </Selectable>
                )}
                {blocks.ad_label && (
                    <Selectable {...sel('ad_label')} className="mb-1">
                        <p className="text-[10px]" style={{ color: muted }}>
                            Реклама
                        </p>
                    </Selectable>
                )}
                {blocks.logo && (
                    <Selectable {...sel('logo')} className="mb-2 w-fit">
                        <span
                            className="inline-flex h-8 items-center px-3 text-xs font-bold"
                            style={{ background: '#F0F0F0', color: '#746A5D' }}
                        >
                            ЛОГОТИП
                        </span>
                    </Selectable>
                )}
                {blocks.promo_timer && (
                    <Selectable {...sel('promo_timer')} className="mb-2">
                        <p className="text-xs" style={{ color: sub }}>
                            До конца акции: 23:59:59
                        </p>
                    </Selectable>
                )}
                {blocks.title && (
                    <Selectable
                        {...sel('title')}
                        className={blocks.close ? 'pr-6' : ''}
                    >
                        <h2
                            className="font-normal"
                            style={{
                                fontSize: appearance.title_size,
                                lineHeight: 1.25,
                                textAlign: appearance.title_align,
                            }}
                        >
                            {title || 'Заголовок'}
                        </h2>
                    </Selectable>
                )}
                {blocks.subtitle && text && (
                    <Selectable {...sel('subtitle')} className="mt-1.5">
                        <p
                            style={{
                                fontSize: appearance.subtitle_size,
                                lineHeight: 1.3,
                                color: sub,
                            }}
                        >
                            {text}
                        </p>
                    </Selectable>
                )}
                <div
                    className="mt-3 grid gap-2"
                    style={{ width: hasArt && !mobile ? '58%' : '100%' }}
                >
                    {blocks.name && (
                        <Selectable {...sel('name')}>
                            <FieldMock
                                label="Ваше имя"
                                radius={radius}
                                muted={muted}
                            />
                        </Selectable>
                    )}
                    {blocks.phone && (
                        <Selectable {...sel('phone')}>
                            <FieldMock
                                label="Ваш номер телефона*"
                                radius={radius}
                                muted={muted}
                            />
                        </Selectable>
                    )}
                    {blocks.email && (
                        <Selectable {...sel('email')}>
                            <FieldMock
                                label="E-mail"
                                radius={radius}
                                muted={muted}
                            />
                        </Selectable>
                    )}
                    {blocks.comment && (
                        <Selectable {...sel('comment')}>
                            <FieldMock
                                label="Комментарий"
                                radius={radius}
                                muted={muted}
                                tall
                            />
                        </Selectable>
                    )}
                    {blocks.department && (
                        <Selectable {...sel('department')}>
                            <FieldMock
                                label="Выберите отдел"
                                radius={radius}
                                muted={muted}
                            />
                        </Selectable>
                    )}
                </div>
                {blocks.call_time && (
                    <Selectable {...sel('call_time')} className="mt-3 mb-1">
                        <CallTimeMock appearance={appearance} dark={dark} />
                    </Selectable>
                )}
                {blocks.button && (
                    <Selectable {...sel('button')} className="mt-3">
                        <span
                            className="flex w-full items-center justify-center gap-2 text-base text-white"
                            style={{
                                height: appearance.button_height,
                                borderRadius: radius,
                                background: appearance.button_color,
                            }}
                        >
                            <Phone
                                className="size-4"
                                fill="currentColor"
                                aria-hidden="true"
                            />
                            {appearance.button_label}
                        </span>
                    </Selectable>
                )}
                {blocks.consent && (
                    <Selectable {...sel('consent')} className="mt-3">
                        <p
                            className="text-[8px] leading-[10px]"
                            style={{ color: muted }}
                        >
                            Вы даете согласие на{' '}
                            <span style={{ color: appearance.button_color }}>
                                обработку персональных данных.
                            </span>
                        </p>
                    </Selectable>
                )}
                {blocks.copyright && (
                    <Selectable {...sel('copyright')}>
                        <p
                            className="text-[8px] leading-[10px]"
                            style={{ color: appearance.button_color }}
                        >
                            Сделано в Landflow
                        </p>
                    </Selectable>
                )}
            </article>
            {!preview && selected === null && (
                <SelectionFrame onResize={onResize} />
            )}
        </div>
    );
}

function FieldMock({
    label,
    radius,
    muted,
    tall = false,
}: {
    label: string;
    radius: number;
    muted: string;
    tall?: boolean;
}) {
    return (
        <div>
            <p className="mb-0.5 text-xs" style={{ color: muted }}>
                {label}
            </p>
            <div
                className={cn(
                    'border border-[#8F9496] bg-white',
                    tall ? 'h-16' : 'h-8',
                )}
                style={{ borderRadius: radius }}
            />
        </div>
    );
}

function CallTimeMock({
    appearance,
    dark,
}: {
    appearance: PopupAppearance;
    dark: boolean;
}) {
    const ink = dark ? '#FFFFFF' : appearance.call_time_color;
    const justify =
        appearance.call_time_align === 'center'
            ? 'center'
            : appearance.call_time_align === 'right'
              ? 'flex-end'
              : 'flex-start';

    if (appearance.call_time_variant === 'row') {
        return (
            <p
                className="flex items-center gap-2"
                style={{
                    color: ink,
                    fontSize: appearance.call_time_size,
                    lineHeight: `${appearance.call_time_line}px`,
                    justifyContent: justify,
                }}
            >
                <span>Перезвонить сейчас</span>
                <span className="text-[#CABCAA]">|</span>
                <span
                    className="underline"
                    style={{ color: appearance.call_time_link_color }}
                >
                    Выбрать удобное время
                </span>
            </p>
        );
    }

    return (
        <p
            className="flex items-center gap-4"
            style={{
                color: ink,
                fontSize: appearance.call_time_size,
                lineHeight: `${appearance.call_time_line}px`,
                justifyContent: justify,
            }}
        >
            <span className="inline-flex items-center gap-1.5">
                <span
                    className="inline-flex size-3 items-center justify-center rounded-full border"
                    style={{ borderColor: appearance.call_time_bullet_color }}
                >
                    <span
                        className="size-1.5 rounded-full"
                        style={{
                            background: appearance.call_time_bullet_color,
                        }}
                    />
                </span>
                Сейчас
            </span>
            <span className="inline-flex items-center gap-1.5 opacity-70">
                <span
                    className="inline-flex size-3 rounded-full border"
                    style={{ borderColor: appearance.call_time_bullet_color }}
                />
                Точное время
            </span>
        </p>
    );
}

export function ConfirmCard({
    appearance,
    canvas,
    blocks,
    kind,
    selected,
    onSelect,
    preview,
}: {
    appearance: PopupAppearance;
    canvas: PopupCanvas;
    blocks: Record<ConfirmBlock, boolean>;
    kind: 'notice' | 'replace';
    selected: string | null;
    onSelect: (key: string | null) => void;
    preview: boolean;
}) {
    const sel = (key: string) => ({
        id: key,
        selected,
        onSelect: (id: string) => onSelect(id),
        preview,
    });
    const notice = kind === 'notice';

    return (
        <div
            className="relative"
            style={{ width: appearance.confirm_width }}
            onClick={() => onSelect(null)}
        >
            {!notice && blocks.background && (
                <span
                    aria-hidden="true"
                    className="absolute z-0 rounded-full"
                    style={{
                        width: appearance.backdrop_width,
                        height: appearance.backdrop_height,
                        background: gradientLook(
                            appearance.backdrop_gradient,
                            appearance.backdrop_from,
                            appearance.backdrop_from_opacity,
                            appearance.backdrop_to,
                            appearance.backdrop_to_opacity,
                            appearance.backdrop_angle,
                        ),
                        ...cornerStyle(
                            appearance.backdrop_corner,
                            appearance.backdrop_x,
                            appearance.backdrop_y,
                        ),
                    }}
                />
            )}
            <article
                className={cn(
                    'relative z-10 p-5 text-center',
                    notice ? 'text-white' : 'bg-white text-[#1F282C]',
                )}
                style={{
                    borderRadius: appearance.confirm_radius,
                    minHeight: appearance.confirm_height_auto
                        ? undefined
                        : appearance.confirm_height,
                    background: notice
                        ? gradientLook(
                              true,
                              appearance.backdrop_to,
                              100,
                              appearance.backdrop_from,
                              100,
                              appearance.backdrop_angle,
                          )
                        : undefined,
                }}
            >
                {blocks.close && (
                    <X
                        className="absolute top-2 right-2 size-4 opacity-60"
                        aria-label="Закрыть"
                    />
                )}
                {blocks.image && !notice && (
                    <div className="mx-auto mb-2 h-24 w-24">
                        <Illustration
                            kind={appearance.illustration_kind}
                            className="size-full"
                        />
                    </div>
                )}
                {blocks.icon && (
                    <Selectable {...sel('icon')} className="mx-auto mb-2 w-fit">
                        <CheckCircle2 className="size-9" aria-hidden="true" />
                    </Selectable>
                )}
                {blocks.title && (
                    <Selectable {...sel('title')}>
                        <p className="text-lg font-semibold">Заявка принята</p>
                    </Selectable>
                )}
                {blocks.subtitle && (
                    <Selectable {...sel('subtitle')} className="mt-1">
                        <p className="text-sm">
                            Мы перезвоним вам по номеру +7 (900) ***-00-00
                        </p>
                    </Selectable>
                )}
                {blocks.timer && (
                    <p className="mt-2 text-xs opacity-80">
                        Закроется через 5 сек
                    </p>
                )}
                {blocks.button && !notice && (
                    <span
                        className="mt-3 flex items-center justify-center text-white"
                        style={{
                            height: appearance.button_height,
                            borderRadius: canvas.element_radius,
                            background: appearance.button_color,
                        }}
                    >
                        Закрыть
                    </span>
                )}
                {blocks.copyright && (
                    <p
                        className="mt-2 text-[8px]"
                        style={{ color: appearance.button_color }}
                    >
                        Сделано в Landflow
                    </p>
                )}
            </article>
            {!preview && selected === null && <SelectionFrame />}
        </div>
    );
}

/** Размытая страница сайта за формой. */
export function BlurredSite() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute top-14 -right-10 hidden h-[300px] w-[330px] overflow-hidden rounded-md border-[3px] border-[#D2CCC3] bg-white opacity-60 blur-[3px] lg:block"
        >
            <div className="flex h-7 items-center gap-1.5 bg-[#FAFAFA] px-2">
                <span className="size-2.5 rounded-full bg-[#DDDFDF]" />
                <span className="size-2.5 rounded-full bg-[#DDDFDF]" />
                <span className="size-2.5 rounded-full bg-[#DDDFDF]" />
            </div>
            <div className="space-y-3 p-3">
                <div className="flex items-center gap-3">
                    <span className="size-10 rounded-full bg-[#F2F2F2]" />
                    <span className="h-3 flex-1 bg-[#DDDFDF]" />
                </div>
                <div className="h-24 bg-[#F2F2F2]" />
                <div className="h-3 bg-[#DDDFDF]" />
                <div className="h-3 w-2/3 bg-[#F2F2F2]" />
            </div>
        </div>
    );
}
