export const LAUNCHER_ICONS = [
    ['phone', 'Трубка'],
    ['phone_arrow', 'Трубка со стрелкой'],
    ['phone_waves', 'Трубка с волнами'],
    ['phone_circle', 'Трубка в кругу со стрелками'],
    ['phone_dots', 'Трубка со стрелкой и точками'],
    ['phone_outline', 'Контурная трубка'],
    ['phone_outline_arrow', 'Контурная трубка со стрелкой'],
    ['phone_outline_waves', 'Контурная трубка с волнами'],
    ['phone_outline_circle', 'Контурная трубка со стрелками'],
    ['message', 'Сообщение'],
    ['car', 'Автомобиль'],
] as const;

export type LauncherIcon = (typeof LAUNCHER_ICONS)[number][0];

export const LAUNCHER_HOVER = [
    ['none', 'Без анимации'],
    ['dialog', 'Всплывающий диалог'],
    ['shadow', 'Тень'],
    ['scale', 'Увеличение'],
    ['fade', 'Выцветание'],
] as const;

export type LauncherHover = (typeof LAUNCHER_HOVER)[number][0];

export const LAUNCHER_IDLE = [
    ['none', 'Без анимации'],
    ['waves', 'Волны'],
    ['pulse', 'Пульсация'],
    ['scale', 'Увеличение'],
    ['wobble', 'Колебание'],
    ['shake', 'Тряска'],
    ['jelly', 'Желе'],
    ['spin', 'Вращение иконки'],
] as const;

export type LauncherIdle = (typeof LAUNCHER_IDLE)[number][0] | 'bounce';

export const LAUNCHER_SIZES = [
    [44, 'Мини - 44 px'],
    [64, 'Малый - 64 px'],
    [80, 'Средний - 80 px'],
    [100, 'Большой - 100 px'],
] as const;

export const SCREEN_POSITIONS = [
    'top-left',
    'top-center',
    'top-right',
    'middle-left',
    'middle-center',
    'middle-right',
    'bottom-left',
    'bottom-center',
    'bottom-right',
] as const;

export type ScreenPosition = (typeof SCREEN_POSITIONS)[number];

export type LauncherLook = {
    gradient: boolean;
    color_from: string;
    opacity_from: number;
    color_to: string;
    opacity_to: number;
    angle: number;
    icon_color: string;
    position: ScreenPosition;
    radius: number;
    offset_x: number;
    offset_y: number;
};

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
    look: LauncherLook;
};

export const defaultLook = (): LauncherLook => ({
    gradient: true,
    color_from: '#C76EEC',
    opacity_from: 100,
    color_to: '#28ADCB',
    opacity_to: 100,
    angle: 240,
    icon_color: '#FFFFFF',
    position: 'bottom-right',
    radius: 32,
    offset_x: 24,
    offset_y: 24,
});

export function buttonBackground(look: LauncherLook): string {
    const from = hexWithAlpha(look.color_from, look.opacity_from);
    const to = hexWithAlpha(look.color_to, look.opacity_to);

    return look.gradient
        ? `linear-gradient(${look.angle}deg, ${from}, ${to})`
        : from;
}

function hexWithAlpha(hex: string, opacity: number): string {
    const normalized = /^#[0-9A-Fa-f]{6}$/.test(hex) ? hex : '#C76EEC';
    const value = Number.parseInt(normalized.slice(1), 16);
    const alpha = Math.min(100, Math.max(0, opacity)) / 100;

    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
}

/** Внешний вид диалога у кнопки: свой для десктопа и для мобильных. */
export type DialogStyle = {
    title_size: number;
    title_line: number;
    subtitle_size: number;
    subtitle_line: number;
    button_enabled: boolean;
    cta_icon: LauncherIcon;
    cta_size: number;
    cta_line: number;
    pad_left: number;
    pad_right: number;
    pad_top: number;
    pad_bottom: number;
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
        mobile: DialogStyle;
    } & DialogStyle;
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
    look: defaultLook(),
});

export const dialogStyle = (kind: 'desktop' | 'mobile'): DialogStyle => {
    const mobile = kind === 'mobile';

    return {
        title_size: mobile ? 18 : 19,
        title_line: 24,
        subtitle_size: 14,
        subtitle_line: 16,
        button_enabled: true,
        cta_icon: mobile ? 'phone' : 'phone_waves',
        cta_size: 14,
        cta_line: 16,
        pad_left: mobile ? 16 : 34,
        pad_right: mobile ? 16 : 25,
        pad_top: mobile ? 16 : 18,
        pad_bottom: mobile ? 16 : 15,
    };
};

export const defaultLauncher = (): FloatingLauncher => ({
    enabled: false,
    popup: null,
    desktop: device(32),
    mobile: device(24),
    dialog: {
        title: 'Трудный выбор? Есть вопрос?',
        subtitle: 'Мы перезвоним вам в течение нескольких минут',
        cta_label: 'Оставьте телефон',
        open_delay_seconds: 45,
        show_seconds: 5,
        open_popup: true,
        ...dialogStyle('desktop'),
        mobile: dialogStyle('mobile'),
    },
});
