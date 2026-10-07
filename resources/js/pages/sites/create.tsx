import { Form, Head, Link } from '@inertiajs/react';
import { FilePlus2, LayoutTemplate, Lock } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { create, store } from '@/routes/sites';
import type { CatalogAccessCard } from '@/types/blocks';

type SiteTypeValue = 'multi_page' | 'landing' | 'quiz' | 'chat_selection';

type SiteTypeOption = {
    value: SiteTypeValue;
    label: string;
    description: string;
    allowed: boolean;
    blank_allowed: boolean;
};

type TemplateOption = {
    public_id: string;
    name: string;
    site_types: SiteTypeValue[];
    author: string | null;
    access: CatalogAccessCard;
    available: boolean;
    reason: string | null;
};

type CreateSiteProps = {
    currentWorkspace: {
        public_id: string;
        name: string;
    };
    siteTypes: SiteTypeOption[];
    templates: TemplateOption[];
    siteLimit: {
        active: number;
        max: number;
        reached: boolean;
    };
};

const BLANK = 'blank';

const cardClass =
    'flex min-w-0 cursor-pointer items-start gap-3 rounded-xl border bg-card p-4 shadow-sm transition-colors hover:bg-accent has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/20 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60 has-[:disabled]:hover:bg-card has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring';

function defaultStart(
    type: SiteTypeOption | undefined,
    templates: TemplateOption[],
): string {
    if (!type) {
        return '';
    }

    if (type.blank_allowed) {
        return BLANK;
    }

    return (
        templates.find(
            (template) =>
                template.available && template.site_types.includes(type.value),
        )?.public_id ?? ''
    );
}

