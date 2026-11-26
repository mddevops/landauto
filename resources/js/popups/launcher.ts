export type LauncherIcon = 'phone' | 'message' | 'car';
export type LauncherHover = 'none' | 'dialog' | 'scale';
export type LauncherIdle = 'none' | 'pulse' | 'bounce';

export type LauncherDevice = {
    variant: string;
    size: number;
    text_instead_of_icon: boolean;
    text: string;
    icon: LauncherIcon;
    auto_icon_size: boolean;
    icon_size: number;
    hover: LauncherHover;
    idle: LauncherIdle;
    interval_seconds: number;
    width: number;
    subtitle: string;
};

export type FloatingLauncher = {
    enabled: boolean;
    popup: string | null;
    desktop: LauncherDevice;
    mobile: LauncherDevice;
    dialog: {
        title: string;
        subtitle: string;
        cta_label: string;
        open_delay_seconds: number;
        show_seconds: number;
        open_popup: boolean;
    };
};

const device = (iconSize: number): LauncherDevice => ({
    variant: 'standard',
    size: 64,
    text_instead_of_icon: false,
    text: 'Звонок',
    icon: 'phone',
    auto_icon_size: true,
    icon_size: iconSize,
    hover: 'dialog',
    idle: 'none',
    interval_seconds: 0,
    width: 206,
    subtitle: '',
});

export const defaultLauncher = (): FloatingLauncher => ({
    enabled: false,
    popup: null,
    desktop: device(32),
    mobile: device(24),
    dialog: {
        title: 'Нужна помощь?',
        subtitle: 'Оставьте заявку — перезвоним.',
        cta_label: 'Открыть форму',
        open_delay_seconds: 0,
        show_seconds: 5,
        open_popup: true,
    },
});
