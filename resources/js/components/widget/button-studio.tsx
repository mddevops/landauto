import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent, PointerEvent as ReactPointerEvent } from 'react';
import {
    BlockToggle,
    CornerIcon,
    CtButton,
    CtCheckbox,
    CtColor,
    CtField,
    CtIconChoice,
    CtInput,
    CtLabel,
    CtNumber,
    CtSection,
    CtSelect,
    CtSwitch,
    CtTextarea,
    ElementSwitcher,
    LabeledSelect,
    NumberField,
    PanelHead,
    RadiusIcon,
} from '@/components/widget/ct';
import {
    DeviceToggle,
    PHONE_MODELS,
    PhoneMock,
    SiteMock,
    StudioFrame,
    usePhoneView,
} from '@/components/widget/widget-frame';
import { cn } from '@/lib/utils';
import {
    FloatingLauncherButton,
    LauncherDialog,
    LauncherIconGlyph,
} from '@/popups/floating-launcher-button';
import {
    LAUNCHER_HOVER,
    LAUNCHER_ICONS,
    LAUNCHER_IDLE,
    LAUNCHER_SIZES,
    buttonBackground,
    defaultLauncher,
    defaultLook,
} from '@/popups/launcher';
import type {
    DialogStyle,
    FloatingLauncher,
    LauncherDevice,
    LauncherHover,
    LauncherIcon,
    LauncherIdle,
    LauncherLook,
    ScreenPosition,
} from '@/popups/launcher';

type Mode = 'general' | 'button' | 'dialog';

const desktopCorners: ScreenPosition[] = [
    'top-left',
    'top-right',
    'bottom-left',
    'bottom-right',
];
const bookmarkSlots: ScreenPosition[] = [
    'middle-left',
    'top-right',
    'middle-right',
    'bottom-right',
];
const mobileSlots: ScreenPosition[] = [
    'top-left',
    'top-right',
    'bottom-left',
    'bottom-center',
    'bottom-right',
];

const positionLabels: Record<string, string> = {
    'top-left': 'Сверху слева',
    'top-center': 'Сверху по центру',
    'top-right': 'Сверху справа',
    'middle-left': 'Слева по центру',
    'middle-center': 'По центру',
    'middle-right': 'Справа по центру',
    'bottom-left': 'Снизу слева',
    'bottom-center': 'Снизу по центру',
    'bottom-right': 'Снизу справа',
};

function complete(launcher: FloatingLauncher): FloatingLauncher {
    const base = defaultLauncher();
    const merged = {
        ...base,
        ...launcher,
        desktop: {
            ...base.desktop,
            ...launcher.desktop,
            look: { ...defaultLook(), ...launcher.desktop?.look },
        },
        mobile: {
            ...base.mobile,
            ...launcher.mobile,
            look: { ...defaultLook(), ...launcher.mobile?.look },
        },
        dialog: {
            ...base.dialog,
            ...launcher.dialog,
            mobile: { ...base.dialog.mobile, ...launcher.dialog?.mobile },
        },
    };

    return {
        ...merged,
        enabled: launcher.enabled || launcher.popup === null,
    };
}

const cornerOf = (position: ScreenPosition) =>
    `${position.startsWith('top') ? 'top' : 'bottom'}-${
        position.endsWith('left') ? 'left' : 'right'
    }` as 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';