export default function CreateSite({
    currentWorkspace,
    siteTypes,
    templates,
    siteLimit,
}: CreateSiteProps) {
    const initialType = siteTypes.find((type) => type.allowed);
    const [siteType, setSiteType] = useState<SiteTypeValue | undefined>(
        initialType?.value,
    );
    const [start, setStart] = useState(() =>
        defaultStart(initialType, templates),
    );

    const selectedType = siteTypes.find((type) => type.value === siteType);
    const compatible = templates.filter(
        (template) =>
            siteType !== undefined && template.site_types.includes(siteType),
    );
    const canSubmit =
        !siteLimit.reached && selectedType?.allowed === true && start !== '';

    const chooseType = (type: SiteTypeOption) => {
        setSiteType(type.value);
        setStart(defaultStart(type, templates));
    };

    return (
        <>
            <Head title="Создание сайта" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="min-w-0 space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        {currentWorkspace.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Создание сайта
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Выберите формат, способ старта и укажите название. Сайт
                        появится в текущем рабочем пространстве.
                    </p>
                </header>

                {siteLimit.reached && (
                    <Alert>
                        <AlertTitle>Достигнут лимит активных сайтов</AlertTitle>
                        <AlertDescription>
                            {`Активных сайтов: ${siteLimit.active} из ${siteLimit.max}. Чтобы создать новый сайт, освободите место в текущем рабочем пространстве.`}
                        </AlertDescription>
                    </Alert>
                )}

                <Form
                    {...store.form()}
                    disableWhileProcessing
                    className="flex max-w-3xl flex-col gap-8"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.site} />

                            <fieldset className="space-y-3">
                                <legend className="mb-3 text-xl font-semibold">
                                    1. Формат
                                </legend>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {siteTypes.map((type) => (
                                        <label
                                            key={type.value}
                                            className={cardClass}
                                        >
                                            <input
                                                type="radio"
                                                name="site_type"
                                                value={type.value}
                                                checked={
                                                    siteType === type.value
                                                }
                                                disabled={!type.allowed}
                                                onChange={() =>
                                                    chooseType(type)
                                                }
                                                className="mt-1 size-4 shrink-0 accent-primary"
                                                aria-describedby={`site-type-${type.value}-hint`}
                                            />
                                            <span className="min-w-0 space-y-1">
                                                <span className="flex items-center gap-2 font-medium break-words">
                                                    {type.label}
                                                    {!type.allowed && (
                                                        <Lock
                                                            aria-hidden="true"
                                                            className="size-4 shrink-0 text-muted-foreground"
                                                        />
                                                    )}
                                                </span>
                                                <span
                                                    id={`site-type-${type.value}-hint`}
                                                    className="block text-sm text-muted-foreground"
                                                >
                                                    {type.allowed
                                                        ? type.description
                                                        : 'Недоступно на текущем тарифе.'}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.site_type} />
                            </fieldset>

                            <fieldset
                                className="space-y-3"
                                aria-describedby={
                                    errors.template || errors.start
                                        ? 'template-error'
                                        : undefined
                                }
                            >
                                <legend className="mb-3 text-xl font-semibold">
                                    2. Старт
                                </legend>
                                <input
                                    type="hidden"
                                    name="start"
                                    value={start === BLANK ? BLANK : 'template'}
                                />
                                {start !== BLANK && start !== '' && (
                                    <input
                                        type="hidden"
                                        name="template"
                                        value={start}
                                    />
                                )}

                                {!selectedType?.blank_allowed &&
                                compatible.length === 0 ? (
                                    <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                                        Для этого формата пока нет шаблонов.
                                        Пустой старт недоступен: квиз и
                                        чат-подбор создаются только из шаблона.
                                    </p>
                                ) : (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {selectedType?.blank_allowed && (
                                            <label className={cardClass}>
                                                <input
                                                    type="radio"
                                                    name="start_option"
                                                    value={BLANK}
                                                    checked={start === BLANK}
                                                    onChange={() =>
                                                        setStart(BLANK)
                                                    }
                                                    className="mt-1 size-4 shrink-0 accent-primary"
                                                />
                                                <FilePlus2
                                                    aria-hidden="true"
                                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                                />
                                                <span className="min-w-0 font-medium">
                                                    Пустой старт
                                                </span>
                                            </label>
                                        )}
                                        {compatible.map((template) => (
                                            <label
                                                key={template.public_id}
                                                className={cardClass}
                                            >
                                                <input
                                                    type="radio"
                                                    name="start_option"
                                                    value={template.public_id}
                                                    checked={
                                                        start ===
                                                        template.public_id
                                                    }
                                                    disabled={
                                                        !template.available
                                                    }
                                                    onChange={() =>
                                                        setStart(
                                                            template.public_id,
                                                        )
                                                    }
                                                    className="mt-1 size-4 shrink-0 accent-primary"
                                                    aria-invalid={Boolean(
                                                        errors.template,
                                                    )}
                                                    aria-describedby={`template-${template.public_id}-hint`}
                                                />
                                                <LayoutTemplate
                                                    aria-hidden="true"
                                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                                />
                                                <span className="min-w-0 space-y-1">
                                                    <span className="flex flex-wrap items-center gap-2 font-medium break-words">
                                                        {template.name}
                                                        {template.access
                                                            .restricted && (
                                                            <Badge variant="outline">
                                                                {
                                                                    template
                                                                        .access
                                                                        .label
                                                                }
                                                            </Badge>
                                                        )}
                                                    </span>
                                                    <span
                                                        id={`template-${template.public_id}-hint`}
                                                        className="block text-sm text-muted-foreground"
                                                    >
                                                        {[
                                                            template.author
                                                                ? `Автор: ${template.author}`
                                                                : 'Шаблон Landflow',
                                                            template.access
                                                                .detail,
                                                            template.reason,
                                                        ]
                                                            .filter(Boolean)
                                                            .join('. ')}
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                )}

                                <InputError
                                    id="template-error"
                                    message={errors.template ?? errors.start}
                                />
                            </fieldset>

                            <fieldset className="space-y-3">
                                <legend className="mb-3 text-xl font-semibold">
                                    3. Название
                                </legend>
                                <div className="grid max-w-md gap-2">
                                    <Label htmlFor="name">Название сайта</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        maxLength={255}
                                        autoComplete="off"
                                        placeholder="Например, Автосалон Север"
                                        aria-invalid={Boolean(errors.name)}
                                        aria-describedby={
                                            errors.name
                                                ? 'name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="name-error"
                                        message={errors.name}
                                    />
                                </div>
                            </fieldset>

                            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                                <Button variant="outline" asChild>
                                    <Link href={dashboard()}>Отмена</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={!canSubmit || processing}
                                >
                                    {processing && <Spinner />}
                                    Создать сайт
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}

CreateSite.layout = {
    breadcrumbs: [
        {
            title: 'Все сайты',
            href: dashboard(),
        },
        {
            title: 'Создание сайта',
            href: create(),
        },
    ],
};
