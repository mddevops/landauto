import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { Choice } from '@/components/platform/form-fields';
import {
    BlockToggle,
    CornerIcon,
    CtButton,
    CtCheckbox,
    CtColor,
    CtField,
    CtIconChoice,
    CtInput,
    CtNumber,
    CtSection,
    CtSelect,
    LabeledSelect,
    LockToggle,
    NumberField,
    RadiusIcon,
} from '@/components/widget/ct';
import { ILLUSTRATIONS } from '@/components/widget/illustrations';
import { cn } from '@/lib/utils';
import type {
    CanvasBlock,
    ConfirmBlock,
    PopupAppearance,
    PopupCanvas,
    PopupLaunch,
    ShowScenario,
} from '@/popups/launch';

export const adjustable: { key: CanvasBlock; label: string }[] = [
    { key: 'logo', label: 'Логотип' },
    { key: 'title', label: 'Заголовок' },
    { key: 'subtitle', label: 'Подзаголовок' },
    { key: 'name', label: 'Имя' },
    { key: 'phone', label: 'Телефон' },
    { key: 'email', label: 'E-mail' },
    { key: 'comment', label: 'Комментарий' },
    { key: 'call_time', label: 'Выбор времени звонка' },
    { key: 'department', label: 'Выбор отдела' },
    { key: 'button', label: 'Кнопка' },
    { key: 'consent', label: 'Согласие' },
    { key: 'copyright', label: 'Копирайт' },
];

export const staticBlocks: { key: CanvasBlock; label: string }[] = [
    { key: 'image', label: 'Иллюстрация на форме' },
    { key: 'backdrop', label: 'Задний план' },
    { key: 'close', label: 'Кнопка закрытия' },
    { key: 'ad_label', label: 'Маркировка рекламы' },
    { key: 'promo_timer', label: 'Таймер акции' },
];

export const blockNames: Record<string, string> = Object.fromEntries(
    [...adjustable, ...staticBlocks].map((block) => [block.key, block.label]),
);

export const lockedBlocks = new Set<CanvasBlock>(['phone', 'button']);

const presets = [
    ['', 'Выберите пресет'],
    ['standard', 'Стандартный'],
    ['minimal', 'Минималистичный'],
    ['round', 'Круглый'],
    ['square', 'Квадратный'],
    ['vertical', 'Прямоугольный Вертикальный'],
    ['horizontal', 'Прямоугольный Горизонтальный'],
    ['horizontal_image', 'Горизонтальный с картинкой'],
    ['image_background', 'С картинкой и фоном'],
] as const;

const formPositions = [
    { value: 'left', label: 'Слева по центру', x: 1, y: 6 },
    { value: 'bottom-left', label: 'Снизу слева', x: 1, y: 10 },
    { value: 'center', label: 'По центру', x: 5, y: 6 },
    { value: 'bottom-right', label: 'Снизу справа', x: 10, y: 10 },
    { value: 'bottom-center', label: 'Снизу по центру', x: 5, y: 10 },
] as const;

function PositionIcon({ x, y }: { x: number; y: number }) {
    return (
        <svg viewBox="0 0 16 16" className="size-4" aria-hidden="true">
            <rect
                x="1.5"
                y="1.5"
                width="13"
                height="13"
                fill="none"
                stroke="currentColor"
                strokeWidth="1"
                opacity=".55"
            />
            <rect x={x} y={y} width="5" height="4.5" fill="currentColor" />
        </svg>
    );
}