export function ButtonStudio({
    sitePublicId,
    launcher,
    popups,
    onBack,
}: {
    sitePublicId: string;
    launcher: FloatingLauncher;
    popups: { public_id: string; name: string; status: boolean }[];
    onBack: () => void;
}) {
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const [mode, setMode] = useState<Mode>('general');
    const form = useForm(complete(launcher));
    const current = device === 'desktop' ? form.data.desktop : form.data.mobile;
    const look = current.look;
    const positions =
        device === 'mobile'
            ? mobileSlots
            : current.variant === 'bookmark'
              ? bookmarkSlots
              : desktopCorners;
    const dialogOn = current.hover === 'dialog';
    const phoneView = usePhoneView();

    function setCurrent(next: LauncherDevice) {
        form.setData(device, next);
    }

    function setLook(patch: Partial<LauncherLook>) {
        setCurrent({ ...current, look: { ...look, ...patch } });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            dialog: {
                ...data.dialog,
                open_popup: Boolean(data.popup),
            },
        }));
        form.patch(`/sites/${sitePublicId}/popups-launcher`, {
            preserveScroll: true,
            onSuccess: onBack,
        });
    }

    const switcherItems = [
        {
            value: 'general',
            label:
                device === 'desktop'
                    ? 'Кнопка для десктопа'
                    : 'Кнопка для мобильных',
        },
        { value: 'button', label: 'Кнопка' },
        { value: 'dialog', label: 'Всплывающий диалог' },
    ];
    const horizontal = look.position.endsWith('left') ? 'left' : 'right';
    const vertical = look.position.startsWith('top')
        ? 'top'
        : look.position.startsWith('middle')
          ? 'middle'
          : 'bottom';

    const length = 300;
    const phoneModel =
        PHONE_MODELS.find((item) => item.id === phoneView.model) ??
        PHONE_MODELS[0];
    const mockWidth =
        device === 'desktop'
            ? 802
            : Math.round(phoneModel.width * phoneView.zoom * 0.96) + 20;
    const centered = look.position.endsWith('center');
    const dialogLook = device === 'desktop' ? 'desktop' : 'mobile';

    const dialogStage = (
        <>
            {device === 'desktop' ? (
                <LauncherDialog
                    dialog={form.data.dialog}
                    device="desktop"
                    look={look}
                    showCta
                    className="absolute z-30"
                    style={{
                        [look.position.endsWith('left') ? 'left' : 'right']:
                            look.offset_x,
                        [look.position.startsWith('top') ? 'top' : 'bottom']:
                            look.offset_y,
                    }}
                />
            ) : (
                <div
                    className={cn(
                        'absolute inset-x-0 z-30',
                        vertical === 'top' ? 'top-0' : 'bottom-0',
                    )}
                >
                    <LauncherDialog
                        dialog={form.data.dialog}
                        device={dialogLook}
                        look={look}
                        showCta
                    />
                </div>
            )}
        </>
    );
    const stage =
        mode === 'dialog' ? (
            dialogStage
        ) : (
            <FloatingLauncherButton
                launcher={{ ...form.data, enabled: true }}
                previewDevice={device}
                onOpenPopup={() => undefined}
            />
        );

    const cards = (side: boolean) => (
        <>
            <ElementCard
                side={side}
                label="Кнопка"
                active={mode === 'button'}
                onClick={() => setMode('button')}
            >
                <span
                    className="inline-flex size-12 items-center justify-center rounded-full text-white"
                    style={{
                        background: buttonBackground(look),
                        color: look.icon_color,
                    }}
                >
                    <LauncherIconGlyph icon={current.icon} size={24} />
                </span>
            </ElementCard>
            <ElementCard
                side={side}
                label="Всплывающий диалог"
                caption={dialogOn ? undefined : 'Выключен'}
                active={mode === 'dialog'}
                onClick={() => setMode('dialog')}
            >
                <span
                    className="grid w-[84px] gap-0.5 rounded-sm px-2 py-1.5 text-left text-white"
                    style={{
                        background: buttonBackground(look),
                    }}
                >
                    <span className="truncate text-[8px] leading-[10px] font-bold">
                        {form.data.dialog.title}
                    </span>
                    <span className="truncate text-[6px] leading-[8px]">
                        {form.data.dialog.subtitle}
                    </span>
                    <span
                        className="mt-0.5 truncate rounded-[2px] bg-white px-1 text-[6px] leading-[10px]"
                        style={{ color: look.color_from }}
                    >
                        {form.data.dialog.cta_label}
                    </span>
                </span>
            </ElementCard>
        </>
    );
    return (
        <form onSubmit={submit} className="contents">
            <StudioFrame
                title="Виджет обратного звонка"
                onBack={onBack}
                tabs={[{ id: 'button', label: 'Кнопка' }]}
                tab="button"
                enabled={form.data.enabled}
                onEnabled={(enabled) => form.setData('enabled', enabled)}
                dirty={form.isDirty}
                saving={form.processing}
                onDiscard={() => {
                    form.reset();
                    form.clearErrors();
                    setMode('general');
                }}
                panel={
                    <>
                        <PanelHead
                            caption={
                                mode === 'general'
                                    ? 'Общие настройки'
                                    : 'Настройки элемента'
                            }
                            title=""
                            switcher={
                                <ElementSwitcher
                                    value={mode}
                                    items={switcherItems}
                                    onChange={(value) => setMode(value as Mode)}
                                />
                            }
                            onClose={
                                mode === 'general'
                                    ? undefined
                                    : () => setMode('general')
                            }
                        />
                        {mode === 'general' && (
                            <GeneralPanel
                                device={device}
                                current={current}
                                look={look}
                                positions={positions}
                                popup={form.data.popup}
                                popups={popups}
                                dialogOn={dialogOn}
                                onPopup={(popup) =>
                                    form.setData('popup', popup || null)
                                }
                                onCurrent={setCurrent}
                                onLook={setLook}
                                onCopyDesktop={() =>
                                    form.setData('mobile', {
                                        ...form.data.desktop,
                                        variant:
                                            form.data.desktop.variant ===
                                            'bookmark'
                                                ? 'standard'
                                                : form.data.desktop.variant,
                                        look: { ...form.data.desktop.look },
                                    })
                                }
                            />
                        )}
                        {mode === 'button' && (
                            <ButtonPanel
                                device={device}
                                current={current}
                                onCurrent={setCurrent}
                            />
                        )}
                        {mode === 'dialog' && (
                            <DialogPanel
                                device={device}
                                dialog={form.data.dialog}
                                onChange={(dialog) =>
                                    form.setData('dialog', dialog)
                                }
                            />
                        )}
                        {form.errors && Object.keys(form.errors).length > 0 && (
                            <p
                                role="alert"
                                className="py-3 text-sm text-red-600"
                            >
                                Проверьте настройки кнопки.
                            </p>
                        )}
                    </>
                }
            >
                <DeviceToggle
                    device={device}
                    onDevice={(next) => {
                        setDevice(next);
                    }}
                    phone={phoneView}
                />
                <div className="mt-6 flex justify-center">
                    <div style={{ width: mockWidth }}>
                        <div
                            className="relative"
                            style={{ paddingTop: vertical === 'top' ? 94 : 0 }}
                        >
                            {device === 'desktop' ? (
                                <SiteMock className="w-[802px] max-w-none">
                                    {stage}
                                </SiteMock>
                            ) : (
                                <PhoneMock view={phoneView}>{stage}</PhoneMock>
                            )}
                            {device === 'mobile' && (
                                <div
                                    className="absolute flex flex-col gap-3"
                                    style={{
                                        right: 'calc(100% + 77px)',
                                        top: vertical === 'top' ? 94 : 0,
                                    }}
                                >
                                    {cards(true)}
                                </div>
                            )}
                            {mode !== 'dialog' && vertical !== 'middle' && (
                                <div
                                    className="absolute"
                                    style={{
                                        [horizontal === 'right'
                                            ? 'left'
                                            : 'right']:
                                            `calc(100% + ${device === 'mobile' ? 34 : 24}px)`,
                                        ...(vertical === 'top'
                                            ? { top: 97 }
                                            : {
                                                  bottom:
                                                      device === 'mobile'
                                                          ? 14
                                                          : 4,
                                              }),
                                    }}
                                >
                                    <OffsetSlider
                                        orientation="vertical"
                                        label="Отступ по вертикали"
                                        value={look.offset_y}
                                        length={length}
                                        reversed={vertical === 'bottom'}
                                        boxSide={horizontal}
                                        boxAlign={
                                            vertical === 'top' ? 'start' : 'end'
                                        }
                                        onChange={(offset_y) =>
                                            setLook({ offset_y })
                                        }
                                    />
                                </div>
                            )}
                            {mode !== 'dialog' && !centered && (
                                <div
                                    className="absolute"
                                    style={{
                                        [horizontal]: 0,
                                        top:
                                            vertical === 'top'
                                                ? 14
                                                : 'calc(100% + 23px)',
                                    }}
                                >
                                    <OffsetSlider
                                        orientation="horizontal"
                                        label="Отступ по горизонтали"
                                        value={look.offset_x}
                                        length={length}
                                        reversed={horizontal === 'right'}
                                        boxSide={
                                            vertical === 'top'
                                                ? 'above'
                                                : 'below'
                                        }
                                        boxAlign={
                                            horizontal === 'left'
                                                ? 'start'
                                                : 'end'
                                        }
                                        onChange={(offset_x) =>
                                            setLook({ offset_x })
                                        }
                                    />
                                </div>
                            )}
                        </div>
                        {device === 'desktop' && (
                            <div
                                className="mt-4 flex gap-5"
                                style={{
                                    paddingLeft:
                                        vertical !== 'top' &&
                                        horizontal === 'left' &&
                                        !centered
                                            ? length + 45
                                            : 0,
                                }}
                            >
                                {cards(false)}
                            </div>
                        )}
                    </div>
                </div>
            </StudioFrame>
        </form>
    );
}

