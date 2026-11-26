import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { FloatingLauncherButton } from '@/popups/floating-launcher-button';
import { defaultLauncher } from '@/popups/launcher';
import type { FloatingLauncher, LauncherDevice } from '@/popups/launcher';

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function DeviceFields({
    device,
    kind,
    onChange,
}: {
    device: LauncherDevice;
    kind: 'desktop' | 'mobile';
    onChange: (device: LauncherDevice) => void;
}) {
    const sizes = kind === 'desktop' ? [44, 64, 80, 100] : [44, 64, 80];
    const custom = !sizes.includes(device.size);

    return (
        <div className="grid gap-3">
            <div className="grid gap-1.5">
                <Label>Тип</Label>
                <select
                    className={selectClass}
                    value={device.variant}
                    onChange={(event) =>
                        onChange({ ...device, variant: event.target.value })
                    }
                >
                    <option value="standard">Стандартная</option>
                    {kind === 'desktop' ? (
                        <option value="bookmark">Закладка</option>
                    ) : (
                        <option value="full_width">Во всю ширину</option>
                    )}
                </select>
            </div>
            <div className="grid gap-1.5">
                <Label>Размер кнопки</Label>
                <select
                    className={selectClass}
                    value={custom ? 'custom' : String(device.size)}
                    onChange={(event) => {
                        if (event.target.value === 'custom') {
                            onChange({ ...device, size: device.size });

                            return;
                        }

                        onChange({
                            ...device,
                            size: Number(event.target.value),
                        });
                    }}
                >
                    {sizes.map((size) => (
                        <option key={size} value={size}>
                            {size} px
                        </option>
                    ))}
                    <option value="custom">Задать вручную</option>
                </select>
                {custom && (
                    <Input
                        type="number"
                        min={44}
                        max={kind === 'desktop' ? 100 : 80}
                        value={device.size}
                        onChange={(event) =>
                            onChange({
                                ...device,
                                size: Number(event.target.value),
                            })
                        }
                    />
                )}
            </div>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={device.text_instead_of_icon}
                    onCheckedChange={(checked) =>
                        onChange({
                            ...device,
                            text_instead_of_icon: checked === true,
                        })
                    }
                />
                Текст вместо иконки
            </label>
            {device.text_instead_of_icon || device.variant === 'bookmark' ? (
                <div className="grid gap-1.5">
                    <Label>Текст на кнопке</Label>
                    <Input
                        value={device.text}
                        maxLength={40}
                        onChange={(event) =>
                            onChange({ ...device, text: event.target.value })
                        }
                    />
                </div>
            ) : (
                <div className="grid gap-1.5">
                    <Label>Иконка</Label>
                    <select
                        className={selectClass}
                        value={device.icon}
                        onChange={(event) =>
                            onChange({
                                ...device,
                                icon: event.target
                                    .value as LauncherDevice['icon'],
                            })
                        }
                    >
                        <option value="phone">Трубка</option>
                        <option value="message">Сообщение</option>
                        <option value="car">Автомобиль</option>
                    </select>
                </div>
            )}
            {device.variant === 'bookmark' && (
                <>
                    <div className="grid gap-1.5">
                        <Label>Ширина кнопки</Label>
                        <Input
                            type="number"
                            min={80}
                            max={360}
                            value={device.width}
                            onChange={(event) =>
                                onChange({
                                    ...device,
                                    width: Number(event.target.value),
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Подзаголовок</Label>
                        <Input
                            value={device.subtitle}
                            maxLength={80}
                            onChange={(event) =>
                                onChange({
                                    ...device,
                                    subtitle: event.target.value,
                                })
                            }
                        />
                    </div>
                </>
            )}
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={device.auto_icon_size}
                    onCheckedChange={(checked) =>
                        onChange({
                            ...device,
                            auto_icon_size: checked === true,
                        })
                    }
                />
                Автоматический размер иконки
            </label>
            {!device.auto_icon_size && (
                <div className="grid gap-1.5">
                    <Label>Размер иконки</Label>
                    <Input
                        type="number"
                        min={12}
                        max={64}
                        value={device.icon_size}
                        onChange={(event) =>
                            onChange({
                                ...device,
                                icon_size: Number(event.target.value),
                            })
                        }
                    />
                </div>
            )}
            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label>При наведении</Label>
                    <select
                        className={selectClass}
                        value={device.hover}
                        onChange={(event) =>
                            onChange({
                                ...device,
                                hover: event.target
                                    .value as LauncherDevice['hover'],
                            })
                        }
                    >
                        <option value="none">Без анимации</option>
                        <option value="dialog">Всплывающий диалог</option>
                        <option value="scale">Увеличение</option>
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label>Анимация покоя</Label>
                    <select
                        className={selectClass}
                        value={device.idle}
                        onChange={(event) =>
                            onChange({
                                ...device,
                                idle: event.target
                                    .value as LauncherDevice['idle'],
                            })
                        }
                    >
                        <option value="none">Без анимации</option>
                        <option value="pulse">Пульсация</option>
                        <option value="bounce">Подпрыгивание</option>
                    </select>
                </div>
            </div>
        </div>
    );
}

export function FloatingLauncherEditor({
    sitePublicId,
    launcher,
    popups,
}: {
    sitePublicId: string;
    launcher: FloatingLauncher;
    popups: { public_id: string; name: string; status: boolean }[];
}) {
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const form = useForm({ ...defaultLauncher(), ...launcher });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(`/sites/${sitePublicId}/popups-launcher`, {
            preserveScroll: true,
        });
    }

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 rounded-xl border bg-card p-4 shadow-sm"
        >
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="font-semibold">Плавающая кнопка</h2>
                    <p className="text-sm text-muted-foreground">
                        Отдельная кнопка сайта, не связанная с блоками
                        конструктора. Создание кнопки не создаёт скрытый попап.
                    </p>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.enabled}
                        onCheckedChange={(checked) =>
                            form.setData('enabled', checked === true)
                        }
                    />
                    Включить
                </label>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="launcher-popup">Попап</Label>
                <select
                    id="launcher-popup"
                    className={selectClass}
                    value={form.data.popup ?? ''}
                    onChange={(event) =>
                        form.setData('popup', event.target.value || null)
                    }
                >
                    <option value="">Не выбран</option>
                    {popups.map((popup) => (
                        <option key={popup.public_id} value={popup.public_id}>
                            {popup.status
                                ? popup.name
                                : `${popup.name} (выключен)`}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.popup} />
            </div>
            <Tabs
                value={device}
                onValueChange={(value) =>
                    setDevice(value === 'mobile' ? 'mobile' : 'desktop')
                }
            >
                <TabsList>
                    <TabsTrigger value="desktop">Desktop</TabsTrigger>
                    <TabsTrigger value="mobile">Mobile</TabsTrigger>
                </TabsList>
                <TabsContent value="desktop" className="mt-3 grid gap-4">
                    <DeviceFields
                        device={form.data.desktop}
                        kind="desktop"
                        onChange={(desktop) => form.setData('desktop', desktop)}
                    />
                </TabsContent>
                <TabsContent value="mobile" className="mt-3 grid gap-4">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            form.setData('mobile', {
                                ...form.data.desktop,
                                variant:
                                    form.data.desktop.variant === 'bookmark'
                                        ? 'standard'
                                        : form.data.mobile.variant,
                            })
                        }
                    >
                        Копировать с Desktop
                    </Button>
                    <DeviceFields
                        device={form.data.mobile}
                        kind="mobile"
                        onChange={(mobile) => form.setData('mobile', mobile)}
                    />
                </TabsContent>
            </Tabs>
            <fieldset className="grid gap-3 rounded-md border p-3">
                <legend className="px-1 text-sm font-medium">
                    Всплывающий диалог
                </legend>
                <div className="grid gap-1.5">
                    <Label>Заголовок</Label>
                    <Input
                        value={form.data.dialog.title}
                        maxLength={120}
                        onChange={(event) =>
                            form.setData('dialog', {
                                ...form.data.dialog,
                                title: event.target.value,
                            })
                        }
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label>Подзаголовок</Label>
                    <Input
                        value={form.data.dialog.subtitle}
                        maxLength={200}
                        onChange={(event) =>
                            form.setData('dialog', {
                                ...form.data.dialog,
                                subtitle: event.target.value,
                            })
                        }
                    />
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label>Время до открытия, сек</Label>
                        <Input
                            type="number"
                            min={0}
                            max={120}
                            value={form.data.dialog.open_delay_seconds}
                            onChange={(event) =>
                                form.setData('dialog', {
                                    ...form.data.dialog,
                                    open_delay_seconds: Number(
                                        event.target.value,
                                    ),
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Время показа, сек</Label>
                        <Input
                            type="number"
                            min={1}
                            max={30}
                            value={form.data.dialog.show_seconds}
                            onChange={(event) =>
                                form.setData('dialog', {
                                    ...form.data.dialog,
                                    show_seconds: Number(event.target.value),
                                })
                            }
                        />
                    </div>
                </div>
                <div className="grid gap-1.5">
                    <Label>Текст кнопки</Label>
                    <Input
                        value={form.data.dialog.cta_label}
                        maxLength={40}
                        onChange={(event) =>
                            form.setData('dialog', {
                                ...form.data.dialog,
                                cta_label: event.target.value,
                            })
                        }
                    />
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.dialog.open_popup}
                        onCheckedChange={(checked) =>
                            form.setData('dialog', {
                                ...form.data.dialog,
                                open_popup: checked === true,
                            })
                        }
                    />
                    CTA открывает выбранный попап
                </label>
            </fieldset>
            <div className="rounded-md border bg-muted/30 p-4">
                <p className="mb-3 text-sm font-medium">Предпросмотр</p>
                <FloatingLauncherButton
                    launcher={{ ...form.data, enabled: true }}
                    previewDevice={device}
                    onOpenPopup={() => undefined}
                />
            </div>
            <InputError
                message={
                    (form.errors as Record<string, string | undefined>).launcher
                }
            />
            <div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Сохранить кнопку
                </Button>
            </div>
        </form>
    );
}
