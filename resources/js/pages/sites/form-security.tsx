import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { BlacklistSection } from '@/components/forms/blacklist-section';
import type { BlacklistRow } from '@/components/forms/blacklist-section';
import type { Choice } from '@/components/platform/form-fields';
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
import { dashboard } from '@/routes';
import { designer } from '@/routes/sites';
import { update } from '@/routes/sites/form-security';

type NumericKey =
    | 'ip_limit'
    | 'ip_window_minutes'
    | 'phone_limit'
    | 'phone_window_minutes'
    | 'duplicate_window_minutes';

type Policy = Record<NumericKey, number> & { captcha_required: boolean };

type FormSecurityProps = {
    site: { public_id: string; name: string };
    policy: Policy;
    defaults: Policy;
    bounds: Record<NumericKey, [number, number]>;
    blacklist: { site: BlacklistRow[]; workspace: BlacklistRow[] };
    choices: { types: Choice[] };
    captcha: { configured: boolean };
    can: { edit: boolean; editWorkspace: boolean };
};

const groups: {
    title: string;
    description: string;
    fields: { key: NumericKey; label: string }[];
}[] = [
    {
        title: 'Ограничение по IP-адресу',
        description: 'Сколько заявок можно отправить с одного IP за период.',
        fields: [
            { key: 'ip_limit', label: 'Заявок с одного IP' },
            { key: 'ip_window_minutes', label: 'Период, минут' },
        ],
    },
    {
        title: 'Ограничение по телефону',
        description:
            'Сколько заявок с одним номером телефона принимает сайт за период. Проверяется, только если в форме есть телефон.',
        fields: [
            { key: 'phone_limit', label: 'Заявок с одного телефона' },
            { key: 'phone_window_minutes', label: 'Период, минут' },
        ],
    },
    {
        title: 'Повторные заявки',
        description:
            'Повторная заявка с тем же телефоном в ту же форму не принимается в течение интервала. 0 — проверка выключена.',
        fields: [
            {
                key: 'duplicate_window_minutes',
                label: 'Интервал повторной заявки, минут',
            },
        ],
    },
];

export default function FormSecurity({
    site,
    policy,
    defaults,
    bounds,
    blacklist,
    choices,
    captcha,
    can,
}: FormSecurityProps) {
    const form = useForm({
        ip_limit: String(policy.ip_limit),
        ip_window_minutes: String(policy.ip_window_minutes),
        phone_limit: String(policy.phone_limit),
        phone_window_minutes: String(policy.phone_window_minutes),
        duplicate_window_minutes: String(policy.duplicate_window_minutes),
        captcha_required: policy.captcha_required,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(update(site.public_id), { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Защита форм — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Защита форм
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Правила действуют для всех форм сайта. Отклонённые
                            заявки не сохраняются.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={designer(site.public_id)}>
                            Открыть дизайнер
                        </Link>
                    </Button>
                </header>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <fieldset
                        disabled={!can.edit}
                        className="grid gap-6 lg:grid-cols-3"
                    >
                        <legend className="sr-only">Правила защиты</legend>
                        {groups.map((group) => (
                            <Card key={group.title}>
                                <CardHeader>
                                    <h2 className="leading-none font-semibold">
                                        {group.title}
                                    </h2>
                                    <CardDescription>
                                        {group.description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    {group.fields.map((field) => (
                                        <TextField
                                            key={field.key}
                                            id={`security-${field.key}`}
                                            label={field.label}
                                            hint={`По умолчанию: ${defaults[field.key]}. Допустимо от ${bounds[field.key][0]} до ${bounds[field.key][1]}.`}
                                            type="number"
                                            inputMode="numeric"
                                            min={bounds[field.key][0]}
                                            max={bounds[field.key][1]}
                                            value={form.data[field.key]}
                                            onChange={(event) =>
                                                form.setData(
                                                    field.key,
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            error={form.errors[field.key]}
                                        />
                                    ))}
                                </CardContent>
                            </Card>
                        ))}
                        <Card className="lg:col-span-3">
                            <CardHeader>
                                <h2 className="leading-none font-semibold">
                                    Капча
                                </h2>
                                <CardDescription>
                                    Посетитель подтверждает, что он не робот,
                                    через Yandex SmartCaptcha. Если сервис
                                    проверки недоступен, заявка всё равно
                                    принимается.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="security-captcha_required"
                                        checked={form.data.captcha_required}
                                        disabled={
                                            !captcha.configured &&
                                            !form.data.captcha_required
                                        }
                                        aria-describedby="security-captcha-hint"
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'captcha_required',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="security-captcha_required">
                                        Требовать капчу во всех формах сайта
                                    </Label>
                                </div>
                                <p
                                    id="security-captcha-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    {captcha.configured
                                        ? 'Капча подключена на платформе.'
                                        : 'Капча пока не подключена на платформе — включить её нельзя.'}
                                </p>
                                <InputError
                                    message={form.errors.captcha_required}
                                />
                            </CardContent>
                        </Card>
                    </fieldset>
                    {can.edit ? (
                        <div>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Сохранить
                            </Button>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Изменять правила может только участник с правом
                            управления настройками сайта.
                        </p>
                    )}
                </form>

                {can.edit && (
                    <BlacklistSection
                        sitePublicId={site.public_id}
                        scope="site"
                        title="Чёрный список сайта"
                        description="Заявки с этих телефонов и IP-адресов не принимаются формами этого сайта."
                        entries={blacklist.site}
                        types={choices.types}
                    />
                )}
                {can.editWorkspace && (
                    <BlacklistSection
                        sitePublicId={site.public_id}
                        scope="workspace"
                        title="Чёрный список рабочего пространства"
                        description="Действует на формы всех сайтов рабочего пространства."
                        entries={blacklist.workspace}
                        types={choices.types}
                    />
                )}
            </main>
        </>
    );
}

FormSecurity.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