function OffsetSlider({
    orientation,
    label,
    value,
    reversed,
    onChange,
    length,
    boxSide,
    boxAlign,
    max = 300,
}: {
    orientation: 'vertical' | 'horizontal';
    label: string;
    value: number;
    reversed: boolean;
    onChange: (value: number) => void;
    length: number;
    boxSide: 'left' | 'right' | 'above' | 'below';
    boxAlign: 'start' | 'end';
    max?: number;
}) {
    const track = useRef<HTMLDivElement>(null);
    const vertical = orientation === 'vertical';
    const fraction = Math.min(1, Math.max(0, value / max));
    const along = reversed ? 1 - fraction : fraction;

    function move(event: ReactPointerEvent) {
        const box = track.current?.getBoundingClientRect();

        if (!box) {
            return;
        }

        const raw = vertical
            ? (event.clientY - box.top) / box.height
            : (event.clientX - box.left) / box.width;
        const t = Math.min(1, Math.max(0, raw));

        onChange(Math.round((reversed ? 1 - t : t) * max));
    }

    return (
        <div
            className={cn(
                'flex',
                vertical
                    ? cn('gap-3', boxSide === 'left' && 'flex-row-reverse')
                    : cn(
                          'flex-col gap-2.5',
                          boxSide === 'above' && 'flex-col-reverse',
                      ),
            )}
            style={vertical ? { height: length } : { width: length }}
        >
            <div
                ref={track}
                role="slider"
                tabIndex={0}
                aria-label={label}
                aria-orientation={orientation}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-valuenow={value}
                className={cn(
                    'relative shrink-0 cursor-pointer touch-none',
                    vertical ? 'w-3 self-stretch' : 'h-3 w-full',
                )}
                onPointerDown={(event) => {
                    event.currentTarget.setPointerCapture(event.pointerId);
                    move(event);
                }}
                onPointerMove={(event) => {
                    if (event.buttons === 1) {
                        move(event);
                    }
                }}
                onKeyDown={(event) => {
                    const step =
                        event.key === 'ArrowRight' || event.key === 'ArrowUp'
                            ? 1
                            : event.key === 'ArrowLeft' ||
                                event.key === 'ArrowDown'
                              ? -1
                              : 0;

                    if (step !== 0) {
                        event.preventDefault();
                        onChange(
                            Math.min(
                                max,
                                Math.max(0, value + (reversed ? -step : step)),
                            ),
                        );
                    }
                }}
            >
                <span
                    aria-hidden="true"
                    className={cn(
                        'absolute bg-[#CABCAA]',
                        vertical
                            ? 'top-0 bottom-0 left-1/2 w-[3px] -translate-x-1/2'
                            : 'top-1/2 right-0 left-0 h-[3px] -translate-y-1/2',
                    )}
                />
                <span
                    aria-hidden="true"
                    className={cn(
                        'absolute size-3 rounded-full bg-[#6A6155]',
                        vertical
                            ? 'left-1/2 -translate-x-1/2 -translate-y-1/2'
                            : 'top-1/2 -translate-x-1/2 -translate-y-1/2',
                    )}
                    style={
                        vertical
                            ? { top: `${along * 100}%` }
                            : { left: `${along * 100}%` }
                    }
                />
            </div>
            <div
                className={cn(
                    'flex h-8 w-[72px] shrink-0 items-center border border-[#CABCAA] bg-white px-1.5 text-sm text-[#1F282C]',
                    boxAlign === 'start' ? 'self-start' : 'self-end',
                )}
            >
                <input
                    type="number"
                    min={0}
                    max={max}
                    aria-label={`${label}: значение`}
                    value={value}
                    className="w-8 [appearance:textfield] bg-transparent outline-none [&::-webkit-inner-spin-button]:appearance-none"
                    onChange={(event) =>
                        onChange(
                            Math.min(
                                max,
                                Math.max(0, Number(event.target.value) || 0),
                            ),
                        )
                    }
                />
                <span className="ml-auto text-[#8F9496]">px</span>
            </div>
        </div>
    );
}
function ElementCard({
    label,
    caption,
    active,
    onClick,
    side = false,
    children,
}: {
    label: string;
    caption?: string;
    active?: boolean;
    onClick?: () => void;
    side?: boolean;
    children?: React.ReactNode;
}) {
    const text = (
        <div className={side ? 'w-[120px] pt-0.5 text-right' : ''}>
            <p className="text-[13px] leading-4 text-black">{label}</p>
            {caption && (
                <p className="mt-1 text-[11px] leading-[14px] text-[#ADA7A0]">
                    {caption}
                </p>
            )}
        </div>
    );
    const card = (
        <button
            type="button"
            aria-pressed={active}
            aria-label={label}
            onClick={onClick}
            className={cn(
                'flex h-[76px] w-[76px] shrink-0 items-center justify-center overflow-hidden bg-white',
                active && 'ring-[3px] ring-[#D2CCC3] ring-inset',
            )}
        >
            {children}
        </button>
    );

    return side ? (
        <div className="flex items-start justify-end gap-3">
            {text}
            {card}
        </div>
    ) : (
        <div className="w-[76px]">
            {card}
            <div className="mt-1.5">{text}</div>
        </div>
    );
}
function GeneralPanel({
    device,
    current,
    look,
    positions,
    popup,
    popups,
    dialogOn,
    onPopup,
    onCurrent,
    onLook,
    onCopyDesktop,
}: {
    device: 'desktop' | 'mobile';
    current: LauncherDevice;
    look: LauncherLook;
    positions: ScreenPosition[];
    popup: string | null;
    popups: { public_id: string; name: string }[];
    dialogOn: boolean;
    onPopup: (popup: string) => void;
    onCurrent: (next: LauncherDevice) => void;
    onLook: (patch: Partial<LauncherLook>) => void;
    onCopyDesktop: () => void;
}) {
    const typeOptions =
        device === 'desktop'
            ? [
                  { value: 'standard', label: 'Стандартная кнопка' },
                  { value: 'bookmark', label: 'Кнопка-закладка' },
              ]
            : [
                  { value: 'standard', label: 'Стандартная кнопка' },
                  { value: 'full_width', label: 'Кнопка во всю ширину' },
              ];

    return (
        <div className="pb-4">
            {device === 'mobile' && (
                <CtButton
                    tone="brown"
                    className="mb-4 w-full justify-center"
                    onClick={onCopyDesktop}
                >
                    Скопировать настройки с десктопа
                </CtButton>
            )}
            <div className="grid gap-4 pb-5">
                <CtField label="Тип кнопки" htmlFor="launcher-type">
                    <CtSelect
                        id="launcher-type"
                        value={current.variant}
                        options={typeOptions}
                        ariaLabel="Тип кнопки"
                        onChange={(variant) => {
                            const bookmark = variant === 'bookmark';
                            onCurrent({
                                ...current,
                                variant,
                                look: {
                                    ...look,
                                    radius: bookmark ? 22 : 32,
                                    position: bookmark
                                        ? 'middle-right'
                                        : 'bottom-right',
                                },
                            });
                        }}
                    />
                </CtField>
                <LabeledSelect
                    id="launcher-popup"
                    label="Какой попап открывает"
                    value={popup ?? ''}
                    onChange={onPopup}
                >
                    <option value="">Не выбран</option>
                    {popups.map((item) => (
                        <option key={item.public_id} value={item.public_id}>
                            {item.name}
                        </option>
                    ))}
                </LabeledSelect>
            </div>
            <CtSection title="Внешний вид">
                <CtCheckbox
                    label="Использовать градиент"
                    checked={look.gradient}
                    onCheckedChange={(gradient) => onLook({ gradient })}
                />
                <div className="grid gap-1.5">
                    <div className="flex gap-2">
                        <CtLabel className="flex-1">Цвет кнопки</CtLabel>
                        <CtLabel className="w-24">Непрозрачность</CtLabel>
                    </div>
                    <CtColor
                        label="Цвет кнопки"
                        color={look.color_from}
                        opacity={look.opacity_from}
                        onColor={(color_from) => onLook({ color_from })}
                        onOpacity={(opacity_from) => onLook({ opacity_from })}
                    />
                    {look.gradient && (
                        <CtColor
                            label="Второй цвет кнопки"
                            color={look.color_to}
                            opacity={look.opacity_to}
                            onColor={(color_to) => onLook({ color_to })}
                            onOpacity={(opacity_to) => onLook({ opacity_to })}
                        />
                    )}
                </div>
                {look.gradient && (
                    <div className="flex items-end gap-3">
                        <CtField label="Градиент" className="w-40">
                            <span
                                className="block h-8"
                                style={{
                                    background: `linear-gradient(90deg, ${look.color_to}, ${look.color_from})`,
                                }}
                            />
                        </CtField>
                        <CtField label="Угол градиента" className="flex-1">
                            <CtNumber
                                value={look.angle}
                                min={0}
                                max={360}
                                suffix="°"
                                ariaLabel="Угол градиента"
                                onChange={(angle) => onLook({ angle })}
                            />
                        </CtField>
                    </div>
                )}
                <CtField label="Цвет иконки и текста">
                    <CtColor
                        label="Цвет иконки и текста"
                        color={look.icon_color}
                        showOpacity={false}
                        onColor={(icon_color) => onLook({ icon_color })}
                    />
                </CtField>
                <CtField label="Расположение на экране">
                    <CtIconChoice
                        label="Расположение на экране"
                        value={look.position}
                        onChange={(position) => onLook({ position })}
                        items={positions.map((position) => ({
                            value: position,
                            label: positionLabels[position],
                            icon: <CornerIcon corner={cornerOf(position)} />,
                        }))}
                    />
                </CtField>
                <NumberField
                    label="Радиус скругления"
                    value={look.radius}
                    min={0}
                    max={80}
                    onChange={(radius) => onLook({ radius })}
                />
                <CtIconChoice
                    label="Форма углов"
                    value={
                        look.radius <= 0
                            ? 'square'
                            : look.radius >= 32
                              ? 'round'
                              : 'soft'
                    }
                    onChange={(kind) =>
                        onLook({
                            radius:
                                kind === 'square'
                                    ? 0
                                    : kind === 'soft'
                                      ? 12
                                      : 32,
                        })
                    }
                    items={[
                        {
                            value: 'square',
                            label: 'Без скругления',
                            icon: <RadiusIcon kind="square" />,
                        },
                        {
                            value: 'soft',
                            label: 'Мягкие углы',
                            icon: <RadiusIcon kind="soft" />,
                        },
                        {
                            value: 'round',
                            label: 'Круглая',
                            icon: <RadiusIcon kind="round" />,
                        },
                    ]}
                />
            </CtSection>
            <CtSection title="Регулируемые блоки" help>
                <BlockToggle label="Кнопка" checked disabled />
                <BlockToggle
                    label="Всплывающий диалог"
                    checked={dialogOn}
                    onChange={() =>
                        onCurrent({
                            ...current,
                            hover: dialogOn ? 'none' : 'dialog',
                        })
                    }
                />
            </CtSection>
            <CtSection title="Шрифты" help>
                <p className="flex h-8 items-center bg-[#F0F0F0] px-2 text-sm">
                    Arial
                </p>
                <div>
                    <CtButton
                        tone="brown"
                        disabled
                        title="Загрузка своих шрифтов пока недоступна"
                    >
                        + Добавить шрифт
                    </CtButton>
                </div>
                <CtField label="Шрифт по умолчанию">
                    <CtSelect
                        value="arial"
                        options={[{ value: 'arial', label: 'Arial' }]}
                        ariaLabel="Шрифт по умолчанию"
                        onChange={() => undefined}
                    />
                </CtField>
            </CtSection>
        </div>
    );
}

