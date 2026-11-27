import {
    Car,
    MessageCircle,
    Phone,
    PhoneCall,
    PhoneForwarded,
    PhoneIncoming,
    PhoneOutgoing,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';
import {
    buttonBackground,
    defaultLook,
    SCREEN_POSITIONS,
} from '@/popups/launcher';
import type {
    DialogStyle,
    FloatingLauncher,
    LauncherDevice,
    LauncherIcon,
    LauncherLook,
    ScreenPosition,
} from '@/popups/launcher';

const positionClass: Record<ScreenPosition, string> = {
    'top-left': 'top-4 left-4 items-start',
    'top-center': 'top-4 left-1/2 -translate-x-1/2 items-center',
    'top-right': 'top-4 right-4 items-end',
    'middle-left': 'top-1/2 left-4 -translate-y-1/2 items-start',
    'middle-center':
        'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 items-center',
    'middle-right': 'top-1/2 right-4 -translate-y-1/2 items-end',
    'bottom-left': 'bottom-4 left-4 items-start',
    'bottom-center': 'bottom-4 left-1/2 -translate-x-1/2 items-center',
    'bottom-right': 'bottom-4 right-4 items-end',
};

const icons: Record<LauncherIcon, { Icon: typeof Phone; solid: boolean }> = {
    phone: { Icon: Phone, solid: true },
    phone_arrow: { Icon: PhoneIncoming, solid: true },
    phone_waves: { Icon: PhoneCall, solid: true },
    phone_circle: { Icon: PhoneForwarded, solid: true },
    phone_dots: { Icon: PhoneOutgoing, solid: true },
    phone_outline: { Icon: Phone, solid: false },
    phone_outline_arrow: { Icon: PhoneIncoming, solid: false },
    phone_outline_waves: { Icon: PhoneCall, solid: false },
    phone_outline_circle: { Icon: PhoneForwarded, solid: false },
    message: { Icon: MessageCircle, solid: false },
    car: { Icon: Car, solid: false },
};

export function LauncherIconGlyph({
    icon,
    size,
    className,
}: {
    icon: LauncherIcon;
    size: number;
    className?: string;
}) {
    const entry = icons[icon] ?? icons.phone;

    return (
        <entry.Icon
            aria-hidden="true"
            className={className}
            fill={entry.solid ? 'currentColor' : 'none'}
            style={{ width: size, height: size }}
        />
    );
}

/**
 * Всплывающий диалог у кнопки. На десктопе — пузырь у угла, на мобильных —
 * шторка на всю ширину с закрытием внизу (как в Calltouch).
 */
export function LauncherDialog({
    dialog,
    device,
    look,
    showCta,
    onCta,
    onClose,
    className,
    style,
}: {
    dialog: FloatingLauncher['dialog'];
    device: 'desktop' | 'mobile';
    look: LauncherLook;
    showCta: boolean;
    onCta?: (trigger: HTMLElement) => void;
    onClose?: () => void;
    className?: string;
    style?: CSSProperties;
}) {
    const mobile = device === 'mobile';
    const look_: DialogStyle = mobile ? dialog.mobile : dialog;
    const close = (
        <button
            type="button"
            aria-label="Закрыть диалог"
            className="inline-flex size-[21px] items-center justify-center rounded-full border border-white/25 bg-white/10 text-white"
            onClick={onClose}
        >
            <X className="size-3" aria-hidden="true" />
        </button>
    );

    return (
        <div
            role="dialog"
            aria-label={dialog.title}
            className={cn(
                'relative text-white',
                mobile
                    ? 'w-full rounded-t-lg text-center'
                    : 'w-max max-w-[383px] rounded-2xl',
                className,
            )}
            style={{
                background: buttonBackground(look),
                fontFamily: 'Arial, Helvetica, sans-serif',
                paddingLeft: look_.pad_left,
                paddingRight: look_.pad_right,
                paddingTop: look_.pad_top,
                paddingBottom: look_.pad_bottom,
                ...style,
            }}
        >
            {!mobile && (
                <span className="absolute top-[13px] right-[15px]">
                    {close}
                </span>
            )}
            <p
                style={{
                    fontSize: look_.title_size,
                    lineHeight: `${look_.title_line}px`,
                }}
            >
                {dialog.title}
            </p>
            {dialog.subtitle && (
                <p
                    className="mt-[7px]"
                    style={{
                        fontSize: look_.subtitle_size,
                        lineHeight: `${look_.subtitle_line}px`,
                    }}
                >
                    {dialog.subtitle}
                </p>
            )}
            {showCta && look_.button_enabled && (
                <div className="mt-4">
                    <button
                        type="button"
                        className="inline-flex items-center gap-3 rounded bg-white py-2.5 pr-3 pl-2"
                        style={{
                            color: look.color_from,
                            fontSize: look_.cta_size,
                            lineHeight: `${look_.cta_line}px`,
                        }}
                        onClick={(event) => onCta?.(event.currentTarget)}
                    >
                        <LauncherIconGlyph icon={look_.cta_icon} size={20} />
                        {dialog.cta_label}
                    </button>
                </div>
            )}
            {mobile && <div className="mt-4 flex justify-center">{close}</div>}
        </div>
    );
}
const idleClass: Record<string, string> = {
    waves: 'lf-idle-waves',
    pulse: 'animate-pulse',
    scale: 'lf-idle-scale',
    wobble: 'lf-idle-wobble',
    shake: 'lf-idle-shake',
    jelly: 'lf-idle-jelly',
    spin: 'lf-idle-spin',
    bounce: 'animate-bounce',
};

const hoverClass: Record<string, string> = {
    shadow: 'transition-shadow hover:shadow-[0_8px_24px_rgba(0,0,0,0.45)]',
    scale: 'transition-transform hover:scale-110',
    fade: 'transition-opacity hover:opacity-70',
};

function useIdleCycle(idle: string, interval: number): boolean {
    const [active, setActive] = useState(true);

    useEffect(() => {
        if (idle === 'none' || interval <= 0) {
            setActive(true);

            return;
        }

        let timer: number;
        let running = true;

        const tick = () => {
            running = !running;
            setActive(running);
            timer = window.setTimeout(tick, running ? 2000 : interval * 1000);
        };

        setActive(true);
        timer = window.setTimeout(tick, 2000);

        return () => window.clearTimeout(timer);
    }, [idle, interval]);

    return active;
}

export function FloatingLauncherButton({
    launcher,
    onOpenPopup,
    previewDevice = null,
}: {
    launcher: FloatingLauncher;
    onOpenPopup: (trigger: HTMLElement) => void;
    previewDevice?: 'desktop' | 'mobile' | null;
}) {
    const [isMobile, setIsMobile] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(false);

    useEffect(() => {
        if (previewDevice) {
            setIsMobile(previewDevice === 'mobile');

            return;
        }

        const media = window.matchMedia('(max-width: 767px)');
        const sync = () => setIsMobile(media.matches);
        sync();
        media.addEventListener('change', sync);

        return () => media.removeEventListener('change', sync);
    }, [previewDevice]);

    const device: LauncherDevice = isMobile
        ? launcher.mobile
        : launcher.desktop;
    const look = device.look ?? defaultLook();
    const position = SCREEN_POSITIONS.includes(look.position)
        ? look.position
        : 'bottom-right';
    const iconSize = device.auto_icon_size
        ? Math.round(device.size * 0.5)
        : device.icon_size;
    const fullWidth = isMobile && device.variant === 'full_width';
    const bookmark = !isMobile && device.variant === 'bookmark';
    const idleActive = useIdleCycle(device.idle, device.interval_seconds);

    useEffect(() => {
        if (
            device.hover !== 'dialog' ||
            launcher.dialog.open_delay_seconds <= 0
        ) {
            return;
        }

        const timer = window.setTimeout(
            () => setDialogOpen(true),
            launcher.dialog.open_delay_seconds * 1000,
        );

        return () => window.clearTimeout(timer);
    }, [device.hover, launcher.dialog.open_delay_seconds]);

    useEffect(() => {
        if (!dialogOpen) {
            return;
        }

        const timer = window.setTimeout(
            () => setDialogOpen(false),
            launcher.dialog.show_seconds * 1000,
        );

        return () => window.clearTimeout(timer);
    }, [dialogOpen, launcher.dialog.show_seconds]);

    const top = position.startsWith('top');
    const dialogNode =
        dialogOpen && device.hover === 'dialog' ? (
            <LauncherDialog
                dialog={launcher.dialog}
                device={isMobile ? 'mobile' : 'desktop'}
                look={look}
                showCta={launcher.dialog.open_popup}
                onCta={onOpenPopup}
                onClose={() => setDialogOpen(false)}
            />
        ) : null;

    return (
        <>
            <div
                className={cn(
                    'z-40 flex flex-col gap-2',
                    previewDevice ? 'absolute' : 'fixed',
                    fullWidth && !previewDevice
                        ? 'right-0 bottom-0 left-0'
                        : positionClass[position],
                )}
                style={
                    fullWidth
                        ? undefined
                        : {
                              ...(position.endsWith('left')
                                  ? { left: look.offset_x ?? 24 }
                                  : {}),
                              ...(position.endsWith('right')
                                  ? { right: look.offset_x ?? 24 }
                                  : {}),
                              ...(position.startsWith('top')
                                  ? { top: look.offset_y ?? 24 }
                                  : {}),
                              ...(position.startsWith('bottom')
                                  ? { bottom: look.offset_y ?? 24 }
                                  : {}),
                          }
                }
            >
                {!isMobile && !top && dialogNode}
                <button
                    type="button"
                    data-testid="floating-launcher"
                    aria-label={device.text}
                    onMouseEnter={() => {
                        if (device.hover === 'dialog') {
                            setDialogOpen(true);
                        }
                    }}
                    onClick={(event) => onOpenPopup(event.currentTarget)}
                    className={cn(
                        'inline-flex items-center justify-center gap-2 shadow-lg focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:outline-none',
                        fullWidth
                            ? 'h-14 w-full rounded-none text-base'
                            : bookmark
                              ? 'rounded-l-md rounded-r-none'
                              : '',
                        hoverClass[device.hover],
                        idleActive && idleClass[device.idle],
                    )}
                    style={{
                        background: buttonBackground(look),
                        color: look.icon_color,
                        borderRadius: fullWidth
                            ? undefined
                            : bookmark
                              ? position.endsWith('left')
                                  ? `0 ${look.radius}px ${look.radius}px 0`
                                  : `${look.radius}px 0 0 ${look.radius}px`
                              : look.radius,
                        ...(fullWidth
                            ? {}
                            : bookmark
                              ? {
                                    width: Math.round(device.size * 0.72),
                                    height: device.size,
                                }
                              : { width: device.size, height: device.size }),
                    }}
                >
                    {device.text_instead_of_icon && !bookmark ? (
                        <span className="px-3 text-sm font-medium">
                            {device.text}
                        </span>
                    ) : (
                        <LauncherIconGlyph
                            icon={device.icon}
                            size={iconSize}
                            className={cn(
                                device.idle === 'spin' &&
                                    idleActive &&
                                    'lf-icon-spin',
                            )}
                        />
                    )}
                </button>
                {!isMobile && top && dialogNode}
            </div>
            {isMobile && dialogNode && (
                <div
                    className={cn(
                        'inset-x-0 z-50',
                        previewDevice ? 'absolute' : 'fixed',
                        top ? 'top-0' : 'bottom-0',
                    )}
                >
                    {dialogNode}
                </div>
            )}
        </>
    );
}
