import { Car, MessageCircle, Phone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { FloatingLauncher, LauncherDevice } from '@/popups/launcher';

const icons = {
    phone: Phone,
    message: MessageCircle,
    car: Car,
};

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
    const Icon = icons[device.icon];
    const iconSize = device.auto_icon_size
        ? Math.round(device.size * 0.5)
        : device.icon_size;
    const fullWidth = isMobile && device.variant === 'full_width';
    const bookmark = !isMobile && device.variant === 'bookmark';

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

    return (
        <div
            className={cn(
                'z-40 flex flex-col items-end gap-2',
                previewDevice
                    ? 'relative'
                    : 'fixed right-4 bottom-4 max-sm:right-0 max-sm:bottom-0',
                fullWidth && !previewDevice && 'right-0 left-0 px-0',
            )}
        >
            {dialogOpen && device.hover === 'dialog' && (
                <div
                    role="dialog"
                    aria-label={launcher.dialog.title}
                    className="max-w-xs rounded-lg border bg-white p-3 text-sm shadow-lg"
                >
                    <p className="font-medium">{launcher.dialog.title}</p>
                    {launcher.dialog.subtitle && (
                        <p className="mt-1 text-muted-foreground">
                            {launcher.dialog.subtitle}
                        </p>
                    )}
                    {launcher.dialog.open_popup && (
                        <button
                            type="button"
                            className="mt-2 text-sm font-medium text-(--lf-primary)"
                            onClick={(event) =>
                                onOpenPopup(event.currentTarget)
                            }
                        >
                            {launcher.dialog.cta_label}
                        </button>
                    )}
                </div>
            )}
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
                    'inline-flex items-center justify-center gap-2 bg-(--lf-primary) text-(--lf-on-primary) shadow-lg focus-visible:ring-2 focus-visible:ring-(--lf-primary) focus-visible:outline-none',
                    fullWidth
                        ? 'h-14 w-full rounded-none text-base'
                        : bookmark
                          ? 'rounded-l-md rounded-r-none'
                          : 'rounded-full',
                    device.hover === 'scale' &&
                        'transition-transform hover:scale-110',
                    device.idle === 'pulse' && 'animate-pulse',
                    device.idle === 'bounce' && 'animate-bounce',
                )}
                style={
                    fullWidth
                        ? undefined
                        : bookmark
                          ? { width: device.width, minHeight: device.size }
                          : { width: device.size, height: device.size }
                }
            >
                {device.text_instead_of_icon || bookmark ? (
                    <span className="px-3 text-sm font-medium">
                        {bookmark && device.subtitle
                            ? `${device.text} · ${device.subtitle}`
                            : device.text}
                    </span>
                ) : (
                    <Icon
                        aria-hidden="true"
                        style={{ width: iconSize, height: iconSize }}
                    />
                )}
            </button>
        </div>
    );
}