function ButtonPanel({
    device,
    current,
    onCurrent,
}: {
    device: 'desktop' | 'mobile';
    current: LauncherDevice;
    onCurrent: (next: LauncherDevice) => void;
}) {
    const preset = LAUNCHER_SIZES.some(([size]) => size === current.size);
    const sizeValue = preset ? String(current.size) : 'custom';
    const iconOptions = LAUNCHER_ICONS.map(([value, label]) => ({
        value,
        label,
        icon: (
            <span className="inline-flex w-5 justify-center text-[#3DAFCC]">
                <LauncherIconGlyph icon={value} size={14} />
            </span>
        ),
    }));

    return (
        <div className="pb-4">
            <div className="grid gap-4 pb-5">
                <CtField label="Размер кнопки" htmlFor="launcher-size">
                    <CtSelect
                        id="launcher-size"
                        className="w-52"
                        value={sizeValue}
                        ariaLabel="Размер кнопки"
                        options={[
                            ...LAUNCHER_SIZES.filter(
                                ([size]) => device === 'desktop' || size <= 80,
                            ).map(([size, label]) => ({
                                value: String(size),
                                label,
                            })),
                            { value: 'custom', label: 'Задать вручную' },
                        ]}
                        onChange={(value) =>
                            onCurrent({
                                ...current,
                                size:
                                    value === 'custom'
                                        ? preset
                                            ? current.size + 8
                                            : current.size
                                        : Number(value),
                            })
                        }
                    />
                </CtField>
                {sizeValue === 'custom' && (
                    <NumberField
                        label="Размер кнопки, px"
                        value={current.size}
                        min={24}
                        max={160}
                        onChange={(size) => onCurrent({ ...current, size })}
                    />
                )}
            </div>
            <CtSection title="Иконка">
                <div className="grid gap-1.5">
                    <div className="flex items-center justify-between">
                        <CtLabel htmlFor="launcher-icon">Стиль иконки</CtLabel>
                        <CtCheckbox
                            label="Текст вместо иконки"
                            checked={current.text_instead_of_icon}
                            onCheckedChange={(text_instead_of_icon) =>
                                onCurrent({ ...current, text_instead_of_icon })
                            }
                        />
                    </div>
                    <CtSelect
                        id="launcher-icon"
                        value={current.icon}
                        options={iconOptions}
                        ariaLabel="Стиль иконки"
                        onChange={(icon) =>
                            onCurrent({
                                ...current,
                                icon: icon as LauncherIcon,
                            })
                        }
                    />
                </div>
                {current.text_instead_of_icon && (
                    <CtField label="Текст на кнопке" htmlFor="launcher-text">
                        <CtInput
                            id="launcher-text"
                            maxLength={40}
                            value={current.text}
                            onChange={(event) =>
                                onCurrent({
                                    ...current,
                                    text: event.target.value,
                                })
                            }
                        />
                    </CtField>
                )}
                <CtCheckbox
                    label="Автоматический размер иконок"
                    checked={current.auto_icon_size}
                    onCheckedChange={(auto_icon_size) =>
                        onCurrent({ ...current, auto_icon_size })
                    }
                />
                <CtField
                    label={
                        device === 'desktop'
                            ? 'Размер для десктопа'
                            : 'Размер для телефона'
                    }
                    className="w-40"
                >
                    <CtNumber
                        value={current.icon_size}
                        min={12}
                        max={64}
                        suffix="px"
                        disabled={current.auto_icon_size}
                        ariaLabel="Размер иконки"
                        onChange={(icon_size) =>
                            onCurrent({ ...current, icon_size })
                        }
                    />
                </CtField>
            </CtSection>
            <CtSection title="Анимация кнопки">
                <CtField label="При наведении" htmlFor="launcher-hover">
                    <CtSelect
                        id="launcher-hover"
                        value={current.hover}
                        ariaLabel="При наведении"
                        options={LAUNCHER_HOVER.map(([value, label]) => ({
                            value,
                            label,
                        }))}
                        onChange={(hover) =>
                            onCurrent({
                                ...current,
                                hover: hover as LauncherHover,
                            })
                        }
                    />
                </CtField>
                <div className="flex items-end gap-3">
                    <CtField
                        label="Анимация покоя"
                        htmlFor="launcher-idle"
                        className="flex-1"
                    >
                        <CtSelect
                            id="launcher-idle"
                            value={
                                current.idle === 'bounce'
                                    ? 'none'
                                    : current.idle
                            }
                            ariaLabel="Анимация покоя"
                            options={LAUNCHER_IDLE.map(([value, label]) => ({
                                value,
                                label,
                            }))}
                            onChange={(idle) =>
                                onCurrent({
                                    ...current,
                                    idle: idle as LauncherIdle,
                                })
                            }
                        />
                    </CtField>
                    <CtField label="Интервал" className="w-24">
                        <CtNumber
                            value={current.interval_seconds}
                            min={0}
                            max={60}
                            suffix="сек"
                            slider={false}
                            ariaLabel="Интервал анимации"
                            onChange={(interval_seconds) =>
                                onCurrent({ ...current, interval_seconds })
                            }
                        />
                    </CtField>
                </div>
            </CtSection>
        </div>
    );
}

