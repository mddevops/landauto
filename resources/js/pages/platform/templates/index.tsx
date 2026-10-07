import { Head } from '@inertiajs/react';
import { TemplateList } from '@/components/template-authoring/template-list';
import { create, index } from '@/routes/platform/templates';
import type { AuthoringTemplate } from '@/types/templates';

export default function PlatformTemplates({
    templates,
}: {
    templates: AuthoringTemplate[];
}) {
    return (
        <>
            <Head title="Шаблоны Landflow" />
            <TemplateList
                title="Шаблоны Landflow"
                description="Официальные шаблоны платформы. Сайт из шаблона создаётся по опубликованной версии."
                templates={templates}
                createHref={create()}
            />
        </>
    );
}

PlatformTemplates.layout = {
    breadcrumbs: [{ title: 'Шаблоны Landflow', href: index() }],
};
