import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { TextField } from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/sites/analytics';

export type MetricaSettings = {
    enabled: boolean;
    counter_id: string | null;
    clickmap: boolean;
    track_links: boolean;
    accurate_track_bounce: boolean;
    webvisor: boolean;
};

type ToggleKey =
    | 'clickmap'
    | 'track_links'
    | 'accurate_track_bounce'
    | 'webvisor';

const toggles: { key: ToggleKey; label: string }[] = [
    { key: 'clickmap', label: 'Карта кликов' },
    { key: 'track_links', label: 'Внешние ссылки, загрузки файлов' },
    { key: 'accurate_track_bounce', label: 'Точный показатель отказов' },
    { key: 'webvisor', label: 'Вебвизор (запись действий посетителей)' },
];

export function MetricaSettingsCard({
    sitePublicId,
    settings,
    canManage,
}: {
    sitePublicId: string;
    settings: MetricaSettings;
    canManage: boolean;
}) {
    const form = useForm({
        enabled: settings.enabled,
        counter_id: settings.counter_id ?? '',
        clickmap: settings.clickmap,
        track_links: settings.track_links,
        accurate_track_bounce: settings.accurate_track_bounce,
        webvisor: settings.webvisor,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            counter_id:
                data.counter_id.trim() === '' ? null : data.counter_id.trim(),
        }));
        form.submit(update(sitePublicId), { preserveScroll: true });
    }

    return (
        <Card>
            <CardHeader>
                <h2 className="leading-none font-semibold">Яндекс Метрика</h2>
                <CardDescription>
                    Счётчик посещаемости и цели по формам и всплывающим окнам.
                    Изменения появятся на сайте после публикации; в
                    предпросмотре счётчик не работает.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <fieldset
                        disabled={!canManage}
                        className="flex flex-col gap-4"
                    >
                        <legend className="sr-only">
                            Настройки Яндекс Метрики
                        </legend>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="metrica-enabled"
                                checked={form.data.enabled}
                                onCheckedChange={(checked) =>
                                    form.setData('enabled', checked === true)
                                }
                            />
                            <Label htmlFor="metrica-enabled">
                                Подключить счётчик
                            </Label>
                        </div>
                        <InputError message={form.errors.enabled} />
                        <TextField
                            id="metrica-counter_id"
                            label="Номер счётчика"
                            hint="Только цифры — номер из интерфейса Яндекс Метрики."
                            inputMode="numeric"
                            autoComplete="off"
                            value={form.data.counter_id}
                            onChange={(event) =>
                                form.setData('counter_id', event.target.value)
                            }
                            required={form.data.enabled}
                            error={form.errors.counter_id}
                        />
                        <div className="grid gap-2 sm:grid-cols-2">
                            {toggles.map((toggle) => (
                                <div
                                    key={toggle.key}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`metrica-${toggle.key}`}
                                        checked={form.data[toggle.key]}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                toggle.key,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor={`metrica-${toggle.key}`}>
                                        {toggle.label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </fieldset>
                    {canManage ? (
                        <div>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Сохранить
                            </Button>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Изменять настройки может только участник с правом
                            управления интеграциями.
                        </p>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}