export function DataPanel({
    formId,
    forms,
    launch,
    device,
    mobileFullscreen,
    onForm,
    onFullscreen,
    onLaunch,
    onPreset,
    onToggle,
}: {
    formId: string;
    forms: Choice[];
    launch: PopupLaunch;
    device: 'desktop' | 'mobile';
    mobileFullscreen: boolean;
    onForm: (value: string) => void;
    onFullscreen: (value: boolean) => void;
    onLaunch: (launch: PopupLaunch) => void;
    onPreset: (preset: string) => void;
    onToggle: (key: CanvasBlock) => void;
}) {
    const canvas = launch.canvas;
    const appearance =
        device === 'desktop' ? canvas.appearance : canvas.appearance_mobile;
    const blocks =
        device === 'desktop' ? canvas.desktop_blocks : canvas.mobile_blocks;
    const scenario =
        device === 'desktop' ? canvas.desktop_scenario : canvas.mobile_scenario;

    function setCanvas(next: PopupCanvas, extra: Partial<PopupLaunch> = {}) {
        onLaunch({ ...launch, ...extra, canvas: next });
    }

    function setAppearance(patch: Partial<PopupAppearance>) {
        setCanvas({
            ...canvas,
            [device === 'desktop' ? 'appearance' : 'appearance_mobile']: {
                ...appearance,
                ...patch,
            },
        });
    }

    function setScenario(value: ShowScenario) {
        const field =
            device === 'desktop' ? 'desktop_scenario' : 'mobile_scenario';
        const extra: Partial<PopupLaunch> =
            device === 'desktop' ? { auto_show: value } : {};

        if (
            device === 'desktop' &&
            (value === 'once_session' || value === 'periodic') &&
            launch.delay_seconds === 8
        ) {
            extra.delay_seconds = 25;
        }

        if (value === 'custom' && launch.shows.length === 0) {
            extra.shows = [{ delay_seconds: 30 }];
        }

        setCanvas({ ...canvas, [field]: value }, extra);
    }

    const shownAdjustable =
        device === 'mobile'
            ? adjustable.filter(
                  (block) => block.key !== 'logo' && block.key !== 'title',
              )
            : adjustable;

    return (
        <div className="pb-4">
            <div className="grid gap-4 pb-5">
                <LabeledSelect
                    label="Сценарий показа формы"
                    help
                    value={scenario}
                    onChange={(value) => setScenario(value as ShowScenario)}
                >
                    <option value="auto">Расчёт времени автоматически</option>
                    <option value="once_session">
                        Показ формы один раз за сессию
                    </option>
                    <option value="periodic">
                        Периодический показ формы за сессию
                    </option>
                    <option value="custom">
                        Индивидуальный показ формы за сессию
                    </option>
                    <option value="off">
                        Отключение автоматического показа формы
                    </option>
                </LabeledSelect>
                {(scenario === 'once_session' || scenario === 'periodic') && (
                    <CtField
                        label={
                            scenario === 'once_session'
                                ? 'Показать форму через'
                                : 'Показывать форму каждые'
                        }
                        help
                        className="w-52"
                    >
                        <CtNumber
                            value={
                                scenario === 'periodic'
                                    ? launch.period_seconds
                                    : launch.delay_seconds
                            }
                            min={scenario === 'periodic' ? 10 : 0}
                            max={300}
                            suffix="сек"
                            ariaLabel={
                                scenario === 'once_session'
                                    ? 'Показать форму через'
                                    : 'Показывать форму каждые'
                            }
                            onChange={(value) =>
                                onLaunch({
                                    ...launch,
                                    [scenario === 'periodic'
                                        ? 'period_seconds'
                                        : 'delay_seconds']: value,
                                })
                            }
                        />
                    </CtField>
                )}
                {scenario === 'custom' && (
                    <div className="grid gap-2">
                        <p className="text-sm text-[#746A5D]">
                            Номер показа / Время ожидания до показа
                        </p>
                        {launch.shows.map((show, index) => (
                            <div
                                key={index}
                                className="flex items-center gap-2"
                            >
                                <span className="w-16 shrink-0 text-sm">
                                    {index + 1}-й показ
                                </span>
                                <CtNumber
                                    className="w-28"
                                    value={show.delay_seconds}
                                    min={0}
                                    max={300}
                                    suffix="сек"
                                    ariaLabel={`${index + 1}-й показ`}
                                    onChange={(value) =>
                                        onLaunch({
                                            ...launch,
                                            shows: launch.shows.map(
                                                (item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              delay_seconds:
                                                                  value,
                                                          }
                                                        : item,
                                            ),
                                        })
                                    }
                                />
                                <button
                                    type="button"
                                    aria-label="Удалить показ"
                                    className="text-[#C7C9CA] hover:text-[#8F9496]"
                                    onClick={() =>
                                        onLaunch({
                                            ...launch,
                                            shows: launch.shows.filter(
                                                (_, itemIndex) =>
                                                    itemIndex !== index,
                                            ),
                                        })
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        ))}
                        <div>
                            <CtButton
                                tone="sand"
                                onClick={() =>
                                    onLaunch({
                                        ...launch,
                                        shows: [
                                            ...launch.shows,
                                            { delay_seconds: 30 },
                                        ],
                                    })
                                }
                            >
                                + Добавить показ
                            </CtButton>
                        </div>
                    </div>
                )}
                <CtField label="Пресет" help>
                    <CtSelect
                        value={canvas.preset}
                        ariaLabel="Пресет"
                        placeholder="Выберите пресет"
                        options={presets
                            .filter(([value]) => value !== '')
                            .map(([value, label]) => ({ value, label }))}
                        onChange={onPreset}
                    />
                </CtField>
                <LabeledSelect
                    id="popup-form"
                    label="Форма заявки"
                    value={formId}
                    onChange={onForm}
                >
                    <option value="">Без формы</option>
                    {forms.map((choice) => (
                        <option key={choice.value} value={choice.value}>
                            {choice.label}
                        </option>
                    ))}
                </LabeledSelect>
            </div>
            <CtSection title="Элементы на форме" help>
                <p className="-mb-2 pl-6 text-sm text-[#746A5D]">
                    Регулируемые блоки
                </p>
                <div className="grid gap-3 pl-6">
                    {shownAdjustable.map((block) => (
                        <BlockToggle
                            key={block.key}
                            label={block.label}
                            checked={blocks[block.key]}
                            disabled={lockedBlocks.has(block.key)}
                            onChange={() => onToggle(block.key)}
                        />
                    ))}
                </div>
                <p className="-mb-2 pl-6 text-sm text-[#746A5D]">Статичные</p>
                <div className="grid gap-3 pl-6">
                    {staticBlocks.map((block) => (
                        <BlockToggle
                            key={block.key}
                            label={block.label}
                            checked={blocks[block.key]}
                            onChange={() => onToggle(block.key)}
                        />
                    ))}
                </div>
                <div>
                    <CtButton
                        tone="brown"
                        disabled
                        title="Свои блоки на форму пока добавить нельзя"
                    >
                        + Добавить блок
                    </CtButton>
                </div>
            </CtSection>
            <CtSection title="Внешний вид">
                {device === 'desktop' ? (
                    <CtField label="Расположение формы на экране">
                        <CtIconChoice
                            label="Расположение формы на экране"
                            value={canvas.position}
                            onChange={(position) =>
                                setCanvas({ ...canvas, position })
                            }
                            items={formPositions.map((item) => ({
                                value: item.value,
                                label: item.label,
                                icon: <PositionIcon x={item.x} y={item.y} />,
                            }))}
                        />
                    </CtField>
                ) : (
                    <LabeledSelect
                        label="Расположение формы на экране"
                        value={mobileFullscreen ? 'fullscreen' : 'bottom'}
                        onChange={(value) =>
                            onFullscreen(value === 'fullscreen')
                        }
                    >
                        <option value="bottom">Снизу экрана</option>
                        <option value="fullscreen">На весь экран</option>
                    </LabeledSelect>
                )}
                <div className="grid grid-cols-2 gap-3">
                    <NumberField
                        label="Ширина формы"
                        value={canvas.width}
                        min={240}
                        max={640}
                        onChange={(width) => setCanvas({ ...canvas, width })}
                    />
                    <NumberField
                        label="Высота формы"
                        value={appearance.height}
                        min={80}
                        max={900}
                        badge={appearance.height_auto ? 'Авто' : undefined}
                        onBadge={() =>
                            setAppearance({
                                height_auto: !appearance.height_auto,
                            })
                        }
                        onChange={(height) =>
                            setAppearance({ height, height_auto: false })
                        }
                    />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-2">
                        <NumberField
                            label="Скругление формы"
                            value={canvas.radius}
                            min={0}
                            max={40}
                            onChange={(radius) =>
                                setCanvas({ ...canvas, radius })
                            }
                        />
                        <RadiusPresets
                            label="Форма углов формы"
                            value={canvas.radius}
                            onChange={(radius) =>
                                setCanvas({ ...canvas, radius })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <NumberField
                            label="Скругление элементов"
                            value={canvas.element_radius}
                            min={0}
                            max={40}
                            onChange={(element_radius) =>
                                setCanvas({ ...canvas, element_radius })
                            }
                        />
                        <RadiusPresets
                            label="Форма углов элементов"
                            value={canvas.element_radius}
                            onChange={(element_radius) =>
                                setCanvas({ ...canvas, element_radius })
                            }
                        />
                    </div>
                </div>
            </CtSection>
            <CtSection title="Фон">
                <CtCheckbox
                    label="Использовать градиент фона"
                    checked={appearance.gradient}
                    onCheckedChange={(gradient) => setAppearance({ gradient })}
                />
                <CtField label="Цвет фона">
                    <CtColor
                        label="Цвет фона"
                        color={appearance.color_from}
                        opacity={appearance.opacity_from}
                        onColor={(color_from) => setAppearance({ color_from })}
                        onOpacity={(opacity_from) =>
                            setAppearance({ opacity_from })
                        }
                    />
                </CtField>
                {appearance.gradient && (
                    <>
                        <CtField label="Второй цвет градиента">
                            <CtColor
                                label="Второй цвет градиента"
                                color={appearance.color_to}
                                opacity={appearance.opacity_to}
                                onColor={(color_to) =>
                                    setAppearance({ color_to })
                                }
                                onOpacity={(opacity_to) =>
                                    setAppearance({ opacity_to })
                                }
                            />
                        </CtField>
                        <NumberField
                            label="Угол градиента"
                            value={appearance.angle}
                            min={0}
                            max={360}
                            suffix="°"
                            onChange={(angle) => setAppearance({ angle })}
                        />
                    </>
                )}
                <CtField label="Цвет обводки">
                    <CtColor
                        label="Цвет обводки"
                        color={appearance.border_color}
                        opacity={appearance.border_opacity}
                        onColor={(border_color) =>
                            setAppearance({ border_color })
                        }
                        onOpacity={(border_opacity) =>
                            setAppearance({ border_opacity })
                        }
                    />
                </CtField>
                <NumberField
                    label="Толщина обводки"
                    value={appearance.border_width}
                    min={0}
                    max={20}
                    onChange={(border_width) => setAppearance({ border_width })}
                />
                <CtField label="Фоновое изображение формы" help>
                    <span
                        className="flex h-8 items-center justify-between border border-[#8F9496] bg-[#F0F0F0] px-2 text-sm text-[#8F9496]"
                        title="Загрузка изображений появится позже"
                    >
                        Загрузите картинку или видео
                    </span>
                </CtField>
            </CtSection>
            <CtSection>
                <CtField label="Цветовая тема" help>
                    <CtSelect
                        value={appearance.theme}
                        ariaLabel="Цветовая тема"
                        options={[
                            { value: 'light', label: 'Светлая тема' },
                            { value: 'dark', label: 'Тёмная тема' },
                        ]}
                        onChange={(theme) =>
                            setAppearance({
                                theme: theme === 'dark' ? 'dark' : 'light',
                            })
                        }
                    />
                </CtField>
                <p className="flex gap-2 text-sm text-[#746A5D]">
                    <span aria-hidden="true" className="text-[#E8A33D]">
                        ▲
                    </span>
                    Некоторые элементы не наследуют цветовую тему, потому что
                    для них заданы индивидуальные настройки цветов
                </p>
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
            <CtSection title="Отступы внутри формы">
                <div className="grid grid-cols-2 gap-x-3 gap-y-4">
                    {(
                        [
                            ['pad_left', 'Левый'],
                            ['pad_right', 'Правый'],
                            ['pad_top', 'Верхний'],
                            ['pad_bottom', 'Нижний'],
                        ] as const
                    ).map(([key, label]) => (
                        <NumberField
                            key={key}
                            label={label}
                            value={appearance[key]}
                            min={0}
                            max={80}
                            onChange={(value) =>
                                setAppearance({ [key]: value })
                            }
                        />
                    ))}
                </div>
            </CtSection>
        </div>
    );
}

function RadiusPresets({
    label,
    value,
    onChange,
}: {
    label: string;
    value: number;
    onChange: (value: number) => void;
}) {
    return (
        <CtIconChoice
            label={label}
            value={value <= 0 ? 'square' : value >= 30 ? 'round' : 'soft'}
            onChange={(kind) =>
                onChange(kind === 'square' ? 0 : kind === 'soft' ? 12 : 40)
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
                    label: 'Сильное скругление',
                    icon: <RadiusIcon kind="round" />,
                },
            ]}
        />
    );
}

