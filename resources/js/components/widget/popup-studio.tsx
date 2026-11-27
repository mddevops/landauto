import { router, useForm } from '@inertiajs/react';
import { Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import type { Choice } from '@/components/platform/form-fields';
import type { ManagedPopup } from '@/components/popups/popup-settings-dialog';
import { ElementSwitcher, PanelHead } from '@/components/widget/ct';
import { CtButton } from '@/components/widget/ct';
import {
    BlurredSite,
    ConfirmCard,
    FormCard,
} from '@/components/widget/popup-canvas';
import {
    ConditionsAddButton,
    ConditionsScreen,
} from '@/components/widget/popup-conditions';
import {
    ConfirmationPanel,
    DataPanel,
    ElementPanel,
    adjustable,
    lockedBlocks,
    staticBlocks,
} from '@/components/widget/popup-panels';
import {
    DeviceToggle,
    PhoneMock,
    usePhoneView,
    SiteMock,
    StudioFrame,
} from '@/components/widget/widget-frame';
import { cn } from '@/lib/utils';
import {
    defaultAppearance,
    defaultLaunch,
    defaultRule,
    screenshotCanvas,
} from '@/popups/launch';
import type {
    CanvasBlock,
    PopupAppearance,
    PopupCanvas,
    PopupLaunch,
} from '@/popups/launch';
import { destroy, store, update } from '@/routes/sites/popups';

function completeLaunch(launch?: PopupLaunch, fresh = false): PopupLaunch {
    const base = defaultLaunch();
    const source = fresh ? screenshotCanvas() : launch?.canvas;

    return {
        ...base,
        ...launch,
        conditions: { ...base.conditions, ...launch?.conditions },
        canvas: {
            ...base.canvas,
            ...source,
            desktop_blocks: {
                ...base.canvas.desktop_blocks,
                ...source?.desktop_blocks,
            },
            mobile_blocks: {
                ...base.canvas.mobile_blocks,
                ...source?.mobile_blocks,
            },
            confirm_blocks: {
                ...base.canvas.confirm_blocks,
                ...source?.confirm_blocks,
            },
            appearance: { ...defaultAppearance(), ...source?.appearance },
            appearance_mobile: {
                ...defaultAppearance(),
                ...source?.appearance_mobile,
            },
        },
    };
}

const confirmNames: Record<string, string> = {
    icon: 'Иконка',
    timer: 'Таймер',
    title: 'Заголовок',
    subtitle: 'Подзаголовок',
    logo: 'Логотип',
    close: 'Кнопка закрытия',
};

type Pane = 'data' | 'confirmation';

export function PopupStudio({
    sitePublicId,
    popup,
    forms,
    onBack,
}: {
    sitePublicId: string;
    popup?: ManagedPopup;
    forms: Choice[];
    onBack: () => void;
}) {
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const [tab, setTab] = useState<'form' | 'conditions'>('form');
    const [pane, setPane] = useState<Pane>('data');
    const [selected, setSelected] = useState<string | null>(null);
    const [preview, setPreview] = useState(false);
    const phoneView = usePhoneView();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const form = useForm({
        name: popup?.name ?? 'Обратный звонок',
        title: popup?.title ?? 'Давайте мы вам позвоним',
        text: popup?.text ?? 'Оставьте свой номер, и мы перезвоним вам',
        status: popup && !popup.status ? '0' : '1',
        size: popup?.size ?? 'medium',
        animation: popup?.animation ?? 'fade',
        close_on_overlay: popup?.close_on_overlay ?? true,
        close_on_escape: popup?.close_on_escape ?? true,
        show_close_button: popup?.show_close_button ?? true,
        mobile_fullscreen: popup?.mobile_fullscreen ?? false,
        form: popup?.form_public_id ?? '',
        launch: completeLaunch(popup?.launch, popup === undefined),
    });

    const launch = form.data.launch;
    const canvas = launch.canvas;
    const appearance =
        device === 'desktop' ? canvas.appearance : canvas.appearance_mobile;
    const blocks =
        device === 'desktop' ? canvas.desktop_blocks : canvas.mobile_blocks;

    function setLaunch(next: PopupLaunch) {
        form.setData('launch', next);
    }

    function setCanvas(next: PopupCanvas) {
        setLaunch({
            ...launch,
            auto_show:
                next.desktop_scenario === 'off' ||
                next.desktop_scenario === 'once_session' ||
                next.desktop_scenario === 'periodic' ||
                next.desktop_scenario === 'custom'
                    ? next.desktop_scenario
                    : 'auto',
            canvas: next,
        });
    }

    function setAppearance(next: PopupAppearance) {
        setCanvas({
            ...canvas,
            [device === 'desktop' ? 'appearance' : 'appearance_mobile']: next,
        });
    }

    function toggleBlock(key: CanvasBlock) {
        if (lockedBlocks.has(key)) {
            return;
        }

        const field = device === 'desktop' ? 'desktop_blocks' : 'mobile_blocks';
        setCanvas({
            ...canvas,
            [field]: { ...blocks, [key]: !blocks[key] },
        });
    }

    function resize(axis: 'x' | 'y', delta: number, side: -1 | 1) {
        const growth = delta * side;

        if (axis === 'x') {
            const centered =
                canvas.position === 'center' ||
                canvas.position === 'bottom-center';
            const width = Math.round(
                Math.min(
                    640,
                    Math.max(240, canvas.width + growth * (centered ? 2 : 1)),
                ),
            );
            setCanvas({ ...canvas, width });

            return;
        }

        const height = Math.round(
            Math.min(900, Math.max(80, appearance.height + growth)),
        );
        setAppearance({ ...appearance, height, height_auto: false });
    }

    function applyPreset(preset: string) {
        const standard = screenshotCanvas();
        let next: PopupCanvas = { ...canvas, preset };
        let title = form.data.title;
        let text = form.data.text;

        if (preset === 'standard') {
            next = {
                ...next,
                ...standard,
                preset,
                appearance: defaultAppearance(),
                appearance_mobile: defaultAppearance(),
            };
            title = 'Давайте мы вам позвоним';
            text = 'Оставьте свой номер, и мы перезвоним вам';
        }

        if (preset === 'minimal') {
            title = 'Хотите квартиру?';
            text = '';
            next = {
                ...next,
                radius: 16,
                element_radius: 16,
                desktop_blocks: {
                    ...next.desktop_blocks,
                    subtitle: false,
                    call_time: false,
                },
            };
        }

        if (preset === 'round') {
            next = { ...next, radius: 28, element_radius: 24 };
        }

        if (preset === 'square') {
            next = { ...next, radius: 0, element_radius: 0 };
        }

        if (preset === 'vertical') {
            next = { ...next, width: 320 };
        }

        if (preset === 'horizontal') {
            next = { ...next, width: 560 };
        }

        if (preset === 'horizontal_image' || preset === 'image_background') {
            next = {
                ...next,
                width: preset === 'horizontal_image' ? 560 : 395,
                desktop_blocks: { ...next.desktop_blocks, image: true },
                appearance: {
                    ...next.appearance,
                    gradient: preset === 'image_background',
                },
            };
        }

        form.setData({
            ...form.data,
            title,
            text,
            launch: { ...launch, canvas: next },
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ ...data, status: data.status === '1' }));
        form.submit(
            popup
                ? update({ site: sitePublicId, popup: popup.public_id })
                : store(sitePublicId),
            { preserveScroll: true, onSuccess: onBack },
        );
    }

    function remove() {
        if (!popup) {
            return;
        }

        router.delete(
            destroy.url({ site: sitePublicId, popup: popup.public_id }),
            { preserveScroll: true, onSuccess: onBack },
        );
    }

    const isConfirm = pane === 'confirmation';
    const generalLabel =
        device === 'desktop' ? 'Попап для десктопа' : 'Попап для мобильных';
    const items = [
        { value: 'general', label: generalLabel },
        ...(isConfirm
            ? (Object.keys(canvas.confirm_blocks) as string[])
                  .filter(
                      (key) =>
                          canvas.confirm_blocks[
                              key as keyof typeof canvas.confirm_blocks
                          ],
                  )
                  .map((key) => ({
                      value: key,
                      label: confirmNames[key] ?? key,
                  }))
            : [...adjustable, ...staticBlocks]
                  .filter((block) => blocks[block.key])
                  .map((block) => ({
                      value: block.key as string,
                      label: block.label,
                  }))),
    ];
    const current = selected ?? 'general';

    const formCard = (
        <FormCard
            title={form.data.title}
            text={form.data.text}
            canvas={canvas}
            appearance={appearance}
            blocks={blocks}
            selected={selected}
            onSelect={setSelected}
            preview={preview}
            onResize={resize}
            mobile={device === 'mobile'}
        />
    );
    const card = isConfirm ? (
        <ConfirmCard
            appearance={appearance}
            canvas={canvas}
            blocks={canvas.confirm_blocks}
            kind={canvas.confirmation_kind === 'notice' ? 'notice' : 'replace'}
            selected={selected}
            onSelect={setSelected}
            preview={preview}
        />
    ) : (
        formCard
    );
    const placement = cn(
        'absolute inset-0 flex p-6',
        canvas.position === 'center' && 'items-center justify-center',
        canvas.position === 'left' && 'items-center justify-start',
        canvas.position === 'bottom-right' && 'items-end justify-end',
        canvas.position === 'bottom-left' && 'items-end justify-start',
        canvas.position === 'bottom-center' && 'items-end justify-center',
    );

    return (
        <form onSubmit={submit} className="contents">
            <StudioFrame
                title={form.data.name}
                onTitle={(name) => form.setData('name', name)}
                onBack={onBack}
                tabs={[
                    { id: 'form', label: 'Форма' },
                    { id: 'conditions', label: 'Условия' },
                ]}
                tab={tab}
                onTab={(id) => {
                    setTab(id as 'form' | 'conditions');
                    setSelected(null);
                }}
                enabled={form.data.status === '1'}
                onEnabled={(enabled) =>
                    form.setData('status', enabled ? '1' : '0')
                }
                dirty={form.isDirty}
                saving={form.processing}
                wide={tab === 'conditions'}
                toolbar={
                    tab === 'conditions' &&
                    launch.conditions.groups.length > 0 ? (
                        <ConditionsAddButton
                            onClick={() =>
                                setLaunch({
                                    ...launch,
                                    conditions: {
                                        ...launch.conditions,
                                        groups: [
                                            ...launch.conditions.groups,
                                            {
                                                join: 'or',
                                                rules: [defaultRule()],
                                            },
                                        ],
                                    },
                                })
                            }
                        />
                    ) : undefined
                }
                onDiscard={() => {
                    form.reset();
                    form.clearErrors();
                    setSelected(null);
                }}
                panel={
                    <>
                        <PanelHead
                            caption={
                                current === 'general'
                                    ? 'Общие настройки'
                                    : 'Настройки элемента'
                            }
                            title=""
                            switcher={
                                <ElementSwitcher
                                    value={current}
                                    items={items}
                                    onChange={(value) =>
                                        setSelected(
                                            value === 'general' ? null : value,
                                        )
                                    }
                                />
                            }
                            onClose={
                                selected ? () => setSelected(null) : undefined
                            }
                        />
                        {selected ? (
                            <ElementPanel
                                selected={selected}
                                title={form.data.title}
                                text={form.data.text}
                                appearance={appearance}
                                onTitle={(title) =>
                                    form.setData('title', title)
                                }
                                onText={(text) => form.setData('text', text)}
                                onAppearance={setAppearance}
                            />
                        ) : (
                            <div className="grid gap-4 pb-4">
                                {device === 'mobile' && (
                                    <CtButton
                                        tone="brown"
                                        className="w-full justify-center"
                                        onClick={() =>
                                            setCanvas({
                                                ...canvas,
                                                mobile_scenario:
                                                    canvas.desktop_scenario,
                                                mobile_blocks: {
                                                    ...canvas.desktop_blocks,
                                                },
                                                appearance_mobile: {
                                                    ...canvas.appearance,
                                                },
                                            })
                                        }
                                    >
                                        Скопировать настройки с десктопа
                                    </CtButton>
                                )}
                                {isConfirm ? (
                                    <ConfirmationPanel
                                        canvas={canvas}
                                        appearance={appearance}
                                        device={device}
                                        onCanvas={setCanvas}
                                        onAppearance={setAppearance}
                                    />
                                ) : (
                                    <DataPanel
                                        formId={form.data.form}
                                        forms={forms}
                                        launch={launch}
                                        device={device}
                                        mobileFullscreen={
                                            form.data.mobile_fullscreen
                                        }
                                        onForm={(value) =>
                                            form.setData('form', value)
                                        }
                                        onFullscreen={(value) =>
                                            form.setData(
                                                'mobile_fullscreen',
                                                value,
                                            )
                                        }
                                        onLaunch={setLaunch}
                                        onPreset={applyPreset}
                                        onToggle={toggleBlock}
                                    />
                                )}
                                {popup && (
                                    <div className="border-t border-[#EEEEEE] pt-3">
                                        {confirmDelete ? (
                                            <CtButton
                                                tone="brown"
                                                className="bg-red-600 hover:bg-red-700"
                                                onClick={remove}
                                            >
                                                Да, удалить попап
                                            </CtButton>
                                        ) : (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 text-sm text-[#8F9496] hover:text-red-600"
                                                onClick={() =>
                                                    setConfirmDelete(true)
                                                }
                                            >
                                                <Trash2
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Удалить попап
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                        {Object.keys(form.errors).length > 0 && (
                            <p
                                role="alert"
                                className="py-3 text-sm text-red-600"
                            >
                                {form.errors.launch ??
                                    'Проверьте настройки попапа.'}
                            </p>
                        )}
                    </>
                }
            >
                {tab === 'conditions' ? (
                    <ConditionsScreen
                        launch={launch}
                        onChange={setLaunch}
                        saving={form.processing}
                        dirty={form.isDirty}
                        onDiscard={() => {
                            form.reset();
                            form.clearErrors();
                        }}
                    />
                ) : (
                    <>
                        <div className="flex justify-center">
                            <div className="inline-flex bg-[#DCD3C7] p-0.5 text-sm">
                                {(
                                    [
                                        ['data', 'Ввод данных'],
                                        [
                                            'confirmation',
                                            'Подтверждение заявки',
                                        ],
                                    ] as const
                                ).map(([id, label]) => (
                                    <button
                                        key={id}
                                        type="button"
                                        aria-pressed={pane === id}
                                        className={cn(
                                            'h-8 px-4',
                                            pane === id
                                                ? 'bg-[#CABCAA] text-[#3F3932]'
                                                : 'text-[#746A5D] hover:text-[#3F3932]',
                                        )}
                                        onClick={() => {
                                            setPane(id);
                                            setSelected(null);
                                        }}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="mt-3">
                            <DeviceToggle
                                device={device}
                                onDevice={(value) => {
                                    setDevice(value);
                                    setSelected(null);
                                }}
                                onPreview={() => setPreview(true)}
                                phone={phoneView}
                            />
                        </div>
                        <div
                            className="relative mt-5 flex justify-center"
                            onClick={() => setSelected(null)}
                        >
                            <div className="relative w-full max-w-[802px]">
                                {device === 'desktop' ? (
                                    <SiteMock className="aspect-auto min-h-[402px]">
                                        <BlurredSite />
                                        <div className={placement}>{card}</div>
                                    </SiteMock>
                                ) : (
                                    <PhoneMock view={phoneView}>
                                        <div
                                            className={cn(
                                                'absolute inset-x-0 flex justify-center p-3',
                                                form.data.mobile_fullscreen
                                                    ? 'inset-y-0 items-stretch'
                                                    : 'bottom-0',
                                            )}
                                        >
                                            {card}
                                        </div>
                                    </PhoneMock>
                                )}
                            </div>
                        </div>
                    </>
                )}
            </StudioFrame>
            {preview && (
                <div
                    role="dialog"
                    aria-label="Предпросмотр попапа"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-6"
                >
                    <button
                        type="button"
                        className="absolute top-4 right-4 text-white"
                        onClick={() => setPreview(false)}
                        aria-label="Закрыть предпросмотр"
                    >
                        <X />
                    </button>
                    {card}
                </div>
            )}
        </form>
    );
}
