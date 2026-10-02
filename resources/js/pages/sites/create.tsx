import { Form, Head, Link } from '@inertiajs/react';
import { LayoutTemplate } from 'lucide-react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { create, store } from '@/routes/sites';

type TemplateOption = {
    public_id: string;
    name: string;
};

type CreateSiteProps = {
    currentWorkspace: {
        public_id: string;
        name: string;
    };
    templates: TemplateOption[];
    siteLimit: {
        active: number;
        max: number;
        reached: boolean;
    };
};

export default function CreateSite({
    currentWorkspace,
    templates,
    siteLimit,
}: CreateSiteProps) {
    const canSubmit = !siteLimit.reached && templates.length > 0;

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
                        Выберите шаблон и укажите название. Сайт появится в
                        текущем рабочем пространстве.
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

                            <fieldset
                                className="space-y-3"
                                aria-describedby={
                                    errors.template
                                        ? 'template-error'
                                        : undefined
                                }
                            >
                                <legend className="mb-3 text-xl font-semibold">
                                    1. Шаблон
                                </legend>

                                {templates.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Сейчас нет доступных шаблонов.
                                    </p>
                                ) : (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {templates.map((template, index) => (
                                            <label
                                                key={template.public_id}
                                                className="flex min-w-0 cursor-pointer items-center gap-3 rounded-xl border bg-card p-4 shadow-sm transition-colors hover:bg-accent has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/20 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring"
                                            >
                                                <input
                                                    type="radio"
                                                    name="template"
                                                    value={template.public_id}
                                                    defaultChecked={index === 0}
                                                    required
                                                    className="size-4 shrink-0 accent-primary"
                                                    aria-invalid={Boolean(
                                                        errors.template,
                                                    )}
                                                />
                                                <LayoutTemplate
                                                    aria-hidden="true"
                                                    className="size-5 shrink-0 text-muted-foreground"
                                                />
                                                <span className="min-w-0 font-medium break-words">
                                                    {template.name}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                )}

                                <InputError
                                    id="template-error"
                                    message={errors.template}
                                />
                            </fieldset>

                            <fieldset className="space-y-3">
                                <legend className="mb-3 text-xl font-semibold">
                                    2. Название
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
            title: 'Панель управления',
            href: dashboard(),
        },
        {
            title: 'Создание сайта',
            href: create(),
        },
    ],
};