function DialogPanel({
    device,
    dialog,
    onChange,
}: {
    device: 'desktop' | 'mobile';
    dialog: FloatingLauncher['dialog'];
    onChange: (dialog: FloatingLauncher['dialog']) => void;
}) {
    const style: DialogStyle = device === 'desktop' ? dialog : dialog.mobile;
    const iconOptions = LAUNCHER_ICONS.map(([value, label]) => ({
        value,
        label,
        icon: (
            <span className="inline-flex w-5 justify-center text-[#3DAFCC]">
                <LauncherIconGlyph icon={value} size={14} />
            </span>
        ),
    }));
    const fonts = [{ value: 'default', label: 'По умолчанию' }];

    function shared<K extends 'title' | 'subtitle' | 'cta_label'>(
        key: K,
        value: FloatingLauncher['dialog'][K],
    ) {
        onChange({ ...dialog, [key]: value });
    }

    function timing(key: 'open_delay_seconds' | 'show_seconds', value: number) {
        onChange({ ...dialog, [key]: value });
    }

    function set<K extends keyof DialogStyle>(key: K, value: DialogStyle[K]) {
        onChange(
            device === 'desktop'
                ? { ...dialog, [key]: value }
                : { ...dialog, mobile: { ...dialog.mobile, [key]: value } },
        );
    }

    const fontRow = (
        label: string,
        size: keyof DialogStyle,
        line: keyof DialogStyle,
        limits: { size: [number, number]; line: [number, number] },
    ) => (
        <div className="grid grid-cols-[1fr_88px_88px] gap-3">
            <CtField label="Шрифт">
                <CtSelect
                    value="default"
                    options={fonts}
                    ariaLabel={`Шрифт: ${label}`}
                    onChange={() => undefined}
                />
            </CtField>
            <NumberField
                label="Размер"
                value={style[size] as number}
                min={limits.size[0]}
                max={limits.size[1]}
                suffix=""
                onChange={(value) => set(size, value as never)}
            />
            <NumberField
                label="Строка"
                value={style[line] as number}
                min={limits.line[0]}
                max={limits.line[1]}
                suffix=""
                onChange={(value) => set(line, value as never)}
            />
        </div>
    );

    return (
        <div className="pb-4">
            <div className="grid grid-cols-2 gap-3 pb-5">
                <NumberField
                    label="Время до открытия"
                    help
                    value={dialog.open_delay_seconds}
                    min={0}
                    max={120}
                    suffix=""
                    onChange={(value) => timing('open_delay_seconds', value)}
                />
                <NumberField
                    label="Время показа"
                    help
                    value={dialog.show_seconds}
                    min={1}
                    max={30}
                    suffix=""
                    onChange={(value) => timing('show_seconds', value)}
                />
            </div>
            <CtSection title="Заголовок">
                <CtField label="Текст заголовка" htmlFor="dialog-title">
                    <CtTextarea
                        id="dialog-title"
                        rows={3}
                        value={dialog.title}
                        maxLength={120}
                        onChange={(event) =>
                            shared('title', event.target.value)
                        }
                    />
                </CtField>
                {fontRow('заголовок', 'title_size', 'title_line', {
                    size: [10, 48],
                    line: [10, 64],
                })}
            </CtSection>
            <CtSection title="Подзаголовок">
                <CtField label="Текст подзаголовка" htmlFor="dialog-subtitle">
                    <CtTextarea
                        id="dialog-subtitle"
                        rows={3}
                        value={dialog.subtitle}
                        maxLength={200}
                        onChange={(event) =>
                            shared('subtitle', event.target.value)
                        }
                    />
                </CtField>
                {fontRow('подзаголовок', 'subtitle_size', 'subtitle_line', {
                    size: [10, 32],
                    line: [10, 48],
                })}
            </CtSection>
            <section className="grid gap-4 border-t border-[#EEEEEE] py-5">
                <div className="flex items-center justify-between">
                    <h3 className="text-base font-semibold text-[#1F282C]">
                        Кнопка
                    </h3>
                    <CtSwitch
                        checked={style.button_enabled}
                        label="Показывать кнопку"
                        onChange={(value) => set('button_enabled', value)}
                    />
                </div>
                {style.button_enabled && (
                    <>
                        <CtField label="Стиль иконки">
                            <CtSelect
                                value={style.cta_icon}
                                options={iconOptions}
                                ariaLabel="Стиль иконки на кнопке диалога"
                                onChange={(value) =>
                                    set('cta_icon', value as LauncherIcon)
                                }
                            />
                        </CtField>
                        <CtField label="Текст на кнопке" htmlFor="dialog-cta">
                            <CtInput
                                id="dialog-cta"
                                value={dialog.cta_label}
                                maxLength={40}
                                onChange={(event) =>
                                    shared('cta_label', event.target.value)
                                }
                            />
                        </CtField>
                        {fontRow('кнопка', 'cta_size', 'cta_line', {
                            size: [10, 32],
                            line: [10, 48],
                        })}
                    </>
                )}
            </section>
            <CtSection title="Внутренние отступы">
                <div className="grid grid-cols-2 gap-3">
                    {(
                        [
                            ['pad_left', 'Слева'],
                            ['pad_right', 'Справа'],
                            ['pad_top', 'Сверху'],
                            ['pad_bottom', 'Снизу'],
                        ] as const
                    ).map(([key, label]) => (
                        <NumberField
                            key={key}
                            label={label}
                            value={style[key]}
                            min={0}
                            max={80}
                            onChange={(value) => set(key, value)}
                        />
                    ))}
                </div>
            </CtSection>
        </div>
    );
}
