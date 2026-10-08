import { Head } from '@inertiajs/react';
import { TemplateList } from '@/components/template-authoring/template-list';
import { dashboard } from '@/routes/developer';
import { create, index } from '@/routes/developer/templates';
import type { AuthoringTemplate } from '@/types/templates';

export default function DeveloperTemplates({
    templates,
}: {
    templates: AuthoringTemplate[];
}) {
    return (
        <>
            <Head title="Шаблоны" />
            <TemplateList
                title="Шаблоны"
                description="Шаблоны вашего профиля разработчика. Публикация проходит автоматические проверки без ручной модерации."
                templates={templates}
                createHref={create()}
            />
        </>
    );
}

DeveloperTemplates.layout = {
    breadcrumbs: [
        { title: 'Студия', href: dashboard() },
        { title: 'Шаблоны', href: index() },
    ],
};