export function ConfirmationPanel({
    canvas,
    appearance,
    device,
    onCanvas,
    onAppearance,
}: {
    canvas: PopupCanvas;
    appearance: PopupAppearance;
    device: 'desktop' | 'mobile';
    onCanvas: (canvas: PopupCanvas) => void;
    onAppearance: (appearance: PopupAppearance) => void;
}) {
    const blocks = canvas.confirm_blocks;
    const rows: { key: ConfirmBlock; label: string }[] =
        canvas.confirmation_kind === 'notice'
            ? [
                  { key: 'icon', label: 'Иконка' },
                  { key: 'timer', label: 'Таймер' },
                  { key: 'title', label: 'Заголовок' },
                  { key: 'subtitle', label: 'Подзаголовок' },
                  { key: 'close', label: 'Кнопка закрытия' },
              ]
            : [
                  { key: 'icon', label: 'Иконка' },
                  { key: 'timer', label: 'Таймер' },
                  { key: 'title', label: 'Заголовок' },
                  { key: 'subtitle', label: 'Подзаголовок' },
                  { key: 'logo', label: 'Логотип' },
                  { key: 'button', label: 'Кнопка' },
                  { key: 'copyright', label: 'Копирайт' },
                  { key: 'image', label: 'Иллюстрация на форме' },
                  { key: 'background', label: 'Задний план' },
                  { key: 'close', label: 'Кнопка закрытия' },
              ];

    return (
        <div className="pb-4">
            <div className="grid gap-4 pb-5">
                {device === 'desktop' && (
                    <LabeledSelect
                        label="Вид подтверждения"
                        value={canvas.confirmation_kind}
                        onChange={(confirmation_kind) =>
                            onCanvas({
                                ...canvas,
                                confirmation_kind:
                                    confirmation_kind === 'replace'
                                        ? 'replace'
                                        : 'notice',
                            })
                        }
                    >
                        <option value="notice">Уведомление</option>
                        <option value="replace">На форме</option>
                    </LabeledSelect>
                )}
                <LabeledSelect
                    label="Сценарий закрытия формы"
                    value={canvas.close_scenario}
                    onChange={(close_scenario) =>
                        onCanvas({
                            ...canvas,
                            close_scenario:
                                close_scenario === 'manual' ? 'manual' : 'auto',
                        })
                    }
                >
                    <option value="auto">Автоматически</option>
                    <option value="manual">Вручную</option>
                </LabeledSelect>
                {canvas.close_scenario === 'auto' && (
                    <NumberField
                        label="Закрывать форму через"
                        value={canvas.close_after_seconds}
                        min={1}
                        max={30}
                        suffix="сек"
                        onChange={(close_after_seconds) =>
                            onCanvas({ ...canvas, close_after_seconds })
                        }
                    />
                )}
            </div>
            <CtSection title="Элементы на форме" help>
                <div className="grid gap-3 pl-6">
                    {rows.map((block) => (
                        <BlockToggle
                            key={block.key}
                            label={block.label}
                            checked={blocks[block.key]}
                            disabled={block.key === 'close'}
                            onChange={() =>
                                onCanvas({
                                    ...canvas,
                                    confirm_blocks: {
                                        ...blocks,
                                        [block.key]: !blocks[block.key],
                                    },
                                })
                            }
                        />
                    ))}
                </div>
            </CtSection>
            <CtSection title="Внешний вид">
                <div className="grid grid-cols-2 gap-3">
                    <NumberField
                        label="Ширина"
                        value={appearance.confirm_width}
                        min={180}
                        max={640}
                        onChange={(confirm_width) =>
                            onAppearance({ ...appearance, confirm_width })
                        }
                    />
                    <NumberField
                        label="Высота"
                        value={appearance.confirm_height}
                        min={80}
                        max={800}
                        badge={
                            appearance.confirm_height_auto ? 'Авто' : undefined
                        }
                        onBadge={() =>
                            onAppearance({
                                ...appearance,
                                confirm_height_auto:
                                    !appearance.confirm_height_auto,
                            })
                        }
                        onChange={(confirm_height) =>
                            onAppearance({
                                ...appearance,
                                confirm_height,
                                confirm_height_auto: false,
                            })
                        }
                    />
                </div>
                <NumberField
                    label="Скругление формы"
                    value={appearance.confirm_radius}
                    min={0}
                    max={40}
                    onChange={(confirm_radius) =>
                        onAppearance({ ...appearance, confirm_radius })
                    }
                />
            </CtSection>
        </div>
    );
}

