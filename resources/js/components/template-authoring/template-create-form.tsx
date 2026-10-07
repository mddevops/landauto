import { Form, Link } from '@inertiajs/react';
import { TextField } from '@/components/platform/form-fields';
import type { Choice } from '@/components/platform/form-fields';
import { SiteTypeFields } from '@/components/template-authoring/site-type-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { RouteDefinition, RouteFormDefinition } from '@/wayfinder';

export function TemplateCreateForm({
    title,
    description,
    action,
    cancelHref,
    siteTypes,
}: {
    title: string;
    description: string;
    action: RouteFormDefinition<'post'>;
    cancelHref: RouteDefinition<'get'>;
    siteTypes: Choice[];
}) {
    return (
        <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
            <header className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {title}
                </h1>
                <p className="text-sm text-muted-foreground">{description}</p>
            </header>

            <Form
                {...action}
                disableWhileProcessing
                className="grid w-full max-w-xl gap-4 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
            >
                {({ processing, errors }) => (
                    <>
                        <TextField
                            id="template-name"
                            name="name"
                            label="Название"
                            required
                            maxLength={100}
                            autoComplete="off"
                            error={errors.name}
                        />
                        <TextField
                            id="template-slug"
                            name="slug"
                            label="Slug"
                            required
                            minLength={3}
                            maxLength={60}
                            autoComplete="off"
                            spellCheck={false}
                            hint="Строчные латинские буквы, цифры и дефисы, 3–60 символов. После создания не меняется."
                            error={errors.slug}
                        />
                        <SiteTypeFields
                            choices={siteTypes}
                            selected={[]}
                            error={errors.site_types}
                        />
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href={cancelHref}>Отмена</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Создать шаблон
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </main>
    );
}