const alignChoices = [
    { value: 'left', label: 'Слева' },
    { value: 'center', label: 'По центру' },
    { value: 'right', label: 'Справа' },
] as const;

function AlignIcon({ side }: { side: 'left' | 'center' | 'right' }) {
    return (
        <svg viewBox="0 0 16 16" className="size-4" aria-hidden="true">
            <rect
                x={side === 'left' ? 1 : side === 'center' ? 5.5 : 10}
                y="3"
                width="5"
                height="10"
                fill="currentColor"
            />
        </svg>
    );
}

function CornerChoice({
    label,
    value,
    onChange,
}: {
    label: string;
    value: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';
    onChange: (
        value: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right',
    ) => void;
}) {
    return (
        <CtIconChoice
            label={label}
            value={value}
            onChange={onChange}
            items={(
                [
                    ['top-left', 'Сверху слева'],
                    ['top-right', 'Сверху справа'],
                    ['bottom-left', 'Снизу слева'],
                    ['bottom-right', 'Снизу справа'],
                ] as const
            ).map(([corner, text]) => ({
                value: corner,
                label: text,
                icon: <CornerIcon corner={corner} />,
            }))}
        />
    );
}

export function ElementPanel({
    selected,
    title,
    text,
    appearance,
    onTitle,
    onText,
    onAppearance,
}: {
    selected: string;
    title: string;
    text: string;
    appearance: PopupAppearance;
    onTitle: (value: string) => void;
    onText: (value: string) => void;
    onAppearance: (appearance: PopupAppearance) => void;
}) {
    const [locked, setLocked] = useState({ image: true, backdrop: true });
    const patch = (next: Partial<PopupAppearance>) =>
        onAppearance({ ...appearance, ...next });

    if (selected === 'title') {
        return (
            <div className="grid gap-4 pb-4">
                <CtField label="Текст заголовка" htmlFor="el-title">
                    <CtInput
                        id="el-title"
                        value={title}
                        maxLength={160}
                        onChange={(event) => onTitle(event.target.value)}
                    />
                </CtField>
                <CtSection title="Настройки" first>
                    <NumberField
                        label="Размер"
                        value={appearance.title_size}
                        min={10}
                        max={48}
                        onChange={(title_size) => patch({ title_size })}
                    />
                    <CtField label="Цвет шрифта">
                        <CtColor
                            label="Цвет шрифта"
                            color={appearance.title_color}
                            showOpacity={false}
                            onColor={(title_color) => patch({ title_color })}
                        />
                    </CtField>
                </CtSection>
                <CtSection title="Выравнивание в блоке">
                    <CtIconChoice
                        label="Выравнивание в блоке"
                        value={appearance.title_align}
                        onChange={(title_align) => patch({ title_align })}
                        items={alignChoices.map((item) => ({
                            value: item.value,
                            label: item.label,
                            icon: <AlignIcon side={item.value} />,
                        }))}
                    />
                </CtSection>
            </div>
        );
    }

    if (selected === 'subtitle') {
        return (
            <div className="grid gap-4 pb-4">
                <CtField label="Текст подзаголовка" htmlFor="el-text">
                    <CtInput
                        id="el-text"
                        value={text}
                        maxLength={2000}
                        onChange={(event) => onText(event.target.value)}
                    />
                </CtField>
                <NumberField
                    label="Размер"
                    value={appearance.subtitle_size}
                    min={10}
                    max={32}
                    onChange={(subtitle_size) => patch({ subtitle_size })}
                />
            </div>
        );
    }

    if (selected === 'button') {
        return (
            <div className="grid gap-4 pb-4">
                <CtField label="Текст на кнопке" htmlFor="el-button">
                    <CtInput
                        id="el-button"
                        value={appearance.button_label}
                        maxLength={40}
                        onChange={(event) =>
                            patch({ button_label: event.target.value })
                        }
                    />
                </CtField>
                <NumberField
                    label="Высота кнопки"
                    value={appearance.button_height}
                    min={24}
                    max={80}
                    onChange={(button_height) => patch({ button_height })}
                />
                <CtField label="Цвет фона">
                    <CtColor
                        label="Цвет фона кнопки"
                        color={appearance.button_color}
                        showOpacity={false}
                        onColor={(button_color) => patch({ button_color })}
                    />
                </CtField>
            </div>
        );
    }

    if (selected === 'call_time') {
        const row = appearance.call_time_variant === 'row';

        return (
            <div className="grid gap-4 pb-4">
                <CtField label="Вариант блока" htmlFor="el-call-variant">
                    <CtSelect
                        id="el-call-variant"
                        value={appearance.call_time_variant}
                        ariaLabel="Вариант блока"
                        options={[
                            { value: 'bullets', label: 'Буллеты' },
                            { value: 'row', label: 'Строка' },
                        ]}
                        onChange={(call_time_variant) =>
                            patch({
                                call_time_variant:
                                    call_time_variant === 'row'
                                        ? 'row'
                                        : 'bullets',
                            })
                        }
                    />
                </CtField>
                <CtSection title="Настройки">
                    <div className="grid grid-cols-2 gap-3">
                        <NumberField
                            label="Размер"
                            value={appearance.call_time_size}
                            min={8}
                            max={32}
                            onChange={(call_time_size) =>
                                patch({ call_time_size })
                            }
                        />
                        <NumberField
                            label="Строка"
                            value={appearance.call_time_line}
                            min={8}
                            max={48}
                            onChange={(call_time_line) =>
                                patch({ call_time_line })
                            }
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <CtField label="Цвет шрифта">
                            <CtColor
                                label="Цвет шрифта"
                                color={appearance.call_time_color}
                                showOpacity={false}
                                onColor={(call_time_color) =>
                                    patch({ call_time_color })
                                }
                            />
                        </CtField>
                        <CtField label={row ? 'Цвет ссылки' : 'Цвет буллетов'}>
                            <CtColor
                                label={row ? 'Цвет ссылки' : 'Цвет буллетов'}
                                color={
                                    row
                                        ? appearance.call_time_link_color
                                        : appearance.call_time_bullet_color
                                }
                                showOpacity={false}
                                onColor={(color) =>
                                    patch(
                                        row
                                            ? { call_time_link_color: color }
                                            : { call_time_bullet_color: color },
                                    )
                                }
                            />
                        </CtField>
                    </div>
                </CtSection>
                <CtSection title="Выравнивание в блоке">
                    <CtIconChoice
                        label="Выравнивание в блоке"
                        value={appearance.call_time_align}
                        onChange={(call_time_align) =>
                            patch({ call_time_align })
                        }
                        items={alignChoices.map((item) => ({
                            value: item.value,
                            label: item.label,
                            icon: <AlignIcon side={item.value} />,
                        }))}
                    />
                </CtSection>
            </div>
        );
    }

    if (selected === 'image') {
        const ratio =
            appearance.illustration_height > 0
                ? appearance.illustration_width / appearance.illustration_height
                : 1;

        return (
            <div className="grid gap-4 pb-4">
                <CtField label="Иллюстрация" htmlFor="el-illustration">
                    <CtSelect
                        id="el-illustration"
                        value={appearance.illustration_kind}
                        ariaLabel="Иллюстрация"
                        options={[...ILLUSTRATIONS].map((item) => ({
                            value: item.value,
                            label: item.label,
                        }))}
                        onChange={(illustration_kind) =>
                            patch({
                                illustration_kind:
                                    illustration_kind as PopupAppearance['illustration_kind'],
                            })
                        }
                    />
                </CtField>
                <div className="flex items-start gap-2">
                    <NumberField
                        label="Ширина"
                        value={appearance.illustration_width}
                        min={10}
                        max={600}
                        onChange={(illustration_width) =>
                            patch({
                                illustration_width,
                                ...(locked.image
                                    ? {
                                          illustration_height: Math.max(
                                              10,
                                              Math.round(
                                                  illustration_width / ratio,
                                              ),
                                          ),
                                      }
                                    : {}),
                            })
                        }
                    />
                    <LockToggle
                        locked={locked.image}
                        onChange={(value) =>
                            setLocked({ ...locked, image: value })
                        }
                    />
                    <NumberField
                        label="Высота"
                        value={appearance.illustration_height}
                        min={10}
                        max={600}
                        onChange={(illustration_height) =>
                            patch({
                                illustration_height,
                                ...(locked.image
                                    ? {
                                          illustration_width: Math.max(
                                              10,
                                              Math.round(
                                                  illustration_height * ratio,
                                              ),
                                          ),
                                      }
                                    : {}),
                            })
                        }
                    />
                </div>
                <NumberField
                    label="Непрозрачность"
                    value={appearance.illustration_opacity}
                    min={0}
                    max={100}
                    suffix="%"
                    onChange={(illustration_opacity) =>
                        patch({ illustration_opacity })
                    }
                />
                <CtSection title="Расположение иллюстрации">
                    <CornerChoice
                        label="Расположение иллюстрации"
                        value={appearance.illustration_corner}
                        onChange={(illustration_corner) =>
                            patch({ illustration_corner })
                        }
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <NumberField
                            label="Горизонтальный сдвиг"
                            value={appearance.illustration_x}
                            min={-400}
                            max={400}
                            onChange={(illustration_x) =>
                                patch({ illustration_x })
                            }
                        />
                        <NumberField
                            label="Вертикальный сдвиг"
                            value={appearance.illustration_y}
                            min={-400}
                            max={400}
                            onChange={(illustration_y) =>
                                patch({ illustration_y })
                            }
                        />
                    </div>
                </CtSection>
            </div>
        );
    }

    if (selected === 'backdrop') {
        return (
            <div className="grid gap-4 pb-4">
                <CtField label="Картинка или видео" htmlFor="el-backdrop">
                    <CtSelect
                        id="el-backdrop"
                        value="circle"
                        ariaLabel="Картинка или видео"
                        options={[
                            { value: 'circle', label: 'Круг' },
                            {
                                value: 'upload',
                                label: 'Загрузить файл',
                                disabled: true,
                            },
                        ]}
                        onChange={() => undefined}
                    />
                </CtField>
                <div className="flex items-start gap-2">
                    <NumberField
                        label="Ширина"
                        value={appearance.backdrop_width}
                        min={50}
                        max={900}
                        onChange={(backdrop_width) =>
                            patch({
                                backdrop_width,
                                ...(locked.backdrop
                                    ? { backdrop_height: backdrop_width }
                                    : {}),
                            })
                        }
                    />
                    <LockToggle
                        locked={locked.backdrop}
                        onChange={(value) =>
                            setLocked({ ...locked, backdrop: value })
                        }
                    />
                    <NumberField
                        label="Высота"
                        value={appearance.backdrop_height}
                        min={50}
                        max={900}
                        onChange={(backdrop_height) =>
                            patch({
                                backdrop_height,
                                ...(locked.backdrop
                                    ? { backdrop_width: backdrop_height }
                                    : {}),
                            })
                        }
                    />
                </div>
                <CtCheckbox
                    label="Использовать градиент фона"
                    checked={appearance.backdrop_gradient}
                    onCheckedChange={(backdrop_gradient) =>
                        patch({ backdrop_gradient })
                    }
                />
                <div className="grid gap-1.5">
                    <p className="text-sm">Цвет градиента</p>
                    <div className="grid grid-cols-2 gap-3">
                        <CtColor
                            label="Первый цвет"
                            color={appearance.backdrop_from}
                            opacity={appearance.backdrop_from_opacity}
                            onColor={(backdrop_from) =>
                                patch({ backdrop_from })
                            }
                            onOpacity={(backdrop_from_opacity) =>
                                patch({ backdrop_from_opacity })
                            }
                        />
                        <CtColor
                            label="Второй цвет"
                            color={appearance.backdrop_to}
                            opacity={appearance.backdrop_to_opacity}
                            onColor={(backdrop_to) => patch({ backdrop_to })}
                            onOpacity={(backdrop_to_opacity) =>
                                patch({ backdrop_to_opacity })
                            }
                        />
                    </div>
                </div>
                <div className="flex items-end gap-3">
                    <span
                        className="block h-8 flex-1"
                        style={{
                            background: `linear-gradient(90deg, ${appearance.backdrop_to}, ${appearance.backdrop_from})`,
                        }}
                    />
                    <CtNumber
                        className="w-24"
                        value={appearance.backdrop_angle}
                        min={0}
                        max={360}
                        suffix="°"
                        ariaLabel="Угол градиента заднего плана"
                        onChange={(backdrop_angle) => patch({ backdrop_angle })}
                    />
                </div>
                <CtSection title="Расположение заднего плана">
                    <CornerChoice
                        label="Расположение заднего плана"
                        value={appearance.backdrop_corner}
                        onChange={(backdrop_corner) =>
                            patch({ backdrop_corner })
                        }
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <NumberField
                            label="Горизонтальный сдвиг"
                            value={appearance.backdrop_x}
                            min={-600}
                            max={600}
                            onChange={(backdrop_x) => patch({ backdrop_x })}
                        />
                        <NumberField
                            label="Вертикальный сдвиг"
                            value={appearance.backdrop_y}
                            min={-600}
                            max={600}
                            onChange={(backdrop_y) => patch({ backdrop_y })}
                        />
                    </div>
                </CtSection>
            </div>
        );
    }

    return (
        <p
            className={cn(
                'pb-4 text-sm text-[#746A5D]',
                selected === 'phone' && 'max-w-xs',
            )}
        >
            {selected === 'phone'
                ? 'Поле телефона обязательно и берётся из формы заявки.'
                : 'Блок показан на форме. Текст согласия хранится вместе с формой заявки.'}
        </p>
    );
}
